<?php
// process_user.php
session_start();
require_once '../includes/database.php';
require_once '../includes/auth.php';


$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);


$user_id = $_SESSION['user_id'];

// Get user name from database
$stmt = $db->prepare("SELECT fullname FROM users WHERE id = :user_id");
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user exists, get their fullname
$userName = $user ? $user['fullname'] : 'Guest';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password']; // Password tidak di-hash sesuai permintaan
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $fullname = $username; // Bisa dimodifikasi sesuai kebutuhan

    try {
        // Persiapkan query
        $query = "INSERT INTO users (username, password, fullname, email, role) 
                  VALUES (:username, :password, :fullname, :email, :role)";
        $stmt = $pdo->prepare($query);

        // Bind parameter
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password); // Simpan password secara plain-text
        $stmt->bindParam(':fullname', $fullname);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);

        // Eksekusi query
        if ($stmt->execute()) {
            // Log aktivitas
            $userId = $pdo->lastInsertId();
            $activityQuery = "INSERT INTO activity_logs (user_id, activity_type, description) 
                              VALUES (:user_id, 'create_user', 'Created new user: ' || :username)";
            $activityStmt = $pdo->prepare($activityQuery);
            $activityStmt->execute([
                ':user_id' => $_SESSION['user_id'], // ID admin yang membuat user
                ':username' => $username
            ]);

            $_SESSION['success'] = "Pengguna baru berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan pengguna baru.";
        }

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            $_SESSION['error'] = "Username atau email sudah digunakan!";
        } else {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }

    // Redirect kembali ke halaman users
    header("Location: users.php");
    exit();
}
?>




<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Dashboard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fb;
        }

        .sidebar {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
        }
    </style>
</head>

