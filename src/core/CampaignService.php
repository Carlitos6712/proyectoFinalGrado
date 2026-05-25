<?php
/**
 * Servicio de envío de campañas de email masivo.
 *
 * Carga la campaña desde BD, obtiene destinatarios según segmento
 * e itera con pausa de 0.1s entre envíos para no saturar SMTP.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/Mailer.php';

class CampaignService
{
    /**
     * Envía una campaña a sus destinatarios segmentados.
     *
     * Actualiza recipients_count, status y sent_at al terminar.
     *
     * @param int $campaignId ID de la campaña en email_campaigns.
     * @return void
     */
    public static function send(int $campaignId): void
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM email_campaigns WHERE id = ?');
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            return;
        }

        $recipients = self::getRecipients(
            $campaign['target_plan'],
            $campaign['target_status']
        );

        $sent = 0;
        foreach ($recipients as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            try {
                Mailer::send($email, $campaign['subject'], $campaign['body_html']);
                $sent++;
            } catch (\Throwable) {
                // Fallos individuales no detienen el envío
            }
            usleep(100000); // 0.1s entre correos
        }

        $pdo->prepare(
            "UPDATE email_campaigns
             SET status = 'sent', sent_at = NOW(), recipients_count = ?
             WHERE id = ?"
        )->execute([$sent, $campaignId]);
    }

    /**
     * Devuelve la lista de emails según los filtros de segmentación.
     *
     * @param string $targetPlan   'all'|'free'|'basic'|'pro'
     * @param string $targetStatus 'all'|'active'|'inactive'
     * @return string[]
     */
    public static function getRecipients(string $targetPlan, string $targetStatus): array
    {
        $pdo  = Database::getInstance();
        $sql  = 'SELECT contact_email FROM businesses WHERE contact_email IS NOT NULL AND contact_email != \'\'';
        $args = [];

        if ($targetPlan !== 'all') {
            $sql .= ' AND plan = ?';
            $args[] = $targetPlan;
        }

        if ($targetStatus === 'active') {
            $sql .= ' AND is_active = 1';
        } elseif ($targetStatus === 'inactive') {
            $sql .= ' AND is_active = 0';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Cuenta los destinatarios estimados según filtros (para el formulario).
     *
     * @param string $targetPlan
     * @param string $targetStatus
     * @return int
     */
    public static function estimateRecipients(string $targetPlan, string $targetStatus): int
    {
        return count(self::getRecipients($targetPlan, $targetStatus));
    }
}
