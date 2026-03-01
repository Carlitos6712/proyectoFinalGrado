<?php
class Database {
    private $host = 'db';
    private $user = 'admin';
    private $pass = 'luigi21plus';
    private $dbname = 'inventario_motos';
    private $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8");
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>