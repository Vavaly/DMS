<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/function/weather.php';

$weather = getWeather();
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$user_id = $_SESSION['user_id'];
$modal_display = 'none';
$edit_modal_display = 'none';


$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Function to fetch all users
function getAllUsers($db)
{
    $query = "SELECT id, username, email, role, created_at, is_active FROM users ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// Show modal based on GET parameters
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'add') {
        $modal_display = 'flex';
    } elseif ($_GET['action'] == 'edit') {
        $edit_modal_display = 'flex';
        $edit_user_id = $_GET['user_id'];
        // Fetch user data for editing
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindParam(":user_id", $edit_user_id);
        $stmt->execute();
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $password = $_POST['password'];
                $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
                $fullname = $username;

                try {
                    $query = "INSERT INTO users (username, password, fullname, email, role) 
                             VALUES (:username, :password, :fullname, :email, :role)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $password);
                    $stmt->bindParam(':fullname', $fullname);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':role', $role);

                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Pengguna baru berhasil ditambahkan!";
                    } else {
                        $_SESSION['error'] = "Gagal menambahkan pengguna baru.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                }
                break;

            case 'edit':
                $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

                try {
                    $query = "UPDATE users SET 
                             username = :username,
                             email = :email,
                             role = :role
                             WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':role', $role);

                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Data pengguna berhasil diperbarui!";
                    } else {
                        $_SESSION['error'] = "Gagal memperbarui data pengguna.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                }
                break;

            case 'delete':
                $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

                try {
                    // Hapus user secara permanent dari database
                    $query = "DELETE FROM users WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id);

                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Pengguna berhasil dihapus secara permanent.";
                    } else {
                        $_SESSION['error'] = "Gagal menghapus pengguna.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                }
                break;

            case 'toggle_status':
                $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_NUMBER_INT);

                try {
                    $query = "UPDATE users SET is_active = :new_status WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':new_status', $new_status);

                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Status pengguna berhasil diperbarui.";
                    } else {
                        $_SESSION['error'] = "Gagal memperbarui status pengguna.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                }
                break;
        }
        header("Location: users.php");
        exit();
    }
}

