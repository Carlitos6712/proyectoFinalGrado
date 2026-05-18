<?php
/**
 * Registro de actividad de empleados por negocio.
 *
 * Inserta en activity_logs. No lanza excepciones para no interrumpir
 * el flujo principal si el insert falla.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';

class ActivityLogger
{
    /**
     * Registra una acción en activity_logs.
     *
     * @param int         $businessId  ID del negocio.
     * @param int         $employeeId  ID del empleado que realiza la acción.
     * @param string      $action      Descripción de la acción (e.g. "Creó producto").
     * @param string|null $entity      Entidad afectada (e.g. "producto").
     * @param int|null    $entityId    ID de la entidad afectada.
     * @return void
     */
    public static function log(
        int    $businessId,
        int    $employeeId,
        string $action,
        ?string $entity   = null,
        ?int    $entityId = null
    ): void {
        try {
            $pdo  = Database::getInstance();
            $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $pdo->prepare(
                'INSERT INTO activity_logs (business_id, employee_id, action, entity, entity_id, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$businessId, $employeeId, $action, $entity, $entityId, $ip]);
        } catch (\Throwable $e) {
            $logFile = __DIR__ . '/../../logs/activity_errors.log';
            @file_put_contents(
                $logFile,
                '[' . date('Y-m-d H:i:s') . '] ActivityLogger failed: ' . $e->getMessage() . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }
}
