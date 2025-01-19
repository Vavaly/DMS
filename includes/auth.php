<?php
class Auth
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function login($email, $password)
    {
        try {
           
            $stmt = $this->db->prepare("SELECT id, email, password, role, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]); 
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['password'] === $password) {  
                if (!$user['is_active']) {
                    // return ['status' => false, 'message' => 'Account is inactive'];
                    return ['status' => false, 'message' => 'Akun tidak aktif!'];
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];

               
                $this->logActivity($user['id'], 'login', 'User logged in');

                return ['status' => true, 'role' => $user['role']];
            }

            return ['status' => false, 'message' => 'Email atau Password salah!'];
        } catch (PDOException $e) {
            
            error_log("Login Error: " . $e->getMessage());
            return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    
    private function logActivity($userId, $type, $description)
    {
        $stmt = $this->db->prepare("INSERT INTO user_activity (user_id, activity_type, activity_description) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $type, $description]);
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin()
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function logout()
    {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        session_destroy();
    }
}