$users = getAllUsers($db);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="../public/js/clock.js"></script>
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
        <!-- Sidebar remains the same -->
        <div class="w-64 sidebar text-white p-6">
            <!-- Sidebar content remains the same -->
            <div class="flex items-center mb-8">
                <i class="ri-folder-line mr-3 text-4xl"></i>
                <h1 class="text-xl font-bold">Admin Panel</h1>
            </div>
            <nav>
                <ul class="space-y-2">
                    <li><a href="dashboard.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition"><i
                                class="ri-dashboard-line mr-3"></i>Dashboard</a></li>
                    <li><a href="users.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition"><i
                                class="ri-user-settings-line mr-3"></i>Manajemen Pengguna</a></li>
                    <li><a href="document.php"
                            class="flex items-center px-4 py-2 rounded-lg hover:bg-white/20 transition"><i
                                class="ri-folder-2-line mr-3"></i>Manajemen Folder</a></li>

                    <!-- Tambahkan separator sebelum menu logout -->
                    <li class="border-t border-gray-700 my-4"></li>

                    <li>
                        <a href="admin-setting.php?return_to=admin/users.php"
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
            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <?php echo $_SESSION['success']; ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <?php echo $_SESSION['error']; ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Manajemen Pengguna</h1>
            </div>

            <!-- Add User Button -->
            <div class="mb-6">
                <a href="?action=add"
                    class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition inline-flex items-center">
                    <i class="ri-user-add-line mr-2"></i>Tambah Pengguna Baru
                </a>
            </div>

            <!-- Add User Modal -->
            <div style="display: <?php echo $modal_display; ?>"
                class="fixed inset-0 bg-black/50 items-center justify-center z-50">
                <div class="bg-white w-96 rounded-2xl shadow-2xl">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">Tambah Pengguna Baru</h2>
                            <a href="users.php" class="text-gray-500 hover:text-gray-700">
                                <i class="ri-close-line text-2xl"></i>
                            </a>
                        </div>

                        <form method="POST" action="">
                            <!-- Add user form content remains the same -->
                            <input type="hidden" name="action" value="add">
                            <div class="space-y-4">
                                <div>
                                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Peran</label>
                                    <select id="role" name="role"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <option value="admin">Admin</option>
                                        <option value="user">User</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Nama
                                        Pengguna</label>
                                    <input type="text" id="username" name="username"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                </div>

                                <div>
                                    <label for="email"
                                        class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" id="email" name="email"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                </div>

                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Kata
                                        Sandi</label>
                                    <input type="password" id="password" name="password"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                </div>

                                <div class="flex space-x-4 mt-6">
                                    <button type="submit"
                                        class="flex-1 bg-gray-800 text-white py-2 rounded-lg hover:bg-gray-700 transition">
                                        Tambah
                                    </button>
                                    <a href="users.php"
                                        class="flex-1 bg-gray-200 text-gray-800 py-2 rounded-lg hover:bg-gray-300 transition text-center">
                                        Batal
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit User Modal -->
            <div style="display: <?php echo $edit_modal_display; ?>"
                class="fixed inset-0 bg-black/50 items-center justify-center z-50">
                <div class="bg-white w-96 rounded-2xl shadow-2xl">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800">Edit Pengguna</h2>
                            <a href="users.php" class="text-gray-500 hover:text-gray-700">
                                <i class="ri-close-line text-2xl"></i>
                            </a>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="user_id"
                                value="<?php echo isset($edit_user) ? $edit_user['id'] : ''; ?>">
                            <div class="space-y-4">
                                <div>
                                    <label for="edit_username" class="block text-sm font-medium text-gray-700 mb-2">Nama
                                        Pengguna</label>
                                    <input type="text" id="edit_username" name="username"
                                        value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['username']) : ''; ?>"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                </div>

                                <div>
                                    <label for="edit_email"
                                        class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" id="edit_email" name="email"
                                        value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['email']) : ''; ?>"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                </div>

                                <div>
                                    <label for="edit_role"
                                        class="block text-sm font-medium text-gray-700 mb-2">Peran</label>
                                    <select id="edit_role" name="role"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <option value="admin" <?php echo (isset($edit_user) && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                        <option value="user" <?php echo (isset($edit_user) && $edit_user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                    </select>
                                </div>

                                <div class="flex space-x-4 mt-6">
                                    <button type="submit"
                                        class="flex-1 bg-gray-800 text-white py-2 rounded-lg hover:bg-gray-700 transition">
                                        Simpan
                                    </button>
                                    <a href="users.php"
                                        class="flex-1 bg-gray-200 text-gray-800 py-2 rounded-lg hover:bg-gray-300 transition text-center">
                                        Batal
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
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
                        <?php foreach ($users as $user): ?>
                            <tr
                                class="border-b hover:bg-gray-50 transition <?php echo $user['is_active'] ? '' : 'bg-gray-100'; ?>">
                                <td class="py-3 px-2"><?php echo htmlspecialchars($user['id']); ?></td>
                                <td class="py-3 px-2">
                                    <a href="user-documents.php?user_id=<?php echo $user['id']; ?>"
                                        class="text-gray-600 hover:text-gray-800 hover:underline">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </a>
                                </td>
                                <td class="py-3 px-2"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="py-3 px-2">
                                    <span
                                        class="<?php echo $user['role'] === 'admin' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> px-2 py-1 rounded-full text-xs">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-2"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td class="py-3 px-2">
                                    <span
                                        class="<?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> px-2 py-1 rounded-full text-xs">
                                        <?php echo $user['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </td>
                                <td class="py-3 px-2">
                                    <div class="flex space-x-2">
                                        <a href="?action=edit&user_id=<?php echo $user['id']; ?>"
                                            class="text-gray-700 hover:text-gray-900">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form method="POST" action="" class="inline"
                                            onsubmit="return confirm('PERHATIAN: Pengguna akan dihapus secara permanent dan tidak dapat dikembalikan. Apakah Anda yakin ingin melanjutkan?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="" class="inline"
                                            onsubmit="return confirm('Apakah Anda yakin ingin <?php echo $user['is_active'] ? 'menonaktifkan' : 'mengaktifkan'; ?> pengguna ini?');">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="new_status"
                                                value="<?php echo $user['is_active'] ? '0' : '1'; ?>">
                                            <button type="submit"
                                                class="<?php echo $user['is_active'] ? 'text-yellow-500 hover:text-yellow-700' : 'text-green-500 hover:text-green-700'; ?>">
                                                <i
                                                    class="<?php echo $user['is_active'] ? 'ri-toggle-fill' : 'ri-toggle-line'; ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

</html>