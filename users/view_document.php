<?php
session_start();
require_once('../includes/database.php');


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}


$database = new Database();
$conn = $database->getConnection();


if (!isset($_GET['id'])) {
    header("Location: home.php");
    exit();
}

$documentId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'view'; 


$query = "SELECT d.*, f.name as folder_name, u.username as uploader_name 
          FROM documents d 
          LEFT JOIN folders f ON d.folder_id = f.id 
          LEFT JOIN users u ON d.uploaded_by = u.id 
          WHERE d.id = :id AND (d.uploaded_by = :user_id OR EXISTS (
              SELECT 1 FROM users WHERE id = :user_id AND role = 'admin'
          ))";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $documentId, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$document = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$document) {
    header("Location: home.php");
    exit();
}


$filePath = "../uploads/documents/" . $document['file_name'];


error_log("Accessing file: " . $filePath);
error_log("File exists: " . (file_exists($filePath) ? 'Yes' : 'No'));


if (!file_exists($filePath)) {
    die("File tidak ditemukan. Path: " . $filePath);
}


$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    // 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];

$fileExtension = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));
$mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';


if ($action === 'download') {
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
} else { 
    
    if (in_array($fileExtension, ['pdf', 'jpg', 'jpeg', 'png'])) {
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $document['file_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
    } else {
        
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
    }
}


readfile($filePath);
exit();