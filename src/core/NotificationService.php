<?php
/**
 * Servicio de notificaciones internas para el superadmin.
 *
 * Todas las inserciones y consultas son tolerantes a fallos;
 * nunca interrumpen el flujo principal si la BD no está disponible.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';

class NotificationService
{
    /**
     * Crea una notificación para el superadmin.
     *
     * @param string      $type  'new_business'|'plan_expiring'|'critical_ticket'|'new_message'|'business_deactivated'
     * @param string      $title Título breve.
     * @param string      $body  Cuerpo descriptivo.
     * @param string|null $link  URL relativa de acción directa.
     * @return void
     */
    public static function notify(
        string  $type,
        string  $title,
        string  $body,
        ?string $link = null
    ): void {
        try {
            $pdo = Database::getInstance();
            $pdo->prepare(
                'INSERT INTO superadmin_notifications (type, title, body, link)
                 VALUES (?, ?, ?, ?)'
            )->execute([$type, $title, $body, $link]);
        } catch (\Throwable) {
            // No interrumpir flujo principal
        }
    }

    /**
     * Devuelve el conteo de notificaciones no leídas.
     *
     * @return int
     */
    public static function unreadCount(): int
    {
        try {
            $pdo = Database::getInstance();
            return (int)$pdo->query(
                'SELECT COUNT(*) FROM superadmin_notifications WHERE is_read = 0'
            )->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Devuelve las últimas N notificaciones no leídas, más recientes primero.
     *
     * @param int $limit
     * @return array
     */
    public static function getUnread(int $limit = 10): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                'SELECT * FROM superadmin_notifications
                 WHERE is_read = 0
                 ORDER BY created_at DESC
                 LIMIT ?'
            );
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Marca todas las notificaciones no leídas como leídas.
     *
     * @return void
     */
    public static function markAllRead(): void
    {
        try {
            $pdo = Database::getInstance();
            $pdo->exec('UPDATE superadmin_notifications SET is_read = 1 WHERE is_read = 0');
        } catch (\Throwable) {
            // No interrumpir flujo principal
        }
    }

    /**
     * Verifica empresas con plan a punto de vencer (< 7 días) y crea
     * notificaciones de tipo 'plan_expiring' si no se creó ya una hoy.
     *
     * @return void
     */
    public static function checkExpiringPlans(): void
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query(
                "SELECT id, name, plan_expires_at
                 FROM businesses
                 WHERE is_active = 1
                   AND plan_expires_at IS NOT NULL
                   AND plan_expires_at > NOW()
                   AND plan_expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)"
            );
            foreach ($stmt->fetchAll() as $biz) {
                $linkPattern = '%id=' . $biz['id'];
                $exists = $pdo->prepare(
                    "SELECT COUNT(*) FROM superadmin_notifications
                     WHERE type = 'plan_expiring'
                       AND link LIKE ?
                       AND created_at >= CURDATE()"
                );
                $exists->execute([$linkPattern]);
                if ((int)$exists->fetchColumn() > 0) {
                    continue;
                }

                $dias = (int)ceil((strtotime($biz['plan_expires_at']) - time()) / 86400);
                self::notify(
                    'plan_expiring',
                    'Plan próximo a vencer: ' . $biz['name'],
                    "El plan de {$biz['name']} vence en {$dias} día(s) ({$biz['plan_expires_at']}).",
                    '/superadmin/businesses.php?action=edit&id=' . $biz['id']
                );
            }
        } catch (\Throwable) {
            // No interrumpir flujo principal
        }
    }
}
