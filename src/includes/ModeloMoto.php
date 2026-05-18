<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../core/Session.php';

/**
 * Modelo de gestión de modelos de moto para compatibilidades de productos.
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @version  1.0.0
 */
class ModeloMoto
{
    private PDO $pdo;

    /**
     * @param PDO|null $pdo Conexión PDO inyectada (útil para tests con SQLite); si es null usa el singleton MySQL.
     * @throws AppException Si falla la conexión.
     * @author Carlitos6712
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Lista todos los modelos de moto ordenados por marca, modelo y año de inicio.
     *
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listar(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM modelos_moto ORDER BY marca ASC, modelo ASC, anio_desde ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un modelo de moto por su ID.
     *
     * @param int $id Identificador del modelo.
     * @throws AppException Si el modelo no existe (código 404).
     * @return array<string, mixed>
     * @author Carlitos6712
     */
    public function obtenerPorId(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM modelos_moto WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new AppException("Modelo de moto #{$id} no encontrado.", 404);
        }
        return $row;
    }

    /**
     * Crea un nuevo modelo de moto.
     *
     * @param string   $marca     Marca de la moto (no puede estar vacía).
     * @param string   $modelo    Modelo de la moto (no puede estar vacío).
     * @param int      $anioDes   Año de inicio de fabricación.
     * @param int|null $anioHasta Año de fin de fabricación (null si sigue en producción).
     * @throws AppException Si la marca o el modelo están vacíos (código 400).
     * @return int ID del modelo creado.
     * @author Carlitos6712
     */
    public function crear(string $marca, string $modelo, int $anioDes, ?int $anioHasta): int
    {
        if ($marca === '') {
            throw new AppException('La marca del modelo de moto es obligatoria.', 400);
        }
        if ($modelo === '') {
            throw new AppException('El modelo de moto es obligatorio.', 400);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO modelos_moto (marca, modelo, anio_desde, anio_hasta)
             VALUES (:marca, :modelo, :anio_desde, :anio_hasta)"
        );
        $stmt->execute([
            ':marca'      => $marca,
            ':modelo'     => $modelo,
            ':anio_desde' => $anioDes,
            ':anio_hasta' => $anioHasta,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Elimina un modelo de moto si no tiene productos compatibles asociados.
     *
     * @param int $id Identificador del modelo.
     * @throws AppException Si el modelo tiene compatibilidades activas (código 409).
     * @return bool True si se eliminó correctamente.
     * @author Carlitos6712
     */
    public function eliminar(int $id): bool
    {
        $stmtCount = $this->pdo->prepare(
            "SELECT COUNT(*) FROM compatibilidades WHERE modelo_id = :id"
        );
        $stmtCount->execute([':id' => $id]);
        $count = (int) $stmtCount->fetchColumn();

        if ($count > 0) {
            throw new AppException(
                "No se puede eliminar: el modelo tiene {$count} producto(s) compatible(s) registrado(s).",
                409
            );
        }

        $stmt = $this->pdo->prepare("DELETE FROM modelos_moto WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return true;
    }

    /**
     * Lista los modelos de moto con los campos necesarios para un select HTML.
     *
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listarParaSelect(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, marca, modelo, anio_desde, anio_hasta
             FROM modelos_moto
             ORDER BY marca ASC, modelo ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
