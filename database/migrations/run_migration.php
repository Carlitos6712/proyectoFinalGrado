<?php
/**
 * Runner de migración superadmin_panel.
 * Ejecutar: php run_migration.php (dentro del contenedor web)
 * @author Carlos Vico
 */
$pdo = new PDO('mysql:host=db;port=3306;dbname=inventario_motos', 'admin', 'luigi21plus');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqls = [];

$sqls[] = "CREATE TABLE IF NOT EXISTS businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    logo_path VARCHAR(255),
    contact_email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    plan ENUM('free','basic','pro') DEFAULT 'free',
    plan_expires_at DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sqls[] = "CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','employee') DEFAULT 'employee',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sqls[] = "CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    sender_name VARCHAR(100) NOT NULL,
    sender_email VARCHAR(150) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sqls[] = "CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    employee_id INT,
    action VARCHAR(200) NOT NULL,
    entity VARCHAR(100),
    entity_id INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sqls[] = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

foreach (['productos', 'categorias', 'movimientos', 'alertas_stock'] as $tbl) {
    $has = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl' AND COLUMN_NAME='business_id'")->fetchColumn();
    if (!$has) {
        $sqls[] = "ALTER TABLE $tbl ADD COLUMN business_id INT NULL AFTER id";
    }
}

foreach ($sqls as $sql) {
    $pdo->exec($sql);
    echo 'OK: ' . substr($sql, 0, 70) . PHP_EOL;
}

echo PHP_EOL . 'Tables: ' . implode(', ', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)) . PHP_EOL;
