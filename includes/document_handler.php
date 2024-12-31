<?php
// includes/document_handler.php
class DocumentHandler {
    private $db;
    private $uploadDir = "uploads/";
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function uploadDocument($file, $title, $folderId, $userId) {
        try {
            if (!is_dir($this->uploadDir)) {
                mkdir($this->uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . basename($file['name']);
            $targetPath = $this->uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $stmt = $this->db->prepare("
                    INSERT INTO documents (title, file_name, file_type, file_size, folder_id, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $title,
                    $fileName,
                    $file['type'],
                    $file['size'],
                    $folderId,
                    $userId
                ]);
                
                return ['status' => true, 'message' => 'Document uploaded successfully'];
            }
            return ['status' => false, 'message' => 'Failed to upload file'];
        } catch (PDOException $e) {
            return ['status' => false, 'message' => 'Database error'];
        }
    }
    
    public function createFolder($name, $parentId, $userId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO folders (name, parent_id, created_by) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $parentId, $userId]);
            
            return ['status' => true, 'message' => 'Folder created successfully'];
        } catch (PDOException $e) {
            return ['status' => false, 'message' => 'Database error'];
        }
    }
    
    public function getDocuments($folderId = null, $userId = null) {
        try {
            $sql = "SELECT d.*, u.fullname as uploaded_by_name 
                    FROM documents d 
                    JOIN users u ON d.uploaded_by = u.id 
                    WHERE 1=1";
            $params = [];
            
            if ($folderId !== null) {
                $sql .= " AND d.folder_id = ?";
                $params[] = $folderId;
            }
            
            if ($userId !== null) {
                $sql .= " AND d.uploaded_by = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY d.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getFolderStructure($userId = null) {
        try {
            $sql = "SELECT f.*, 
                    (SELECT COUNT(*) FROM documents d WHERE d.folder_id = f.id) as doc_count 
                    FROM folders f 
                    WHERE 1=1";
            $params = [];
            
            if ($userId !== null) {
                $sql .= " AND f.created_by = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY f.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}