<?php
class FileUpload {
    private $uploadDir = "uploads/";
    
    public function upload($file) {
        $fileName = time() . '_' . basename($file['name']);
        $targetPath = $this->uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => true,
                'fileName' => $fileName,
                'fileType' => $file['type'],
                'fileSize' => $file['size']
            ];
        }
        return ['success' => false];
    }
}