<body class="antialiased">
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
                    <span class="text-gray-700 font-medium">30°C</span>
                </div>
                <span class="text-gray-700 font-medium">
                    <i class="bi bi-clock"></i> 07:29 WIB
                </span>
                <span class="text-gray-700 font-medium">
                    <i class="bi bi-calendar"></i> Monday, 26 August 2024
                </span>
            </div>

            <!-- Profile and Dropdown -->
            <div class="flex items-center space-x-4 ml-auto relative">
                <h2 class="text-xl font-semibold text-gray-700">Selamat pagi,</h2>
                <button class="focus:outline-none relative" id="profileToggle">
                    <img src="../public/images/pp.jpg" alt="Profil"
                        class="h-10 w-10 rounded-full cursor-pointer ring-2 ring-gray-700">
                </button>
                <span class="text-gray-700 font-bold"><?php echo htmlspecialchars($userName); ?></span>

                <div id="dropdownMenu"
                    class="absolute top-full right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 transform scale-y-0 opacity-0 origin-top transition-all duration-300 ease-in-out">
                    <ul class="py-1">
                        <li class="px-4 py-2 hover:bg-gray-100 cursor-pointer hover:text-gray-700 transition">
                            <a href="#" class="block">Profil</a>
                        </li>
                        <li class="px-4 py-2 hover:bg-gray-100 cursor-pointer hover:text-gray-700 transition">
                            <a href="setting.php" class="block">Pengaturan</a>
                        </li>
                        <li class="px-4 py-2 text-red-500 hover:bg-red-50 cursor-pointer hover:text-red-700 transition">
                            <a href="../logout.php" class="block">Keluar</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <div class="flex h-screen mt-24">

        <!-- Bilah Sisi Modern -->
        <div class="w-64 sidebar text-white p-6">
            <div class="flex items-center mb-8">
                <i class="ri-folder-line mr-3 text-4xl"></i>
                <h1 class="text-xl font-bold">Admin Dokumen</h1>
            </div>
            <nav>
                <ul class="space-y-2">
                    <li><a href="dashboard.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition link"><i
                                class="ri-dashboard-line mr-3"></i>Dashboard</a></li>
                    <li><a href="users.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition link"><i
                                class="ri-user-settings-line mr-3"></i>Manajemen Pengguna</a></li>
                    <li><a href="document.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition link"><i
                                class="ri-folder-2-line mr-3"></i>Dokumen</a></li>
                    <li><a href="#" class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition link"><i
                                class="ri-settings-3-line mr-3"></i>Pengaturan</a></li>
                </ul>
            </nav>
        </div>

        <!-- Konten Utama -->
        <div class="flex-1 overflow-y-auto main-content p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Manajemen Pengguna</h1>
                <div class="flex items-center space-x-4">
                </div>
            </div>

            <!-- Tombol Tambah Pengguna -->
            <div class="mb-6">
                <button id="openModal"
                    class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition flex items-center">
                    <i class="ri-user-add-line mr-2"></i>Tambah Pengguna Baru
                </button>
            </div>

            <!-- Modal Pop-up -->
            <div id="userModal"
                class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 transition-all duration-300 ease-in-out">
                <div
                    class="bg-white w-96 rounded-2xl shadow-2xl transform transition-all duration-300 ease-in-out scale-95 opacity-0">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">Tambah Pengguna Baru</h2>
                            <button id="closeModal" class="text-gray-500 hover:text-gray-700 transition">
                                <i class="ri-close-line text-2xl"></i>
                            </button>
                        </div>

                        <form method="POST" action="your_php_script.php">
                            <div class="space-y-4">
                                <div>
                                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Peran</label>
                                    <select id="role" name="role"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                                        <option value="admin">Admin</option>
                                        <option value="user">User</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Nama
                                        Pengguna</label>
                                    <input type="text" id="username" name="username"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                        placeholder="Masukkan nama pengguna" required>
                                </div>

                                <div>
                                    <label for="email"
                                        class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" id="email" name="email"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                        placeholder="Masukkan email pengguna" required>
                                </div>

                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Kata
                                        Sandi</label>
                                    <input type="password" id="password" name="password"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                                        placeholder="Masukkan kata sandi" required>
                                </div>
                                <div class="flex space-x-4 mt-6">
                                    <button type="submit"
                                        class="flex-1 bg-gray-800 text-white py-2 rounded-lg hover:bg-blue-700 transition transform active:scale-95">
                                        Tambah
                                    </button>
                                    <button type="button" id="cancelModal"
                                        class="flex-1 bg-gray-200 text-gray-800 py-2 rounded-lg hover:bg-gray-300 transition transform active:scale-95">
                                        Batal
                                    </button>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

            <!-- Tabel Manajemen Pengguna -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-2">ID</th>
                            <th class="text-left py-3 px-2">Nama Pengguna</th>
                            <th class="text-left py-3 px-2">Email</th>
                            <th class="text-left py-3 px-2">Peran</th>
                            <th class="text-left py-3 px-2">Tanggal Dibuat</th>
                            <th class="text-left py-3 px-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="py-3 px-2">1</td>
                            <td class="py-3 px-2">Nama Pengguna1</td>
                            <td class="py-3 px-2">user1@example.com</td>
                            <td class="py-3 px-2">
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">admin</span>
                            </td>
                            <td class="py-3 px-2">01 Januari 2024</td>
                            <td class="py-3 px-2">
                                <div class="flex space-x-2">
                                    <button class="text-gray-700 hover:text-gray-900">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="text-red-500 hover:text-red-700">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="py-3 px-2">2</td>
                            <td class="py-3 px-2">Nama Pengguna2</td>
                            <td class="py-3 px-2">user2@example.com</td>
                            <td class="py-3 px-2">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">user</span>
                            </td>
                            <td class="py-3 px-2">02 Januari 2024</td>
                            <td class="py-3 px-2">
                                <div class="flex space-x-2">
                                    <button class="text-gray-700 hover:text-gray-900">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="text-red-500 hover:text-red-700">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Paginasi -->
                <div class="flex justify-between items-center mt-4">
                    <div class="text-sm text-gray-700">Menampilkan 1 hingga 10 dari 20 entri</div>
                    <div class="flex space-x-2">
                        <div class="flex items-center justify-center space-x-4">
                            <button class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                                </svg>
                                Prev
                            </button>
                            <span class="text-gray-700 font-medium">1</span>
                            <button class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                                Next
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
<script src="../public/js/main.js"></script>

</html>