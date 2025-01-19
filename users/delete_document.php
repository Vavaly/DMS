<?php
session_start();
require_once('../includes/database.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'ID dokumen tidak ditemukan'
    ];
    header("Location: home.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Dapatkan informasi dokumen
    $query = "SELECT * FROM documents WHERE id = :id AND uploaded_by = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Dokumen tidak ditemukan atau Anda tidak memiliki akses'
        ];
        header("Location: home.php");
        exit();
    }

    // Hapus file fisik jika ada
    if (!empty($document['file_path'])) {
        $filePath = '../uploads/documents/' . $document['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Hapus record dari database
    $deleteQuery = "DELETE FROM documents WHERE id = :id AND uploaded_by = :user_id";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
    $deleteStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $deleteStmt->execute();

    $_SESSION['flash_message'] = [
        'type' => 'success',
        'message' => 'Dokumen berhasil dihapus'
    ];

} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Terjadi kesalahan saat menghapus dokumen'
    ];
}

header("Location: home.php");
exit();