    <?php
    session_start();
    require_once '../includes/database.php';
    require_once '../includes/file_handler.php';
    require_once '../includes/auth.php';
    require_once '../includes/function/weather.php';


    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit;
    }

    $database = new Database();
    $conn = $database->getConnection();

    $user_id = $_SESSION['user_id'];

    $weather = getWeather();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);




    $user_id = $_SESSION['user_id'];
    $show_create_modal = isset($_GET['action']) && $_GET['action'] === 'create';
    $show_edit_modal = isset($_GET['action']) && $_GET['action'] === 'edit';
    $show_delete_modal = isset($_GET['action']) && $_GET['action'] === 'delete';
    $selected_folder = null;
    $viewing_documents = isset($_GET['view_documents']);
    $current_folder_id = isset($_GET['folder_id']) ? (int) $_GET['folder_id'] : null;

    if ($show_edit_modal || $show_delete_modal || $viewing_documents) {
        $folder_id = $_GET['folder_id'] ?? null;
        if ($folder_id) {
            $stmt = $conn->prepare("SELECT * FROM folders WHERE id = ?");
            $stmt->execute([$folder_id]);
            $selected_folder = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }


    $documents = [];
    if ($viewing_documents && $current_folder_id) {
        $doc_stmt = $conn->prepare("
            SELECT 
            d.*,
            u.fullname as username
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.folder_id = ?
            ORDER BY d.created_at DESC
        ");
        $doc_stmt->execute([$current_folder_id]);
        $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
        $folderName = trim($_POST['folder_name']);
        $parentId = $_POST['parent_id'] ?? null;

        if (empty($folderName)) {
            $_SESSION['error'] = "Nama folder tidak boleh kosong.";
        } else {
            $check_sql = "SELECT id FROM folders WHERE name = :name AND (parent_id = :parent_id OR (parent_id IS NULL AND :parent_id IS NULL))";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([
                ':name' => $folderName,
                ':parent_id' => $parentId
            ]);

            if ($check_stmt->rowCount() > 0) {
                $_SESSION['error'] = "Folder dengan nama tersebut sudah ada di level ini.";
            } else {
                $sql = "INSERT INTO folders (name, parent_id, created_by) VALUES (:name, :parent_id, :created_by)";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([
                    ':name' => $folderName,
                    ':parent_id' => empty($parentId) ? null : $parentId,
                    ':created_by' => $user_id
                ]);

                if ($result) {
                    $_SESSION['success'] = "Folder berhasil dibuat.";
                    header("Location: document.php");
                    exit;
                } else {
                    $_SESSION['error'] = "Gagal membuat folder.";
                }
            }
        }
    }

    // if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_folder'])) {
    //     $folder_id = $_POST['folder_id'];

    //     // Fungsi rekursif untuk menghapus folder dan isinya
    //     function deleteFolderRecursively($folder_id, $conn, $user_id) {
    //        
    //         $delete_docs = $conn->prepare("DELETE FROM documents WHERE folder_id = ?");
    //         $delete_docs->execute([$folder_id]);

    //        
    //         $get_subfolders = $conn->prepare("SELECT id FROM folders WHERE parent_id = ?");
    //         $get_subfolders->execute([$folder_id]);
    //         $subfolders = $get_subfolders->fetchAll(PDO::FETCH_COLUMN);

    //         
    //         foreach ($subfolders as $subfolder_id) {
    //             deleteFolderRecursively($subfolder_id, $conn, $user_id);
    //         }

    //         
    //         $delete_folder = $conn->prepare("DELETE FROM folders WHERE id = ? AND created_by = ?");
    //         return $delete_folder->execute([$folder_id, $user_id]);
    //     }

    //     
    //     if (deleteFolderRecursively($folder_id, $conn, $user_id)) {
    //         $_SESSION['success'] = "Folder beserta isinya berhasil dihapus.";
    //     } else {
    //         $_SESSION['error'] = "Gagal menghapus folder.";
    //     }
    //     header("Location: document.php");
    //     exit;
    // }



    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_folder'])) {
        $folder_id = $_POST['folder_id'];

        $check_docs = $conn->prepare("SELECT COUNT(*) FROM documents WHERE folder_id = ?");
        $check_docs->execute([$folder_id]);
        $doc_count = $check_docs->fetchColumn();

        $check_subfolders = $conn->prepare("SELECT COUNT(*) FROM folders WHERE parent_id = ?");
        $check_subfolders->execute([$folder_id]);
        $subfolder_count = $check_subfolders->fetchColumn();

        if ($doc_count > 0 || $subfolder_count > 0) {
            $_SESSION['error'] = "Folder tidak dapat dihapus karena masih berisi dokumen atau subfolder.";
        } else {
            $delete_sql = "DELETE FROM folders WHERE id = ? AND created_by = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            if ($delete_stmt->execute([$folder_id, $user_id])) {
                $_SESSION['success'] = "Folder berhasil dihapus.";
            } else {
                $_SESSION['error'] = "Gagal menghapus folder.";
            }
        }
        header("Location: document.php");
        exit;
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_folder'])) {
        $folder_id = $_POST['folder_id'];
        $new_name = trim($_POST['folder_name']);
        $new_parent = $_POST['parent_id'] ?? null;

        if (empty($new_name)) {
            $_SESSION['error'] = "Nama folder tidak boleh kosong.";
        } else {
            $check_sql = "SELECT id FROM folders WHERE name = :name AND id != :current_id AND 
                        (parent_id = :parent_id OR (parent_id IS NULL AND :parent_id IS NULL))";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([
                ':name' => $new_name,
                ':current_id' => $folder_id,
                ':parent_id' => $new_parent
            ]);

            if ($check_stmt->rowCount() > 0) {
                $_SESSION['error'] = "Folder dengan nama tersebut sudah ada di level ini.";
            } else {
                $update_sql = "UPDATE folders SET name = :name, parent_id = :parent_id 
                            WHERE id = :id AND created_by = :created_by";
                $update_stmt = $conn->prepare($update_sql);
                $result = $update_stmt->execute([
                    ':name' => $new_name,
                    ':parent_id' => empty($new_parent) ? null : $new_parent,
                    ':id' => $folder_id,
                    ':created_by' => $user_id
                ]);

                if ($result) {
                    $_SESSION['success'] = "Folder berhasil diperbarui.";
                    header("Location: document.php");
                    exit;
                } else {
                    $_SESSION['error'] = "Gagal memperbarui folder.";
                }
            }
        }
    }


    function getFolderHierarchy($conn, $parentId = null, $level = 0)
    {
        $sql = "SELECT f.*, 
                (SELECT COUNT(*) FROM documents d WHERE d.folder_id = f.id) as doc_count,
                (SELECT COUNT(*) FROM folders f2 WHERE f2.parent_id = f.id) as subfolder_count
                FROM folders f 
                WHERE f.parent_id " . ($parentId === null ? "IS NULL" : "= :parent_id") . "
                ORDER BY f.name";

        $stmt = $conn->prepare($sql);
        if ($parentId !== null) {
            $stmt->bindParam(':parent_id', $parentId);
        }
        $stmt->execute();

        $folders = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['level'] = $level;
            $folders[] = $row;
            $subfolders = getFolderHierarchy($conn, $row['id'], $level + 1);
            $folders = array_merge($folders, $subfolders);
        }
        return $folders;
    }

    $folders = getFolderHierarchy($conn);


    $stats = $conn->query("SELECT 
        (SELECT COUNT(*) FROM folders) as total_folders,
        (SELECT COUNT(*) FROM documents) as total_documents,
        (SELECT COUNT(DISTINCT uploaded_by) FROM documents) as active_users")->fetch();



    $fileHandler = new FileHandler('../uploads/documents/');


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
        $document_id = $_POST['document_id'];

        
        $stmt = $conn->prepare("SELECT file_name, folder_id FROM documents WHERE id = ? AND (uploaded_by = ? OR EXISTS (SELECT 1 FROM users WHERE id = ? AND role = 'admin'))");
        $stmt->execute([$document_id, $user_id, $user_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($document) {
            
            $conn->beginTransaction();
            try {
                
                $delete_stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
                $delete_stmt->execute([$document_id]);

                
                $fileHandler->deleteFile($document['file_name']);

                $conn->commit();
                $_SESSION['success'] = "Dokumen berhasil dihapus.";
                header("Location: document.php?view_documensts=1&folder_id=" . $document['folder_id']);
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
                $_SESSION['error'] = "Gagal menghapus dokumen.";
                header("Location: document.php?view_documents=1&folder_id=" . $document['folder_id']);
                exit;
            }
        } else {
            $_SESSION['error'] = "Dokumen tidak ditemukan atau Anda tidak memiliki izin.";
            header("Location: document.php");
            exit;
        }
    }


    if (isset($_GET['download'])) {
        $document_id = $_GET['download'];

        $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($document && $fileHandler->fileExists($document['file_name'])) {
            // Update download count
            $update_stmt = $conn->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id = ?");
            $update_stmt->execute([$document_id]);

            
            $fileHandler->streamFile($document['file_name'], $document['title'], true);
            exit;
        }

        $_SESSION['error'] = "File tidak ditemukan.";
        header("Location: ../admin/document.php");
        exit;
    }


    if (isset($_GET['view'])) {
        $document_id = $_GET['view'];

        $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($document && $fileHandler->fileExists($document['file_name'])) {
            // Update view count
            $update_stmt = $conn->prepare("UPDATE documents SET view_count = view_count + 1 WHERE id = ?");
            $update_stmt->execute([$document_id]);

            // Stream file for viewing
            $fileHandler->streamFile($document['file_name'], $document['title'], false);
            exit;
        }

        $_SESSION['error'] = "File tidak ditemukan.";
        header("Location: document.php");
        exit;
    }

    ?>

    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manajemen Folder</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
        <script src="../public/js/clock.js"></script>
    </head>

    <body class="antialiased font-[Inter] bg-[#f5f7fb]">
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
                        <i class="bi bi-clock"></i> <span id="clock">07:29 WIB</span>
                    </span>
                    <span class="text-gray-700 font-medium">
                        <i class="bi bi-calendar"></i> <span id="date">Monday, 26 August 2024</span>
                    </span>
                </div>

                <!-- Profile and Dropdown -->
                <div class="flex items-center space-x-4 ml-auto relative">
                    <h2 class="text-xl font-semibold text-gray-700"><span id="greeting">Selamat Malam</span>,</h2>
                    <button class="focus:outline-none" id="profileToggle">
                        <?php
                        function getValidProfilePhoto($currentUser)
                        {
                            if (empty($currentUser['profile_photo'])) {
                                return '../public/images/pp.jpg';
                            }
                            $photoPath = '../uploads/profile_photos/' . $currentUser['profile_photo'];
                            if (file_exists($photoPath)) {
                                // Selalu tambahkan timestamp untuk memaksa browser memuat ulang gambar
                                return $photoPath . '?v=' . time();
                            }
                            return '../public/images/pp.jpg';
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

        <div class="flex h-screen mt-24">
            <!-- Sidebar -->
            <div class="w-64 bg-gradient-to-br from-gray-700 to-gray-800 text-white p-6">
                <div class="flex items-center mb-8">
                    <i class="ri-folder-line mr-3 text-4xl"></i>
                    <h1 class="text-xl font-bold">Admin Panel</h1>
                </div>
                <nav>
                    <ul class="space-y-2">
                        <li><a href="dashboard.php"
                                class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition">
                                <i class="ri-dashboard-line mr-3"></i>Dashboard</a></li>
                        <li><a href="users.php" class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition">
                                <i class="ri-user-settings-line mr-3"></i>Manajemen User</a></li>
                        <li><a href="document.php" class="flex items-center px-4 py-2 rounded-lg bg-white/20 transition">
                                <i class="ri-folder-2-line mr-3"></i>Manajemen Folder</a></li>

                        <!-- Tambahkan separator sebelum menu logout -->
                        <li class="border-t border-gray-700 my-4"></li>

                        <li>
                            <a href="admin-setting.php?return_to=admin/document.php"
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
            <div class="flex-1 overflow-y-auto bg-[#eef2f5] p-8">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                        <?php
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <?php
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <?php if ($viewing_documents && $selected_folder): ?>
                        <div>
                            <a href="document.php" class="text-gray-600 hover:text-gray-800 mb-2 inline-block">
                                <i class="ri-arrow-left-line mr-2"></i>Kembali ke Daftar Folder
                            </a>
                            <h1 class="text-3xl font-bold text-gray-800">
                                Dokumen dalam folder: <?= htmlspecialchars($selected_folder['name']) ?>
                            </h1>
                        </div>
                    <?php else: ?>
                        <h1 class="text-3xl font-bold text-gray-800">Manajemen Folder</h1>
                        <div class="flex items-center space-x-4">
                            <a href="?action=create"
                                class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 flex items-center">
                                <i class="ri-folder-add-line mr-2"></i> Buat Folder Baru
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$viewing_documents): ?>
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white rounded-xl p-6 shadow-md">
                            <div class="flex items-center">
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <i class="ri-folder-line text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-gray-500 text-sm">Total Folder</h3>
                                    <p class="text-2xl font-bold"><?= $stats['total_folders'] ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl p-6 shadow-md">
                            <div class="flex items-center">
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="ri-file-line text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-gray-500 text-sm">Total Dokumen</h3>
                                    <p class="text-2xl font-bold"><?= $stats['total_documents'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Folders Table -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-2">Nama Folder</th>
                                    <th class="text-left py-3 px-2">Jumlah Dokumen</th>
                                    <th class="text-left py-3 px-2">Subfolder</th>
                                    <th class="text-left py-3 px-2">Dibuat Pada</th>
                                    <th class="text-left py-3 px-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($folders as $folder): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 px-2">
                                            <div class="flex items-center">
                                                <div style="margin-left: <?= $folder['level'] * 20 ?>px">
                                                    <a href="?view_documents=1&folder_id=<?= $folder['id'] ?>"
                                                        class="flex items-center hover:text-blue-600">
                                                        <i class="ri-folder-fill text-yellow-500 mr-2"></i>
                                                        <?= htmlspecialchars($folder['name']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-2"><?= $folder['doc_count'] ?> dokumen</td>
                                        <td class="py-3 px-2"><?= $folder['subfolder_count'] ?> subfolder</td>
                                        <td class="py-3 px-2"><?= date('d M Y', strtotime($folder['created_at'])) ?></td>
                                        <td class="py-3 px-2">
                                            <div class="flex space-x-2">
                                                <a href="?action=edit&folder_id=<?= $folder['id'] ?>"
                                                    class="text-blue-600 hover:text-blue-700">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                                <a href="?action=delete&folder_id=<?= $folder['id'] ?>"
                                                    class="text-red-500 hover:text-red-700">
                                                    <i class="ri-delete-bin-line"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>

                    <!-- Documents Table -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <?php if (empty($documents)): ?>
                            <p class="text-gray-500 text-center py-8">Tidak ada dokumen dalam folder ini.</p>
                        <?php else: ?>
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-3 px-2">Nama Dokumen</th>
                                        <th class="text-left py-3 px-2">Tipe File</th>
                                        <th class="text-left py-3 px-2">Ukuran</th>
                                        <th class="text-left py-3 px-2">Diupload Oleh</th>
                                        <th class="text-left py-3 px-2">Tanggal Upload</th>
                                        <th class="text-left py-3 px-2">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 px-2">
                                                <div class="flex items-center">
                                                    <i class="ri-file-line text-gray-500 mr-2"></i>
                                                    <?= htmlspecialchars($doc['title']) ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-2">
                                                <?= htmlspecialchars($doc['file_type']) ?>
                                            </td>
                                            <td class="py-3 px-2">
                                                <?= number_format($doc['file_size'] / 1024 / 1024, 2) ?> MB
                                            </td>
                                            <td class="py-3 px-2">
                                                <?= htmlspecialchars($doc['username'] ?? 'Unknown') ?>
                                            </td>
                                            <td class="py-3 px-2">
                                                <?= isset($doc['created_at']) ? date('d M Y H:i', strtotime($doc['created_at'])) : 'N/A' ?>
                                            </td>
                                            <td class="py-3 px-2">
                                                <div class="flex space-x-2">
                                                    <?php if (!empty($doc['file_name'])): ?>
                                                        <a href="?download=<?= $doc['id'] ?>" class="text-blue-600 hover:text-blue-700"
                                                            title="Download">
                                                            <i class="ri-download-line"></i>
                                                        </a>
                                                        <a href="?view=<?= $doc['id'] ?>" class="text-green-600 hover:text-green-700"
                                                            title="Lihat">
                                                            <i class="ri-eye-line"></i>
                                                        </a>
                                                        <form method="POST" action="" class="inline"
                                                            onsubmit="return confirm('Yakin ingin menghapus dokumen ini?');">
                                                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                                            <button type="submit" name="delete_document"
                                                                class="text-red-500 hover:text-red-700" title="Hapus">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">File tidak tersedia</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create Folder Modal -->
        <?php if ($show_create_modal): ?>
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                <div class="bg-white rounded-xl p-6 w-full max-w-md">
                    <h2 class="text-xl font-bold mb-4">Buat Folder Baru</h2>
                    <form action="" method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nama Folder</label>
                            <input type="text" name="folder_name" required
                                class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm p-2">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Parent Folder (Opsional)</label>
                            <select name="parent_id" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm p-2">
                                <option value="">-- Root Level --</option>
                                <?php foreach ($folders as $folder): ?>
                                    <option value="<?= $folder['id'] ?>">
                                        <?= str_repeat('— ', $folder['level']) . htmlspecialchars($folder['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <a href="document.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                                Batal
                            </a>
                            <button type="submit" name="create_folder"
                                class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
                                Buat Folder
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Edit Folder Modal -->
        <?php if ($show_edit_modal && $selected_folder): ?>
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                <div class="bg-white rounded-xl p-6 w-full max-w-md">
                    <h2 class="text-xl font-bold mb-4">Edit Folder</h2>
                    <form action="" method="POST" class="space-y-4">
                        <input type="hidden" name="folder_id" value="<?= $selected_folder['id'] ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nama Folder</label>
                            <input type="text" name="folder_name" required
                                value="<?= htmlspecialchars($selected_folder['name']) ?>"
                                class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Parent Folder</label>
                            <select name="parent_id" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm p-2">
                                <option value="">-- Root Level --</option>
                                <?php foreach ($folders as $folder): ?>
                                    <?php if ($folder['id'] !== $selected_folder['id']): ?>
                                        <option value="<?= $folder['id'] ?>" <?= $folder['id'] === $selected_folder['parent_id'] ? 'selected' : '' ?>>
                                            <?= str_repeat('— ', $folder['level']) . htmlspecialchars($folder['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <a href="document.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                                Batal
                            </a>
                            <button type="submit" name="update_folder"
                                class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Delete Confirmation Modal -->
        <?php if ($show_delete_modal && $selected_folder): ?>
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                <div class="bg-white rounded-xl p-6 w-full max-w-md">
                    <h2 class="text-xl font-bold mb-4">Konfirmasi Hapus</h2>
                    <p class="mb-4">Anda yakin ingin menghapus folder
                        <span class="font-semibold"><?= htmlspecialchars($selected_folder['name']) ?></span>?
                    </p>
                    <form action="" method="POST" class="flex justify-end space-x-2">
                        <input type="hidden" name="folder_id" value="<?= $selected_folder['id'] ?>">
                        <a href="document.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                            Batal
                        </a>
                        <button type="submit" name="delete_folder"
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </body>

    </html>