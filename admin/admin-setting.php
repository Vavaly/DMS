<?php
require_once '../includes/database.php';
session_start();


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Tambahkan pengecekan role admin
$database = new Database();
$conn = $database->getConnection();
$userId = $_SESSION['user_id'];

// Cek apakah user adalah admin
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    header('Location: ../login.php');
    exit;
}

// Tambahkan konstanta untuk path
define('UPLOAD_PATH', '../uploads/profile_photos/');
define('DEFAULT_PHOTO', '../public/images/pp.jpg');

$message = '';
$error = '';

$return_to = isset($_GET['return_to']) ? $_GET['return_to'] : 'dashboard.php';
$allowed_pages = ['users.php', 'document.php'];
if (!in_array($return_to, $allowed_pages)) {
    $return_to = 'dashboard.php';
}

// Fungsi untuk mengambil data admin
function getUserData($conn, $userId)
{
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching admin data: " . $e->getMessage());
        return false;
    }
}

function getValidProfilePhoto($userData)
{
    if (empty($userData['profile_photo'])) {
        return DEFAULT_PHOTO;
    }

    $profilePhoto = basename($userData['profile_photo']);
    $photoPath = UPLOAD_PATH . $profilePhoto;

    // Tambahkan pengecekan cache-busting
    return file_exists($photoPath) ? $photoPath . '?v=' . time() : DEFAULT_PHOTO;
}

// Handle Upload Foto
if (isset($_POST['update_profile'])) {
    if (isset($_FILES['profile_photo'])) {
        try {
            if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload error: " . $_FILES['profile_photo']['error']);
            }

            $uploadDir = UPLOAD_PATH;

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("Gagal membuat direktori upload");
                }
            }

            if (!is_writable($uploadDir)) {
                throw new Exception("Direktori upload tidak dapat ditulis");
            }

            $file = $_FILES['profile_photo'];

            // Validasi mime type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception("Hanya file JPG, PNG, dan GIF yang diperbolehkan!");
            }

            if ($file['size'] > 5000000) {
                throw new Exception("Ukuran file terlalu besar! Maksimal 5MB");
            }

            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = 'admin_' . $userId . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $newFileName;

            // Get old photo name
            $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $oldPhoto = $stmt->fetch()['profile_photo'];

            // Delete old photo if exists
            if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
                unlink($uploadDir . $oldPhoto);
            }

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                chmod($targetPath, 0644);

                // Update database with new photo
                $stmt = $conn->prepare("UPDATE users SET profile_photo = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newFileName, $userId]);

                // Refresh user data setelah update
                $userData = getUserData($conn, $userId);

                $message = "Foto profil berhasil diperbarui!";
            } else {
                throw new Exception("Gagal mengupload file!");
            }

        } catch (Exception $e) {
            error_log("Photo upload error: " . $e->getMessage());
            $error = "Gagal mengupload foto: " . $e->getMessage();
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
// Ambil data admin untuk ditampilkan
$userData = getUserData($conn, $userId);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Profil Admin</title>
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
            <h2 class="text-2xl font-bold text-gray-800 text-center flex-grow">Pengaturan Profil Admin</h2>
        </div>

        <!-- Form Upload Foto -->
        <form action="" method="POST" enctype="multipart/form-data" class="mb-8">
            <div class="mb-6 flex flex-col items-center">
                <div class="relative mb-4">
                    <img src="<?php echo getValidProfilePhoto($userData) . '?v=' . time(); ?>" alt="Foto Profil Admin"
                        class="w-32 h-32 rounded-full object-cover border-4 border-gray-300" id="preview-image">
                    <label for="profile-upload"
                        class="absolute bottom-0 right-0 bg-gray-800 text-white p-2 rounded-full cursor-pointer hover:bg-gray-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0011.586 3H8.414a1 1 0 00-.707.293L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z"
                                clip-rule="evenodd" />
                        </svg>
                        <input type="file" id="profile-upload" name="profile_photo" class="hidden" accept="image/*"
                            onchange="previewImage(this);">
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
<script>
        document.getElementById('profile-upload').onchange = function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.querySelector('img').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</html>