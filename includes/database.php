<?php
class Database
{
    private $host = "localhost";
    private $db_name = "final_project";
    private $username = "root";
    private $password = "";
    private $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $this->conn;
        } catch (PDOException $e) {
            
            error_log("Connection Error: " . $e->getMessage());

           
            if ($e->getCode() == 1049) {
                die("Database '{$this->db_name}' tidak ditemukan. Pastikan database sudah dibuat.");
            } elseif ($e->getCode() == 1045) {
                die("Username atau password database salah. Periksa konfigurasi database Anda.");
            } elseif ($e->getCode() == 2002) {
                die("Tidak dapat terhubung ke database server. Pastikan MySQL server sudah berjalan.");
            } else {
                die("Terjadi kesalahan saat menghubungkan ke database. Error: " . $e->getMessage());
            }
        }
    }
}
