<?php
// profile-settings.php

require_once 'includes/database.php';
session_start();

// Cek session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();
$userId = $_SESSION['user_id'];
$message = '';
$error = '';

$return_to = isset($_GET['return_to']) ? $_GET['return_to'] : 'users/home.php';
$allowed_pages = ['users/home.php', 'users/document.php'];
if (!in_array($return_to, $allowed_pages)) {
    $return_to = 'users/home.php';
}

// Fungsi untuk mengambil data user
function getUserData($conn, $userId)
{
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        return false;
    }
}

// Handle Update Profile (Including Photo)
if (isset($_POST['update_profile'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/profile_photos/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $file = $_FILES['profile_photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($file['type'], $allowedTypes)) {
            $error = "Hanya file JPG, PNG, dan GIF yang diperbolehkan!";
        } elseif ($file['size'] > 5000000) { // 5MB
            $error = "Ukuran file terlalu besar! Maksimal 5MB";
        } else {
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = $userId . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                try {
                    // Get old photo name
                    $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $oldPhoto = $stmt->fetch()['profile_photo'];

                    // Delete old photo if exists
                    if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
                        unlink($uploadDir . $oldPhoto);
                    }

                    // Update database with new photo
                    $stmt = $conn->prepare("UPDATE users SET profile_photo = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newFileName, $userId]);

                    // Log aktivitas
                    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_type, activity_description) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, 'update_photo', 'User updated profile photo']);

                    $message = "Foto profil berhasil diperbarui!";
                } catch (PDOException $e) {
                    $error = "Gagal memperbarui foto profil: " . $e->getMessage();
                }
            } else {
                $error = "Gagal mengupload file!";
            }
        }
    }
}

// Handle Update Password
if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    try {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "Semua field password harus diisi!";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Password baru dan konfirmasi password tidak cocok!";
        } elseif (strlen($newPassword) < 8) {
            $error = "Password baru minimal 8 karakter!";
        } else {
            if ($user && $currentPassword === $user['password']) {
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newPassword, $userId]);

                $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_type, activity_description) VALUES (?, ?, ?)");
                $stmt->execute([$userId, 'update_password', 'User changed password']);

                $message = "Password berhasil diperbarui!";
            } else {
                $error = "Password saat ini tidak valid!";
            }
        }
    } catch (PDOException $e) {
        error_log("Error updating password: " . $e->getMessage());
        $error = "Terjadi kesalahan saat memperbarui password. Silakan coba lagi.";
    }
}

// Ambil data user untuk ditampilkan
$userData = getUserData($conn, $userId);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Profil</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-md">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="flex items-center mb-6">
            <a href="<?php echo htmlspecialchars($return_to); ?>"
                class="mr-4 text-gray-600 hover:text-gray-800 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h2 class="text-2xl font-bold text-gray-800 text-center flex-grow">Pengaturan Profil</h2>
        </div>

        <!-- Form Upload Foto -->
        <form action="" method="POST" enctype="multipart/form-data" class="mb-8">
            <div class="mb-6 flex flex-col items-center">
                <div class="relative mb-4">
                    <img src="<?php echo $userData['profile_photo'] ? 'uploads/profile_photos/' . htmlspecialchars($userData['profile_photo']) : 'public/images/pp.jpg'; ?>"
                        alt="Foto Profil" class="w-32 h-32 rounded-full object-cover border-4 border-gray-300">
                    <label for="profile-upload"
                        class="absolute bottom-0 right-0 bg-gray-800 text-white p-2 rounded-full cursor-pointer hover:bg-gray-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0011.586 3H8.414a1 1 0 00-.707.293L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z"
                                clip-rule="evenodd" />
                        </svg>
                        <input type="file" id="profile-upload" name="profile_photo" class="hidden" accept="image/*">
                    </label>
                </div>
                <p class="text-gray-600 text-sm">Klik ikon kamera untuk memilih foto profil</p>
            </div>
            <button type="submit" name="update_profile"
                class="w-full bg-gray-800 text-white py-2 rounded-md hover:bg-gray-700 transition duration-300 mb-8">
                Simpan Foto Profil
            </button>
        </form>

        <!-- Form Ganti Password -->
        <form action="" method="POST" class="space-y-4">
            <div>
                <label for="current-password" class="block text-gray-700 mb-2">Kata Sandi Saat Ini</label>
                <div class="relative">
                    <input type="password" id="current-password" name="current_password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-800"
                        placeholder="Masukkan kata sandi saat ini">
                </div>
            </div>

            <div>
                <label for="new-password" class="block text-gray-700 mb-2">Kata Sandi Baru</label>
                <div class="relative">
                    <input type="password" id="new-password" name="new_password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-800"
                        placeholder="Masukkan kata sandi baru">
                </div>
            </div>

            <div>
                <label for="confirm-password" class="block text-gray-700 mb-2">Konfirmasi Kata Sandi Baru</label>
                <div class="relative">
                    <input type="password" id="confirm-password" name="confirm_password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-800"
                        placeholder="Konfirmasi kata sandi baru">
                </div>
            </div>

            <button type="submit" name="update_password"
                class="w-full bg-gray-800 text-white py-2 rounded-md hover:bg-gray-700 transition duration-300">
                Perbarui Kata Sandi
            </button>
        </form>
    </div>
</body>

</html>