<?php
// config/database.php
class Database {
    private $host = 'srv1597.hstgr.io';
    private $db_name = 'u565673608_web_oste';
    private $username = 'u565673608_web_oste'; // Cambiar según tu configuración
    private $password = 'AT~H*#A8U$q0';     // Cambiar según tu configuración
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>