<?php
/**
 * Registro de actividad de empleados por negocio.
 *
 * Inserta en activity_logs. No lanza excepciones para no interrumpir
 * el flujo principal si el insert falla.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.1.0
 */

require_once __DIR__ . '/../includes/Database.php';

class ActivityLogger
{
    /**
     * Registra una acción en activity_logs.
     *
     * @param int         $businessId  ID del negocio (0 para acciones de superadmin global).
     * @param int         $employeeId  ID del empleado que realiza la acción.
     * @param string      $action      Descripción de la acción (e.g. "Creó producto").
     * @param string|null $entity      Entidad afectada (e.g. "producto").
     * @param int|null    $entityId    ID de la entidad afectada.
     * @param array       $metadata    Datos adicionales antes/después para auditoría.
     * @param bool        $isCritical  Si true, la acción se marca como crítica.
     * @return void
     */
    public static function log(
        int     $businessId,
        int     $employeeId,
        string  $action,
        ?string $entity     = null,
        ?int    $entityId   = null,
        array   $metadata   = [],
        bool    $isCritical = false
    ): void {
        try {
            $pdo          = Database::getInstance();
            $ip           = $_SERVER['REMOTE_ADDR'] ?? null;
            $metadataJson = !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

            $stmt = $pdo->prepare(
                'INSERT INTO activity_logs
                    (business_id, employee_id, action, entity, entity_id, ip_address, metadata, is_critical)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $businessId ?: null,
                $employeeId ?: null,
                $action,
                $entity,
                $entityId,
                $ip,
                $metadataJson,
                $isCritical ? 1 : 0,
            ]);
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
