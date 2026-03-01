<?php
require_once 'Database.php';

class Categoria {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    /**
     * Lista todas las categorías
     * @return array Arreglo asociativo con las categorías
     */
    public function listar() {
        $sql = "SELECT * FROM categorias ORDER BY nombre";
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene una categoría por su ID
     * @param int $id
     * @return array|null
     */
    public function obtenerPorId($id) {
        $stmt = $this->conn->prepare("SELECT * FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Crea una nueva categoría
     * @param string $nombre
     * @param string $descripcion
     * @return bool
     */
    public function crear($nombre, $descripcion = '') {
        $stmt = $this->conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $descripcion);
        return $stmt->execute();
    }

    /**
     * Actualiza una categoría existente
     * @param int $id
     * @param string $nombre
     * @param string $descripcion
     * @return bool
     */    

    public function actualizar($id, $nombre, $descripcion = '') {
        $stmt = $this->conn->prepare("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nombre, $descripcion, $id);
        return $stmt->execute();
    }

    /**
     * Elimina una categoría por su ID
     * @param int $id
     * @return bool
     */

    public function eliminar($id) {
        $stmt = $this->conn->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
 
    }
}
?>