<?php
/**
 * Migración: Onboarding, Campañas de email, Personalización por empresa.
 *
 * Ejecutar dentro del contenedor web:
 *   docker-compose exec web php database/migrations/migrate_fase_onboarding.php
 *
 * @author Carlos Vico
 */

$pdo = new PDO('mysql:host=db;port=3306;dbname=inventario_motos', 'admin', 'luigi21plus');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$steps = [];

// ─── Columnas en businesses ─────────────────────────────────────────────────

$steps[] = [
    'desc' => 'businesses.onboarding_completed',
    'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='onboarding_completed'",
    'sql'  => "ALTER TABLE businesses ADD COLUMN onboarding_completed TINYINT(1) DEFAULT 0 AFTER is_active",
];

$steps[] = [
    'desc' => 'businesses.theme_color',
    'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='theme_color'",
    'sql'  => "ALTER TABLE businesses ADD COLUMN theme_color VARCHAR(7) DEFAULT '#4F46E5' AFTER logo_path",
];

$steps[] = [
    'desc' => 'businesses.custom_domain',
    'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='custom_domain'",
    'sql'  => "ALTER TABLE businesses ADD COLUMN custom_domain VARCHAR(255) NULL AFTER slug",
];

// ─── Columna en employees ───────────────────────────────────────────────────

$steps[] = [
    'desc' => 'employees.last_login',
    'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='last_login'",
    'sql'  => "ALTER TABLE employees ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL",
];

// ─── Tabla email_campaigns ─────────────────────────────────────────────────

$steps[] = [
    'desc' => 'CREATE TABLE email_campaigns',
    'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_campaigns'",
    'sql'  => "CREATE TABLE IF NOT EXISTS email_campaigns (
        id               INT          AUTO_INCREMENT PRIMARY KEY,
        subject          VARCHAR(200) NOT NULL,
        body_html        TEXT         NOT NULL,
        target_plan      ENUM('all','free','basic','pro') DEFAULT 'all',
        target_status    ENUM('all','active','inactive')  DEFAULT 'active',
        status           ENUM('draft','scheduled','sent') DEFAULT 'draft',
        scheduled_at     TIMESTAMP    NULL,
        sent_at          TIMESTAMP    NULL,
        recipients_count INT          DEFAULT 0,
        created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// ─── Ejecutar ───────────────────────────────────────────────────────────────

$ok  = 0;
$skip = 0;

foreach ($steps as $step) {
    $exists = (int)$pdo->query($step['check'])->fetchColumn();
    if ($exists) {
        echo "SKIP: {$step['desc']}" . PHP_EOL;
        $skip++;
    } else {
        $pdo->exec($step['sql']);
        echo "OK:   {$step['desc']}" . PHP_EOL;
        $ok++;
    }
}

echo PHP_EOL . "Completado: {$ok} aplicado(s), {$skip} omitido(s)." . PHP_EOL;
