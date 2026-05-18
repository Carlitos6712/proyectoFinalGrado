<?php
/**
 * Controlador de notificaciones internas del superadmin.
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../core/NotificationService.php';

class NotificationController
{
    /**
     * Devuelve el conteo de notificaciones no leídas en formato JSON.
     *
     * @return array
     */
    public function unreadCount(): array
    {
        return ['count' => NotificationService::unreadCount()];
    }

    /**
     * Devuelve las últimas 10 notificaciones no leídas en formato JSON.
     *
     * @return array
     */
    public function unreadList(): array
    {
        return NotificationService::getUnread(10);
    }

    /**
     * Marca todas las notificaciones como leídas.
     *
     * @return void
     */
    public function markAllRead(): void
    {
        NotificationService::markAllRead();
    }
}
