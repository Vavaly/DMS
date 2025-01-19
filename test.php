
<?php
// Mencakup file database
include 'includes/database.php';

// Buat instance dari kelas Database
$database = new Database();
$db = $database->getConnection();

// Cek apakah koneksi berhasil
if ($db) {
    echo "Koneksi ke database berhasil!";
} else {
    echo "Koneksi ke database gagal.";
}

?>