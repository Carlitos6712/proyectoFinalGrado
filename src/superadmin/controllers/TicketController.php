<?php
/**
 * Controlador de tickets de soporte.
 *
 * Gestiona tanto la perspectiva del superadmin (todas las empresas)
 * como la del panel de empresa (sus propios tickets).
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../core/NotificationService.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/../../core/Settings.php';

class TicketController
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ──────────────────────────────────────────
    //  Superadmin: listado paginado con filtros
    // ──────────────────────────────────────────

    /**
     * Devuelve tickets paginados con filtros opcionales.
     *
     * @param array<string,mixed> $filters  Keys: status, priority, business_id, q (búsqueda)
     * @param int                 $page     Página actual (1-based)
     * @param int                 $perPage  Items por página
     * @return array{items: array, total: int, pages: int}
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'st.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[]  = 'st.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['business_id'])) {
            $where[]  = 'st.business_id = ?';
            $params[] = (int)$filters['business_id'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(st.subject LIKE ? OR st.ticket_number LIKE ? OR b.name LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = implode(' AND ', $where);

        $total = (int)$this->pdo->prepare(
            "SELECT COUNT(*) FROM support_tickets st
             LEFT JOIN businesses b ON b.id = st.business_id
             WHERE {$sql}"
        )->execute($params) ? $this->pdo->prepare(
            "SELECT COUNT(*) FROM support_tickets st
             LEFT JOIN businesses b ON b.id = st.business_id
             WHERE {$sql}"
        )->execute($params) && 1 : 0;

        // Count correctly
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM support_tickets st
             LEFT JOIN businesses b ON b.id = st.business_id
             WHERE {$sql}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $pages  = max(1, (int)ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $itemStmt = $this->pdo->prepare(
            "SELECT st.*, b.name AS business_name,
                    (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = st.id) AS msg_count
             FROM support_tickets st
             LEFT JOIN businesses b ON b.id = st.business_id
             WHERE {$sql}
             ORDER BY
                CASE st.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
                st.updated_at DESC
             LIMIT ? OFFSET ?"
        );
        $itemStmt->execute(array_merge($params, [$perPage, $offset]));

        return ['items' => $itemStmt->fetchAll(), 'total' => $total, 'pages' => $pages];
    }

    // ──────────────────────────────────────────
    //  Superadmin: detalle de ticket
    // ──────────────────────────────────────────

    /**
     * Devuelve datos completos de un ticket con sus mensajes.
     *
     * @param  int   $id
     * @return array{ticket: array, messages: array}
     * @throws AppException Si el ticket no existe.
     */
    public function view(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT st.*, b.name AS business_name, b.email AS business_email
             FROM support_tickets st
             LEFT JOIN businesses b ON b.id = st.business_id
             WHERE st.id = ?'
        );
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            throw new AppException('Ticket no encontrado.');
        }

        $msgStmt = $this->pdo->prepare(
            'SELECT tm.*,
                    (SELECT GROUP_CONCAT(ta.original_name ORDER BY ta.id SEPARATOR ",")
                     FROM ticket_attachments ta WHERE ta.message_id = tm.id) AS attachments
             FROM ticket_messages tm WHERE tm.ticket_id = ? ORDER BY tm.created_at ASC'
        );
        $msgStmt->execute([$id]);

        return ['ticket' => $ticket, 'messages' => $msgStmt->fetchAll()];
    }

    // ──────────────────────────────────────────
    //  Superadmin: actualizar estado / prioridad
    // ──────────────────────────────────────────

    /**
     * Actualiza el estado y/o prioridad de un ticket.
     *
     * @param int    $id
     * @param string $status   'open'|'in_progress'|'resolved'|'closed'
     * @param string $priority 'low'|'normal'|'high'|'urgent'
     * @throws AppException Si el ticket no existe.
     */
    public function updateStatus(int $id, string $status, string $priority): void
    {
        $validStatuses   = ['open', 'in_progress', 'resolved', 'closed'];
        $validPriorities = ['low', 'normal', 'high', 'urgent'];

        if (!in_array($status, $validStatuses, true)) {
            throw new AppException('Estado no válido.');
        }
        if (!in_array($priority, $validPriorities, true)) {
            throw new AppException('Prioridad no válida.');
        }

        $closedAt = in_array($status, ['resolved', 'closed'], true) ? 'NOW()' : 'NULL';

        $stmt = $this->pdo->prepare(
            "UPDATE support_tickets
             SET status = ?, priority = ?, closed_at = {$closedAt}
             WHERE id = ?"
        );
        $stmt->execute([$status, $priority, $id]);

        if ($stmt->rowCount() === 0) {
            throw new AppException('Ticket no encontrado.');
        }

        // Notificar al negocio por email si resuelto/cerrado
        if (in_array($status, ['resolved', 'closed'], true)) {
            $this->sendStatusEmail($id, $status);
        }
    }

    // ──────────────────────────────────────────
    //  Superadmin: responder ticket
    // ──────────────────────────────────────────

    /**
     * El superadmin publica un mensaje en el ticket.
     *
     * @param int    $id         ID del ticket.
     * @param string $message    Texto del mensaje.
     * @param int    $saUserId   ID del superadmin (de sesión).
     * @throws AppException Si el mensaje está vacío o el ticket no existe.
     */
    public function replyAsSuperadmin(int $id, string $message, int $saUserId): void
    {
        $message = trim($message);
        if ($message === '') {
            throw new AppException('El mensaje no puede estar vacío.');
        }

        $ticket = $this->getTicketRaw($id);

        $this->pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message)
             VALUES (?, "superadmin", ?, ?)'
        )->execute([$id, $saUserId, $message]);

        $this->pdo->prepare(
            'UPDATE support_tickets SET status = "in_progress", updated_at = NOW() WHERE id = ? AND status = "open"'
        )->execute([$id]);

        // Email al negocio
        $this->sendReplyEmail($ticket, $message, 'superadmin');
    }

    // ──────────────────────────────────────────
    //  Panel de empresa: crear ticket
    // ──────────────────────────────────────────

    /**
     * Crea un nuevo ticket desde el panel de empresa.
     *
     * @param string $subject    Asunto del ticket.
     * @param string $message    Mensaje inicial.
     * @param int    $businessId ID del negocio.
     * @param int    $employeeId ID del empleado que abre el ticket.
     * @return int ID del ticket creado.
     * @throws AppException Si los datos son inválidos.
     */
    public function createTicket(
        string $subject,
        string $message,
        int    $businessId,
        int    $employeeId
    ): int {
        $subject = trim($subject);
        $message = trim($message);

        if ($subject === '') {
            throw new AppException('El asunto es obligatorio.');
        }
        if ($message === '') {
            throw new AppException('El mensaje es obligatorio.');
        }

        $ticketNumber = $this->generateTicketNumber();

        $this->pdo->prepare(
            'INSERT INTO support_tickets
             (business_id, employee_id, ticket_number, subject, status, priority)
             VALUES (?, ?, ?, ?, "open", "normal")'
        )->execute([$businessId, $employeeId, $ticketNumber, $subject]);

        $ticketId = (int)$this->pdo->lastInsertId();

        // Mensaje inicial
        $this->pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message)
             VALUES (?, "business", ?, ?)'
        )->execute([$ticketId, $employeeId, $message]);

        // Notificación y email al superadmin
        NotificationService::notify(
            'critical_ticket',
            'Nuevo ticket: ' . $subject,
            'Empresa ID ' . $businessId . ' abrió el ticket ' . $ticketNumber,
            '/superadmin/tickets/view.php?id=' . $ticketId
        );

        $saEmail = Settings::get('superadmin_email', '');
        if ($saEmail) {
            try {
                $html = "<h3>Nuevo ticket de soporte: {$ticketNumber}</h3>"
                    . "<p><strong>Asunto:</strong> " . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . "</p>"
                    . "<p><strong>Mensaje:</strong></p>"
                    . "<blockquote>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</blockquote>"
                    . "<p><a href='/superadmin/tickets/view.php?id={$ticketId}'>Ver ticket en el panel</a></p>";
                Mailer::send($saEmail, "Nuevo ticket #{$ticketNumber}: " . $subject, $html);
            } catch (\Throwable) {
                // No interrumpir el flujo
            }
        }

        return $ticketId;
    }

    // ──────────────────────────────────────────
    //  Panel de empresa: listar tickets propios
    // ──────────────────────────────────────────

    /**
     * Lista los tickets de un negocio, más reciente primero.
     *
     * @param  int $businessId
     * @return array
     */
    public function listForBusiness(int $businessId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT st.*,
                    (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = st.id) AS msg_count
             FROM support_tickets st
             WHERE st.business_id = ?
             ORDER BY st.updated_at DESC'
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll();
    }

    // ──────────────────────────────────────────
    //  Panel de empresa: ver ticket propio
    // ──────────────────────────────────────────

    /**
     * Devuelve un ticket con sus mensajes, verificando que pertenece al negocio.
     *
     * @param  int   $id
     * @param  int   $businessId
     * @return array{ticket: array, messages: array}
     * @throws AppException Si el ticket no existe o no pertenece al negocio.
     */
    public function getTicketForBusiness(int $id, int $businessId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM support_tickets WHERE id = ? AND business_id = ?'
        );
        $stmt->execute([$id, $businessId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            throw new AppException('Ticket no encontrado.');
        }

        $msgStmt = $this->pdo->prepare(
            'SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC'
        );
        $msgStmt->execute([$id]);

        return ['ticket' => $ticket, 'messages' => $msgStmt->fetchAll()];
    }

    // ──────────────────────────────────────────
    //  Panel de empresa: responder ticket
    // ──────────────────────────────────────────

    /**
     * El negocio añade un mensaje a un ticket existente.
     *
     * @param int    $ticketId   ID del ticket.
     * @param int    $businessId ID del negocio (para verificar pertenencia).
     * @param int    $employeeId ID del empleado.
     * @param string $message    Texto del mensaje.
     * @throws AppException Si el ticket está cerrado o no existe.
     */
    public function replyFromBusiness(
        int    $ticketId,
        int    $businessId,
        int    $employeeId,
        string $message
    ): void {
        $message = trim($message);
        if ($message === '') {
            throw new AppException('El mensaje no puede estar vacío.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM support_tickets WHERE id = ? AND business_id = ?'
        );
        $stmt->execute([$ticketId, $businessId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            throw new AppException('Ticket no encontrado.');
        }
        if ($ticket['status'] === 'closed') {
            throw new AppException('El ticket está cerrado y no acepta más respuestas.');
        }

        $this->pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message)
             VALUES (?, "business", ?, ?)'
        )->execute([$ticketId, $employeeId, $message]);

        $this->pdo->prepare(
            'UPDATE support_tickets SET updated_at = NOW() WHERE id = ?'
        )->execute([$ticketId]);

        // Notificar al superadmin
        NotificationService::notify(
            'critical_ticket',
            'Respuesta en ticket: ' . $ticket['ticket_number'],
            'Nueva respuesta del negocio en el ticket ' . $ticket['ticket_number'],
            '/superadmin/tickets/view.php?id=' . $ticketId
        );

        $saEmail = Settings::get('superadmin_email', '');
        if ($saEmail) {
            try {
                $html = "<h3>Respuesta en ticket {$ticket['ticket_number']}</h3>"
                    . "<p><strong>Asunto:</strong> " . htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8') . "</p>"
                    . "<blockquote>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</blockquote>"
                    . "<p><a href='/superadmin/tickets/view.php?id={$ticketId}'>Ver ticket</a></p>";
                Mailer::send($saEmail, "Respuesta en #{$ticket['ticket_number']}", $html);
            } catch (\Throwable) {}
        }
    }

    // ──────────────────────────────────────────
    //  Helpers privados
    // ──────────────────────────────────────────

    private function generateTicketNumber(): string
    {
        $year  = date('Y');
        $count = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM support_tickets WHERE YEAR(created_at) = {$year}"
        )->fetchColumn();
        return 'TK-' . $year . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }

    private function getTicketRaw(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT st.*, b.email AS business_email
             FROM support_tickets st
             LEFT JOIN businesses b ON b.id = st.business_id
             WHERE st.id = ?'
        );
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            throw new AppException('Ticket no encontrado.');
        }
        return $ticket;
    }

    private function sendReplyEmail(array $ticket, string $message, string $from): void
    {
        try {
            if ($from === 'superadmin' && !empty($ticket['business_email'])) {
                $html = "<h3>Nueva respuesta en tu ticket {$ticket['ticket_number']}</h3>"
                    . "<p><strong>Asunto:</strong> " . htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8') . "</p>"
                    . "<blockquote>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</blockquote>"
                    . "<p>Ingresa a tu panel para responder o ver el hilo completo.</p>";
                Mailer::send($ticket['business_email'], "Respuesta en #{$ticket['ticket_number']}", $html);
            }
        } catch (\Throwable) {}
    }

    private function sendStatusEmail(int $ticketId, string $status): void
    {
        try {
            $ticket = $this->getTicketRaw($ticketId);
            if (empty($ticket['business_email'])) {
                return;
            }
            $label = $status === 'resolved' ? 'resuelto' : 'cerrado';
            $html  = "<h3>Tu ticket {$ticket['ticket_number']} fue {$label}</h3>"
                . "<p><strong>Asunto:</strong> " . htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8') . "</p>"
                . "<p>Si tienes más dudas, abre un nuevo ticket desde tu panel.</p>";
            Mailer::send($ticket['business_email'], "Ticket #{$ticket['ticket_number']} {$label}", $html);
        } catch (\Throwable) {}
    }
}
