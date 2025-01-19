<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/function/weather.php';

$weather = getWeather();

class DocumentManager
{
    private $conn;
    private $user_id;

    public function __construct($conn, $user_id)
    {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }

    private function checkUploadDirectory()
    {
        $upload_dir = dirname(dirname(__FILE__)) . '/uploads/documents/';

        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("Failed to create directory: " . $upload_dir);
                throw new Exception("Gagal membuat folder uploads/documents. Silakan buat manual.");
            }
        }

        if (!is_writable($upload_dir)) {
            error_log("Directory not writable: " . $upload_dir);
            throw new Exception("Folder uploads/documents tidak bisa ditulis. Cek permission folder.");
        }

        return $upload_dir;
    }

    private function logActivity($activity_type, $description)
    {
        try {
            $sql = "INSERT INTO user_activity (user_id, activity_type, activity_description) 
                    VALUES (:user_id, :activity_type, :description)";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':user_id' => $this->user_id,
                ':activity_type' => $activity_type,
                ':description' => $description
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return false;
        }
    }

    public function getFolders()
    {
        $sql = "WITH RECURSIVE folder_hierarchy AS (
                    SELECT f.*, 0 as level, CAST(name AS CHAR(1000)) as path
                    FROM folders f
                    WHERE parent_id IS NULL
                    
                    UNION ALL
                    
                    SELECT f.*, fh.level + 1, CONCAT(fh.path, ' > ', f.name)
                    FROM folders f
                    INNER JOIN folder_hierarchy fh ON f.parent_id = fh.id
                )
                SELECT fh.*, 
                       (SELECT COUNT(*) FROM documents d WHERE d.folder_id = fh.id) as doc_count
                FROM folder_hierarchy fh
                ORDER BY path";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function uploadDocument($title, $folder_id, $file)
    {
        try {
            $upload_dir = $this->checkUploadDirectory();

            $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_types)) {
                throw new Exception("Tipe file tidak diizinkan.");
            }

            if ($file['size'] > 5242880) {
                throw new Exception("Ukuran file maksimal 5MB.");
            }

            $new_filename = preg_replace("/[^a-zA-Z0-9.]/", "_", $file['name']);

            $counter = 1;
            $filename_without_ext = pathinfo($new_filename, PATHINFO_FILENAME);
            while (file_exists($upload_dir . $new_filename)) {
                $new_filename = $filename_without_ext . "($counter)." . $file_ext;
                $counter++;
            }

            $upload_path = $upload_dir . $new_filename;

            $this->conn->beginTransaction();

            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception("Gagal mengupload file.");
            }

            $sql = "INSERT INTO documents (title, folder_id, file_name, file_type, file_size, uploaded_by) 
                VALUES (:title, :folder_id, :file_name, :file_type, :file_size, :uploaded_by)";
            $stmt = $this->conn->prepare($sql);

            $result = $stmt->execute([
                ':title' => $title,
                ':folder_id' => $folder_id,
                ':file_name' => $new_filename,
                ':file_type' => $file_ext,
                ':file_size' => $file['size'],
                ':uploaded_by' => $this->user_id
            ]);

            if (!$result) {
                throw new Exception("Gagal menyimpan data dokumen.");
            }

            if (!$this->logActivity('upload_document', "Upload dokumen: $title")) {
                throw new Exception("Gagal mencatat aktivitas.");
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            if (isset($upload_path) && file_exists($upload_path)) {
                unlink($upload_path);
            }

            throw $e;
        }
    }

    public function uploadLink($title, $url, $folder_id = null)
    {
        try {
            $this->conn->beginTransaction();

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception("URL tidak valid. Pastikan URL dimulai dengan http:// atau https://");
            }

            $sql = "INSERT INTO documents (title, file_name, file_type, file_size, uploaded_by, is_link, link_url, folder_id) 
                    VALUES (:title, :file_name, 'link', 0, :uploaded_by, 1, :url, :folder_id)";
            $stmt = $this->conn->prepare($sql);

            $result = $stmt->execute([
                ':title' => $title,
                ':file_name' => basename($url),
                ':uploaded_by' => $this->user_id,
                ':url' => $url,
                ':folder_id' => $folder_id
            ]);

            if (!$result) {
                throw new Exception("Gagal menyimpan data link.");
            }

            $folder_info = '';
            if ($folder_id) {
                $folder_stmt = $this->conn->prepare("SELECT name FROM folders WHERE id = ?");
                $folder_stmt->execute([$folder_id]);
                $folder = $folder_stmt->fetch(PDO::FETCH_ASSOC);
                $folder_info = $folder ? " di folder " . $folder['name'] : "";
            }

            if (!$this->logActivity('upload_link', "Upload link: $title$folder_info")) {
                throw new Exception("Gagal mencatat aktivitas.");
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function getDocuments($page = 1, $limit = 10, $search = '', $folder_id = null)
    {
        $offset = ($page - 1) * $limit;

        $where_conditions = ["d.uploaded_by = :user_id"];
        $params = [':user_id' => $this->user_id];

        if (!empty($search)) {
            $where_conditions[] = "(d.title LIKE :search OR f.name LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if (!empty($folder_id)) {
            $where_conditions[] = "d.folder_id = :folder_id";
            $params[':folder_id'] = $folder_id;
        }

        $where_clause = implode(" AND ", $where_conditions);

        $count_sql = "SELECT COUNT(*) 
                     FROM documents d 
                     LEFT JOIN folders f ON d.folder_id = f.id 
                     WHERE $where_clause";
        $count_stmt = $this->conn->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();

        $sql = "SELECT d.*, f.name as folder_name, u.username as uploader_name,
                CASE WHEN d.is_link = 1 THEN d.link_url ELSE NULL END as link_url
                FROM documents d 
                LEFT JOIN folders f ON d.folder_id = f.id 
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE $where_clause 
                ORDER BY d.created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'documents' => $documents,
            'total_pages' => ceil($total_records / $limit),
            'current_page' => $page,
            'total_records' => $total_records
        ];
    }

    public function editDocument($document_id, $title, $folder_id)
    {
        try {

            $check_sql = "SELECT * FROM documents WHERE id = ? AND uploaded_by = ?";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->execute([$document_id, $this->user_id]);

            if (!$check_stmt->fetch()) {
                throw new Exception("Dokumen tidak ditemukan atau Anda tidak memiliki akses.");
            }


            $sql = "UPDATE documents SET title = ?, folder_id = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ? AND uploaded_by = ?";
            $stmt = $this->conn->prepare($sql);
            $success = $stmt->execute([$title, $folder_id, $document_id, $this->user_id]);

            if (!$success) {
                throw new Exception("Gagal mengupdate dokumen.");
            }

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function deleteDocument($document_id)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "SELECT d.*, f.name as folder_name 
                   FROM documents d
                   LEFT JOIN folders f ON d.folder_id = f.id 
                   WHERE d.id = :id AND d.uploaded_by = :user_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $document_id, ':user_id' => $this->user_id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                throw new Exception("Dokumen tidak ditemukan.");
            }

            if (!$document['is_link']) {
                $file_path = '../../uploads/' . $document['file_name'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            $sql = "DELETE FROM documents WHERE id = :id AND uploaded_by = :user_id";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([':id' => $document_id, ':user_id' => $this->user_id]);

            if (!$result) {
                throw new Exception("Gagal menghapus dokumen.");
            }

            if (
                !$this->logActivity(
                    'delete_document',
                    "Hapus " . ($document['is_link'] ? "link" : "dokumen") . ": {$document['title']}" .
                    ($document['folder_name'] ? " dari folder: {$document['folder_name']}" : "")
                )
            ) {
                throw new Exception("Gagal mencatat aktivitas penghapusan.");
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function getCurrentUser()
    {
        $sql = "SELECT id, username, fullname, email, profile_photo 
                FROM users WHERE id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $this->user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$document_manager = new DocumentManager($conn, $_SESSION['user_id']);


if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $sql = "SELECT * FROM documents WHERE id = ? AND uploaded_by = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$edit_id, $_SESSION['user_id']]);
    $document_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_document'])) {
    try {
        $document_id = $_POST['document_id'];
        $new_title = $_POST['title'];
        $new_folder_id = $_POST['folder_id'];

        $document_manager->editDocument($document_id, $new_title, $new_folder_id);

        $_SESSION['success'] = "Dokumen berhasil diupdate.";
        header("Location: document.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$currentUser = $document_manager->getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    try {
        $document_manager->uploadDocument(
            $_POST['title'],
            $_POST['folder_id'],
            $_FILES['document']
        );
        $_SESSION['success'] = "Dokumen berhasil diupload.";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_link'])) {
    try {
        $document_manager->uploadLink(
            $_POST['link_title'],
            $_POST['link_url'],
            $_POST['folder_id']
        );
        $_SESSION['success'] = "Link berhasil ditambahkan.";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    try {
        $document_manager->deleteDocument($_POST['document_id']);
        $_SESSION['success'] = "Dokumen berhasil dihapus.";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$folder_id = isset($_GET['folder_id']) ? $_GET['folder_id'] : null;

$result = $document_manager->getDocuments($page, 10, $search, $folder_id);
$documents = $result['documents'];
$total_pages = $result['total_pages'];

$folders = $document_manager->getFolders();

function formatSize($size)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Folder - Dashboard Pengguna</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="../public/js/clock.js"></script>
</head>

<body class="antialiased">
    <!-- Header -->
    <header class="bg-white shadow-md fixed top-0 left-0 right-0 z-50">
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-24">
            <!-- Logo -->
            <div class="flex items-center space-x-4">
                <img src="../public/images/logo.png" alt="Logo" class="h-[180px] mt-4 w-auto">
            </div>
            <!-- Weather, Time, Date in Center -->
            <div class="absolute left-1/2 transform -translate-x-1/2 flex items-center space-x-4">
                <div class="flex items-center space-x-2 bg-gray-100 px-3 py-1 rounded-full shadow">
                    <i class="ri-sun-line text-gray-600"></i>
                    <span class="text-gray-700 font-medium">
                        <?php
                        if (isset($weather['error'])) {
                            echo htmlspecialchars($weather['error']);
                        } else {
                            echo htmlspecialchars($weather['temperature']) . '°C';
                        }
                        ?>
                    </span>
                </div>
                <span class="text-gray-700 font-medium">
                    <i class="bi bi-clock"></i>
                    <span id="clock"></span>
                </span>
                <span class="text-gray-700 font-medium">
                    <i class="bi bi-calendar"></i>
                    <span id="date"></span>
                </span>
            </div>
            <!-- Profile and Dropdown -->
            <div class="flex items-center space-x-4 ml-auto">
                <h2 class="text-xl font-semibold text-gray-700">
                    <span id="greeting"></span>,
                </h2>
                <!-- Profile and Dropdown -->
                <button class="focus:outline-none" id="profileToggle">
                    <?php
                    function getValidProfilePhoto($currentUser)
                    {
                        if (empty($currentUser['profile_photo'])) {
                            return '../public/images/pp.jpg';
                        }
                        $photoPath = '../uploads/profile_photos/' . $currentUser['profile_photo'];
                        return file_exists($photoPath) ? $photoPath : '../public/images/pp.jpg';
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars(getValidProfilePhoto($currentUser)); ?>" alt="Profil"
                        class="h-10 w-10 rounded-full cursor-pointer ring-2 ring-gray-700">
                </button>
                <span class="text-gray-700 font-bold">
                    <?php echo htmlspecialchars($currentUser['username'] ?? 'Guest'); ?>
                </span>
            </div>
        </div>
    </header>

    <div class="flex h-screen pt-24">
        <!-- Modern Sidebar -->
        <div class="w-64 bg-gray-800 text-white min-h-screen p-6">
            <div class="flex items-center mb-8">
                <i class="ri-folder-line mr-3 text-4xl"></i>
                <h1 class="text-xl font-bold">Dokumen Saya</h1>
            </div>

            <nav>
                <ul class="space-y-2">
                    <li>
                        <a href="home.php" class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition">
                            <i class="ri-home-line mr-3"></i>Home
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-4 py-2 rounded-lg bg-white/20 transition">
                            <i class="ri-folder-2-line mr-3"></i>Dokumen Saya
                        </a>
                    </li>
                    <?php foreach ($folders as $folder): ?>
                        <li style="margin-left: <?php echo $folder['level'] * 20; ?>px">
                            <a href="?folder_id=<?php echo $folder['id']; ?>"
                                class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition">
                                <i class="ri-folder-line mr-3"></i>
                                <?php echo htmlspecialchars($folder['name']); ?>
                                <span class="ml-auto text-sm opacity-75">(<?php echo $folder['doc_count']; ?>)</span>
                            </a>
                        </li>
                    <?php endforeach; ?>

                    <!-- Tambahkan separator sebelum menu logout -->
                    <li class="border-t border-gray-700 my-4"></li>

                    <!-- Menu Logout -->
                    <li>
                        <a href="../pengaturan.php?return_to=document.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition">
                            <i class="ri-settings-3-line mr-3"></i>Pengaturan
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-red-500/20 transition text-red-400">
                            <i class="ri-logout-box-line mr-3"></i>Keluar
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto main-content p-8">
            <!-- Content Header -->
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Dokumen Saya</h1>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <?php
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <?php
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="mb-6 flex justify-between items-center">
                <div class="flex space-x-4">
                    <form class="flex space-x-4">
                        <input type="hidden" name="folder_id" value="<?php echo $folder_id; ?>">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Cari dokumen..." class="border rounded-lg px-3 py-2 w-64">
                        <button type="submit"
                            class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
                            <i class="ri-search-line"></i>
                        </button>
                    </form>
                </div>
                <div class="flex space-x-3">
                    <button onclick="document.getElementById('uploadModal').classList.remove('hidden')"
                        class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition flex items-center">
                        <i class="ri-add-line mr-2"></i> Unggah Dokumen
                    </button>
                    <button onclick="document.getElementById('linkModal').classList.remove('hidden')"
                        class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition flex items-center">
                        <i class="ri-link mr-2"></i> Tambah Link
                    </button>
                </div>
            </div>


            <!-- Documents Table -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-2">Nama Dokumen</th>
                            <th class="text-left py-3 px-2">Folder</th>
                            <th class="text-left py-3 px-2">Ukuran</th>
                            <th class="text-left py-3 px-2">Tanggal Dibuat</th>
                            <th class="text-left py-3 px-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="py-3 px-2 flex items-center">
                                    <i class="ri-file-line mr-2 text-gray-700"></i>
                                    <?php echo htmlspecialchars($document['title']); ?>
                                </td>
                                <td class="py-3 px-2">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                        <?php echo htmlspecialchars($document['folder_name'] ?? 'No Folder'); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-2"><?php echo formatSize($document['file_size']); ?></td>
                                <td class="py-3 px-2"><?php echo date('d M Y', strtotime($document['created_at'])); ?></td>
                                <td class="py-3 px-2">
                                    <div class="flex space-x-2">
                                        <?php if ($document['is_link']): ?>
                                            <a href="<?php echo htmlspecialchars($document['link_url']); ?>" target="_blank"
                                                class="text-blue-600 hover:text-blue-900 transition-colors duration-200"
                                                title="Buka Tautan">
                                                <i class="ri-external-link-line"></i>
                                            </a>
                                        <?php else: ?>
                                            <!-- Tombol Lihat -->
                                            <a href="view_document.php?id=<?php echo $document['id']; ?>&action=view"
                                                class="text-green-600 hover:text-green-900 transition-colors duration-200"
                                                title="Lihat Dokumen" target="_blank">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <!-- Tombol Unduh -->
                                            <a href="view_document.php?id=<?php echo $document['id']; ?>&action=download"
                                                class="text-black-600 hover:text-black-900 transition-colors duration-200"
                                                title="Unduh Dokumen">
                                                <i class="ri-download-line"></i>
                                            </a>
                                            <!-- Tombol Edit -->
                                            <a href="?edit=<?php echo $document['id']; ?>"
                                                class="text-blue-600 hover:text-blue-900 transition-colors duration-200"
                                                title="Ubah Dokumen">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                        <?php endif; ?>
                                        <!-- Tombol Hapus -->
                                        <form method="POST" class="inline"
                                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus <?php echo $document['is_link'] ? 'tautan' : 'dokumen'; ?> ini?');">
                                            <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                            <button type="submit" name="delete_document"
                                                class="text-red-500 hover:text-red-700 transition-colors duration-200"
                                                title="Hapus">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex justify-end items-center mt-6 space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&folder_id=<?php echo $folder_id; ?>"
                                class="bg-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-400 transition">&lt;</a>
                        <?php endif; ?>

                        <div class="flex items-center space-x-2">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&folder_id=<?php echo $folder_id; ?>"
                                    class="<?php echo $i === $page ? 'bg-gray-700 text-white' : 'bg-gray-300 text-gray-600'; ?> px-4 py-2 rounded-lg transition">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&folder_id=<?php echo $folder_id; ?>"
                                class="bg-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-400 transition">&gt;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Unggah Dokumen</h3>
                <button onclick="document.getElementById('uploadModal').classList.add('hidden')"
                    class="text-gray-600 hover:text-gray-800">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Judul</label>
                        <input type="text" name="title" required
                            class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Folder</label>
                        <select name="folder_id"
                            class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500">
                            <option value="">Pilih Folder</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?php echo $folder['id']; ?>">
                                    <?php echo str_repeat('— ', $folder['level']) . htmlspecialchars($folder['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dokumen</label>
                        <input type="file" name="document" required
                            class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500">
                        <p class="mt-1 text-sm text-gray-500">
                            Ukuran maksimal: 5MB. Format yang diizinkan: PDF, DOC, DOCX, XLS, XLSX, TXT, JPG, JPEG, PNG
                        </p>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')"
                            class="px-4 py-2 border rounded-md text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" name="upload_document"
                            class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800">
                            Unggah
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($document_to_edit)): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Edit Dokumen</h2>
            <form method="POST" action="document.php" class="space-y-4">
                <input type="hidden" name="document_id" value="<?php echo htmlspecialchars($document_to_edit['id']); ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Judul</label>
                    <input type="text" 
                           name="title" 
                           value="<?php echo htmlspecialchars($document_to_edit['title']); ?>" 
                           required
                           class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm p-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Folder</label>
                    <select name="folder_id" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm p-2">
                        <option value="">-- Pilih Folder --</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?php echo $folder['id']; ?>"
                                    <?php echo ($folder['id'] == $document_to_edit['folder_id']) ? 'selected' : ''; ?>>
                                <?php echo str_repeat('— ', $folder['level']) . htmlspecialchars($folder['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <a href="document.php" 
                       class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                        Batal
                    </a>
                    <button type="submit" 
                            name="edit_document"
                            class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>         

    <!-- link -->
    <div id="linkModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Tambah Link</h3>
                <button onclick="document.getElementById('linkModal').classList.add('hidden')"
                    class="text-gray-600 hover:text-gray-800">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Judul</label>
                        <input type="text" name="link_title" required
                            class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">URL</label>
                        <input type="url" name="link_url" required
                            class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500"
                            placeholder="https://...">
                        <p class="mt-1 text-sm text-gray-500">
                            Masukkan URL lengkap (contoh: https://drive.google.com/...)
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Folder</label>
                        <select name="folder_id"
                            class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500">
                            <option value="">Pilih Folder</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?php echo $folder['id']; ?>">
                                    <?php echo str_repeat('— ', $folder['level']) . htmlspecialchars($folder['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="document.getElementById('linkModal').classList.add('hidden')"
                            class="px-4 py-2 border rounded-md text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" name="upload_link"
                            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                            Simpan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white shadow-md p-6 w-[calc(100%-16rem)] ml-auto">
        <div class="w-full mx-auto text-center text-gray-600">
            © 2024 Dokumaster. All rights reserved.
        </div>
    </footer>
</body>

</html>