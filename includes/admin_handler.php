<?php
// includes/admin_handler.php
class AdminHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Total Documents
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM documents");
            $stats['total_documents'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total Users
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
            $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Active Users
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND is_active = 1");
            $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Inactive Users
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND is_active = 0");
            $stats['inactive_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
        } catch (PDOException $e) {
            return [
                'total_documents' => 0,
                'total_users' => 0,
                'active_users' => 0,
                'inactive_users' => 0
            ];
        }
    }
    
    public function getRecentDocuments($limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT d.*, u.fullname as uploaded_by_name, f.name as folder_name
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                LEFT JOIN folders f ON d.folder_id = f.id
                ORDER BY d.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getRecentUsers($limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, fullname, email, created_at, is_active
                FROM users
                WHERE role = 'user'
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getActivityLogs($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT l.*, u.fullname
                FROM activity_logs l
                JOIN users u ON l.user_id = u.id
                ORDER BY l.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}