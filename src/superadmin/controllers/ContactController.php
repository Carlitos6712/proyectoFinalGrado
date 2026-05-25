<?php
/**
 * Controlador de mensajes de contacto.
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../core/Mailer.php';

class ContactController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Bandeja de entrada: mensajes ordenados por no leídos primero, luego fecha.
     *
     * @return array
     */
    public function index(): array
    {
        $stmt = $this->pdo->query(
            "SELECT cm.*, b.name AS negocio
             FROM contact_messages cm
             LEFT JOIN businesses b ON b.id = cm.business_id
             ORDER BY cm.is_read ASC, cm.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Devuelve un mensaje por ID y lo marca como leído.
     *
     * @param int $id
     * @throws AppException
     * @return array
     */
    public function show(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT cm.*, b.name AS negocio
             FROM contact_messages cm
             LEFT JOIN businesses b ON b.id = cm.business_id
             WHERE cm.id = ?"
        );
        $stmt->execute([$id]);
        $msg = $stmt->fetch();
        if (!$msg) {
            throw new AppException('Mensaje no encontrado.', 404);
        }

        if (!$msg['is_read']) {
            $this->pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$id]);
            $msg['is_read'] = 1;
        }

        return $msg;
    }

    /**
     * Envía respuesta al remitente y registra replied_at.
     *
     * @param int    $id
     * @param string $replyBody Texto de la respuesta.
     * @throws AppException
     * @return void
     */
    public function reply(int $id, string $replyBody): void
    {
        $msg = $this->show($id);
        $body = nl2br(htmlspecialchars($replyBody, ENT_QUOTES, 'UTF-8'));
        $html = "<p>Hola {$msg['sender_name']},</p><p>{$body}</p><p>— Equipo es21plus</p>";

        Mailer::send($msg['sender_email'], 'Re: ' . ($msg['subject'] ?? 'Consulta'), $html);

        $this->pdo->prepare('UPDATE contact_messages SET replied_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /**
     * Recibe un mensaje desde el formulario público y notifica al superadmin.
     *
     * @param array $data Campos: sender_name, sender_email, subject, message.
     * @param int|null $businessId
     * @throws AppException
     * @return void
     */
    public function recibir(array $data, ?int $businessId = null): void
    {
        $name    = trim($data['sender_name'] ?? '');
        $email   = trim($data['sender_email'] ?? '');
        $message = trim($data['message'] ?? '');

        if (!$name || !$email || !$message) {
            throw new AppException('Nombre, email y mensaje son obligatorios.', 422);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO contact_messages (business_id, sender_name, sender_email, subject, message)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $businessId,
            $name,
            $email,
            trim($data['subject'] ?? '') ?: null,
            $message,
        ]);

        $adminEmail = getenv('SUPERADMIN_EMAIL') ?: '';
        if ($adminEmail) {
            $subject = 'Nuevo mensaje de contacto – ' . $name;
            $body    = "<p><strong>De:</strong> {$name} ({$email})</p>
                        <p><strong>Asunto:</strong> " . htmlspecialchars($data['subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</p>
                        <p><strong>Mensaje:</strong><br>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</p>";
            try {
                Mailer::send($adminEmail, $subject, $body);
            } catch (\Throwable) {
                // No bloquear flujo si correo falla
            }
        }
    }
}
