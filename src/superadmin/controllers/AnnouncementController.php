<?php
/**
 * Controlador de anuncios globales.
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';

class AnnouncementController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /** @return array Lista completa de anuncios. */
    public function index(): array
    {
        return $this->pdo->query(
            'SELECT * FROM announcements ORDER BY created_at DESC'
        )->fetchAll();
    }

    /**
     * Crea un anuncio.
     *
     * @param array $data title, body.
     * @throws AppException
     * @return int
     */
    public function store(array $data): int
    {
        $title = trim($data['title'] ?? '');
        $body  = trim($data['body'] ?? '');
        if (!$title || !$body) {
            throw new AppException('Título y cuerpo son obligatorios.', 422);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO announcements (title, body) VALUES (?, ?)'
        );
        $stmt->execute([$title, $body]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Activa o desactiva un anuncio.
     *
     * @param int $id
     * @return bool Nuevo estado.
     */
    public function toggle(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT is_active FROM announcements WHERE id = ?');
        $stmt->execute([$id]);
        $current = (int)$stmt->fetchColumn();
        $nuevo   = $current === 1 ? 0 : 1;

        $this->pdo->prepare('UPDATE announcements SET is_active = ? WHERE id = ?')->execute([$nuevo, $id]);
        return (bool)$nuevo;
    }

    /**
     * Devuelve el anuncio activo más reciente (para banner en panel de negocios).
     *
     * @return array|null
     */
    public static function activo(): ?array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                'SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1'
            );
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
