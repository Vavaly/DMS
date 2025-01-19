<?php
session_start();
require_once('../includes/database.php');
require_once '../includes/function/weather.php';



if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$weather = getWeather();
$database = new Database();
$conn = $database->getConnection();

$userId = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$queryUser = "SELECT * FROM users WHERE id = :user_id";
$stmtUser = $conn->prepare($queryUser);
$stmtUser->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmtUser->execute();
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!empty($currentUser['profile_photo'])) {
    $photoPath = '../uploads/profile_photos/' . $currentUser['profile_photo'];
    if (!file_exists($photoPath)) {
        error_log("Warning: Profile photo file not found: " . $photoPath);
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';

$countQuery = "SELECT COUNT(*) as total FROM documents WHERE uploaded_by = :uploaded_by";
if (!empty($search)) {
    $countQuery .= " AND (title LIKE :search OR file_name LIKE :search)";
}
$countStmt = $conn->prepare($countQuery);
$countStmt->bindParam(':uploaded_by', $userId, PDO::PARAM_INT);
if (!empty($search)) {
    $searchTerm = "%$search%";
    $countStmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
}
$countStmt->execute();
$totalDocuments = $countStmt->fetchColumn();

$query = "SELECT d.*, f.name as folder_name, 
          DATE_FORMAT(d.created_at, '%d %M %Y') as formatted_date,
          CONCAT(ROUND(d.file_size/1024/1024, 2), ' MB') as formatted_size 
          FROM documents d 
          LEFT JOIN folders f ON d.folder_id = f.id 
          WHERE d.uploaded_by = :uploaded_by";

if (!empty($search)) {
    $query .= " AND (d.title LIKE :search OR d.file_name LIKE :search)";
}

switch ($sort) {
    case 'nama':
        $query .= " ORDER BY d.title ASC";
        break;
    case 'kategori':
        $query .= " ORDER BY f.name ASC, d.created_at DESC";
        break;
    case 'ukuran':
        $query .= " ORDER BY d.file_size DESC";
        break;
    case 'terbaru':
    default:
        $query .= " ORDER BY d.created_at DESC";
        break;
}

$query .= " LIMIT :offset, :perpage";

$stmt = $conn->prepare($query);
$stmt->bindParam(':uploaded_by', $userId, PDO::PARAM_INT);
if (!empty($search)) {
    $searchTerm = "%$search%";
    $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
}
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':perpage', $perPage, PDO::PARAM_INT);
$stmt->execute();

$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = ceil($totalDocuments / $perPage);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="../public/js/clock.js"></script>
</head>

<body class="min-h-screen flex flex-col bg-gray-800 font-sans antialiased">
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

    <!-- Main Content Wrapper -->
    <div class="flex flex-grow mt-24">
        <!-- Sidebar -->
        <aside class="w-64 bg-gradient-to-br bg-gray-800 text-white min-h-screen p-6">
            <div class="flex items-center mb-6">
                <i class="ri-folder-4-line text-3xl mr-3"></i>
                <h2 class="text-2xl font-bold">Menu Navigasi</h2>
            </div>
            <nav>
                <ul class="space-y-2">
                    <li>
                        <a href="home.php" class="flex items-center px-4 py-2 rounded-lg bg-white/20 transition link">
                            <i class="ri-home-line mr-3"></i>Home
                        </a>
                    </li>
                    <li>
                        <a href="document.php"
                            class="flex items-center space-x-3 py-3 px-4 rounded-lg hover:bg-white/30 transition duration-200 group">
                            <i class="ri-folder-2-line text-lg"></i>
                            <span class="text-sm lg:text-base">Dokumen</span>
                        </a>
                    </li>
                    <!-- Tambahkan separator sebelum menu logout -->
                    <li class="border-t border-gray-700 my-4"></li>

                    <!-- Menu Logout -->
                    <li>
                        <a href="../pengaturan.php?return_to=home.php"
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
        </aside>

        <!-- Main Content Area -->
        <main class="flex-grow p-8 bg-gray-50">
            <div class="max-w-full">
                <!-- Statistics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div
                        class="bg-white p-6 rounded-xl shadow-md hover:translate-y-[-5px] transition-all border-l-4 border-gray-700">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Total Dokumen</h3>
                                <p class="text-3xl font-bold text-gray-700"><?php echo $totalDocuments; ?></p>
                            </div>
                            <i class="ri-file-text-line text-4xl text-gray-300"></i>
                        </div>
                    </div>
                </div>


                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div
                        class="mb-4 p-4 rounded-lg transition-all duration-500 <?php echo $_SESSION['flash_message']['type'] === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-500' : 'bg-red-100 text-red-700 border-l-4 border-red-500'; ?>">
                        <div class="flex items-center">
                            <i
                                class="<?php echo $_SESSION['flash_message']['type'] === 'success' ? 'ri-checkbox-circle-line' : 'ri-error-warning-line'; ?> text-xl mr-2"></i>
                            <?php
                            echo htmlspecialchars($_SESSION['flash_message']['message']);
                            unset($_SESSION['flash_message']);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Document search  -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-700">Daftar Dokumen</h2>
                        <form method="GET" class="flex items-center space-x-4">
                            <div class="relative">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Cari dokumen..."
                                    class="w-60 pl-10 pr-3 py-2 border rounded-lg shadow-sm focus:ring focus:ring-gray-200 focus:border-gray-300 transition">
                                <i
                                    class="ri-search-line absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>

                            <select name="sort"
                                class="px-3 py-2 border rounded-lg shadow-sm focus:ring focus:ring-gray-200 focus:border-gray-300">
                                <option value="terbaru" <?php echo $sort === 'terbaru' ? 'selected' : ''; ?>>Terbaru
                                </option>
                                <option value="nama" <?php echo $sort === 'nama' ? 'selected' : ''; ?>>Nama</option>
                                <option value="kategori" <?php echo $sort === 'kategori' ? 'selected' : ''; ?>>Kategori
                                </option>
                                <option value="ukuran" <?php echo $sort === 'ukuran' ? 'selected' : ''; ?>>Ukuran</option>
                            </select>

                            <button type="submit"
                                class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition flex items-center">
                                <i class="ri-filter-3-line mr-2"></i>
                                Filter
                            </button>

                            <?php if (!empty($search)): ?>
                                <a href="?sort=<?php echo urlencode($sort); ?>"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center">
                                    <i class="ri-refresh-line mr-2"></i>
                                    Reset
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Document Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="py-3 px-4 font-semibold text-gray-600 border-b">No</th>
                                    <th class="py-3 px-4 font-semibold text-gray-600 border-b">Nama Dokumen</th>
                                    <th class="py-3 px-4 font-semibold text-gray-600 border-b">Kategori</th>
                                    <th class="py-3 px-4 font-semibold text-gray-600 border-b">Tanggal</th>
                                    <th class="py-3 px-4 font-semibold text-gray-600 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($documents): ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                                            <td class="py-3 px-4 border-b"><?php echo $offset + $index + 1; ?></td>
                                            <td class="py-3 px-4 border-b font-medium">
                                                <?php echo htmlspecialchars($doc['title']); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <?php if ($doc['folder_name']): ?>
                                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
                                                        <?php echo htmlspecialchars($doc['folder_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 border-b text-gray-600">
                                                <?php echo htmlspecialchars($doc['formatted_date']); ?>
                                            </td>
                                            <td class="py-3 px-4 border-b">
                                                <div class="flex items-center space-x-3">
                                                    <a href="view_document.php?id=<?php echo $doc['id']; ?>&action=view"
                                                        class="text-green-600 hover:text-green-900 transition-colors duration-200"
                                                        title="Lihat Dokumen">
                                                        <i class="ri-eye-line"></i>
                                                    </a>
                                                    <a href="view_document.php?id=<?php echo $doc['id']; ?>&action=download"
                                                        class="text-black-600 hover:text-black-900 transition-colors duration-200"
                                                        title="Unduh Dokumen">
                                                        <i class="ri-download-line"></i>
                                                    </a>
                                                    <button
                                                        onclick="confirmDelete(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['title'])); ?>')"
                                                        class="text-red-500 hover:text-red-700 transition-colors duration-200"
                                                        title="Hapus Dokumen">
                                                        <i class="ri-delete-bin-6-line"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-gray-500">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="ri-inbox-line text-4xl mb-2"></i>
                                                <p>Tidak ada dokumen yang ditemukan.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>


                    <!-- Pagination -->
                    <div class="flex justify-between items-center mt-6">
                        <span class="text-sm text-gray-600">
                            Menampilkan <?php echo min($offset + 1, $totalDocuments); ?> hingga
                            <?php echo min($offset + $perPage, $totalDocuments); ?> dari
                            <?php echo $totalDocuments; ?> dokumen
                        </span>

                        <div class="space-x-1">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>"
                                    class="inline-flex items-center justify-center px-3 py-1 rounded-lg text-sm 
                          <?php echo $page === $i
                              ? 'bg-gray-700 text-white'
                              : 'text-gray-700 hover:bg-gray-100'; ?> 
                          transition-colors duration-200">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 py-6 text-white text-center">
        <p>&copy; 2024 Semua hak cipta dilindungi.</p>
    </footer>

    <script>
        function confirmDelete(documentId, documentTitle) {
            if (confirm(`Apakah Anda yakin ingin menghapus dokumen "${documentTitle}"?`)) {
                window.location.href = `delete_document.php?id=${documentId}`;
            }
        }
    </script>
</body>

</html>