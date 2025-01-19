<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/function/weather.php';

$weather = getWeather();
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if (!empty($currentUser['profile_photo'])) {
    $photoPath = '../uploads/profile_photos/' . $currentUser['profile_photo'];
    if (!file_exists($photoPath)) {
        error_log("Warning: Profile photo file not found: " . $photoPath);
    }
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


$user_id = $_SESSION['user_id'];


$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);



$stmt = $db->query("SELECT COUNT(*) as total_documents FROM documents");
$totalDocuments = $stmt->fetch(PDO::FETCH_ASSOC)['total_documents'];

$stmt = $db->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'user'");
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

$stmt = $db->query("SELECT COUNT(*) as active_users FROM users WHERE is_active = 1 AND role = 'user'");
$activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['active_users'];

$stmt = $db->query("SELECT COUNT(*) as inactive_users FROM users WHERE is_active = 0 AND role = 'user'");
$inactiveUsers = $stmt->fetch(PDO::FETCH_ASSOC)['inactive_users'];

$stmt = $db->query("SELECT d.*, u.username FROM documents d 
                    JOIN users u ON d.uploaded_by = u.id 
                    ORDER BY d.created_at DESC LIMIT 5");
$recentDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM users WHERE role = 'user' 
                    ORDER BY created_at DESC LIMIT 5");
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="../public/js/clock.js"></script>
</head>

<body class="antialiased font-inter bg-[#f5f7fb]">
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
        <!-- Sidebar Modern -->
        <div class="w-64 text-white p-6 bg-gradient-to-br from-[#374151] to-[#1f2937]">
            <div class="flex items-center mb-8">
                <i class="ri-folder-line mr-3 text-4xl"></i>
                <h1 class="text-xl font-bold">Admin Panel</h1>
            </div>

            <nav>
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition">
                            <i class="ri-dashboard-line mr-3"></i>Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition">
                            <i class="ri-user-settings-line mr-3"></i>Manajemen Pengguna
                        </a>
                    </li>
                    <li>
                        <a href="document.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition">
                            <i class="ri-folder-2-line mr-3"></i>Manajemen Folder
                        </a>
                    </li>

                    <!-- Tambahkan separator sebelum menu logout -->
                    <li class="border-t border-gray-700 my-4"></li>

                    <li>
                        <a href="admin-setting.php?return_to=admin/dashboard.php"
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

        <!-- Konten Utama -->
        <div class="flex-1 overflow-y-auto bg-[#f5f7fb] p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
            </div>

            <!-- Kartu Statistik -->
            <div class="grid grid-cols-4 gap-6 mb-8">
                <div
                    class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all hover:translate-y-[-5px] hover:shadow-gray-300/30">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-sm text-gray-500">Total Dokumen</h3>
                            <p class="text-2xl font-bold text-[#374151]"><?php echo $totalDocuments; ?></p>
                        </div>
                        <i class="ri-file-text-line text-3xl text-[#1f2937]"></i>
                    </div>
                </div>

                <div
                    class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all hover:translate-y-[-5px] hover:shadow-gray-300/30">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-sm text-gray-500">Total Pengguna</h3>
                            <p class="text-2xl font-bold text-[#374151]"><?php echo $totalUsers; ?></p>
                        </div>
                        <i class="ri-user-line text-3xl text-[#1f2937]"></i>
                    </div>
                </div>
                <div
                    class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all hover:translate-y-[-5px] hover:shadow-gray-300/30">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-sm text-gray-500">Pengguna Aktif</h3>
                            <p class="text-2xl font-bold text-[#374151]"><?php echo $activeUsers; ?></p>
                        </div>
                        <i class="ri-user-line text-3xl text-[#1f2937]"></i>
                    </div>
                </div>

                <div
                    class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all hover:translate-y-[-5px] hover:shadow-gray-300/30">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-sm text-gray-500">Pengguna Tidak Aktif</h3>
                            <p class="text-2xl font-bold text-[#374151]"><?php echo $inactiveUsers; ?></p>
                        </div>
                        <i class="ri-user-line text-3xl text-[#1f2937]"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8">
                <!-- Recent Documents -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Dokumen Terbaru</h2>
                        <a href="#" class="text-blue-500 hover:underline">Lihat Semua</a>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($recentDocuments as $doc): ?>
                            <div class="flex items-center justify-between border-b pb-2 last:border-b-0">
                                <div class="flex items-center">
                                    <div class="bg-blue-100 p-2 rounded mr-4">
                                        <i class="ri-file-line text-blue-500"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($doc['file_name']) ?></p>
                                        <p class="text-sm text-gray-500">Diunggah oleh
                                            <?= htmlspecialchars($doc['username']) ?>
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="text-sm text-gray-500"><?= date('d M Y', strtotime($doc['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Pengguna Terbaru</h2>
                        <a href="#" class="text-blue-500 hover:underline">Lihat Semua</a>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($recentUsers as $user): ?>
                            <div class="flex items-center justify-between border-b pb-2 last:border-b-0">
                                <div class="flex items-center">
                                    <div class="bg-green-100 p-2 rounded mr-4">
                                        <i class="ri-user-add-line text-green-500"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($user['username']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                </div>
                                <span
                                    class="text-sm text-gray-500"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>