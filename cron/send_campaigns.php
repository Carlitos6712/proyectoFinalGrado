<?php
/**
 * Cron: Envía campañas programadas con scheduled_at <= NOW().
 *
 * Configurar en crontab:
 *   */5 * * * * php /ruta/proyecto/cron/send_campaigns.php >> /ruta/proyecto/logs/campaigns.log 2>&1
 *
 * @package  Es21Plus\Cron
 * @author   Carlos Vico
 * @version  1.0.0
 */

define('CRON_RUN', true);

// Cargar entorno
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
        putenv(trim($key) . '=' . trim($val));
        $_ENV[trim($key)] = trim($val);
    }
}

require_once __DIR__ . '/../src/includes/Database.php';
require_once __DIR__ . '/../src/core/CampaignService.php';

$logPrefix = '[' . date('Y-m-d H:i:s') . '] ';

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->query(
        "SELECT id, subject FROM email_campaigns
         WHERE status = 'draft' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()"
    );
    $campaigns = $stmt->fetchAll();

    if (!$campaigns) {
        echo $logPrefix . "Sin campañas pendientes.\n";
        exit(0);
    }

    foreach ($campaigns as $c) {
        echo $logPrefix . "Enviando campaña #{$c['id']}: {$c['subject']}\n";
        try {
            CampaignService::send((int)$c['id']);
            echo $logPrefix . "Campaña #{$c['id']} enviada.\n";
        } catch (\Throwable $e) {
            echo $logPrefix . "Error en campaña #{$c['id']}: {$e->getMessage()}\n";
        }
    }
} catch (\Throwable $e) {
    echo $logPrefix . "Error crítico: {$e->getMessage()}\n";
    exit(1);
}
