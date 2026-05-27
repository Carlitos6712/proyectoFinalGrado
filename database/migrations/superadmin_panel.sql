-- =============================================================
-- Migración: Panel Superadmin
-- @author   Carlos Vico
-- @version  1.0.0
-- Ejecutar: una sola vez sobre la base de datos objetivo.
-- =============================================================

SET NAMES utf8mb4;
USE inventario_motos;

-- -------------------------------------------------------------
-- Tabla: businesses (negocios / talleres clientes)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS businesses (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)  NOT NULL,
    slug            VARCHAR(100)  NOT NULL UNIQUE,
    logo_path       VARCHAR(255),
    contact_email   VARCHAR(150),
    phone           VARCHAR(30),
    address         TEXT,
    plan            ENUM('free','basic','pro') DEFAULT 'free',
    plan_expires_at DATE,
    is_active       TINYINT(1)    DEFAULT 1,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: employees (empleados por negocio)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
    id            INT           AUTO_INCREMENT PRIMARY KEY,
    business_id   INT           NOT NULL,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL,
    password      VARCHAR(255)  NOT NULL,
    role          ENUM('admin','employee') DEFAULT 'employee',
    is_active     TINYINT(1)    DEFAULT 1,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: contact_messages (bandeja de contacto)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id            INT           AUTO_INCREMENT PRIMARY KEY,
    business_id   INT,
    sender_name   VARCHAR(100)  NOT NULL,
    sender_email  VARCHAR(150)  NOT NULL,
    subject       VARCHAR(200),
    message       TEXT          NOT NULL,
    is_read       TINYINT(1)    DEFAULT 0,
    replied_at    TIMESTAMP     NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: activity_logs (registro de actividad por negocio)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id            INT           AUTO_INCREMENT PRIMARY KEY,
    business_id   INT,
    employee_id   INT,
    action        VARCHAR(200)  NOT NULL,
    entity        VARCHAR(100),
    entity_id     INT,
    ip_address    VARCHAR(45),
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: announcements (anuncios globales del superadmin)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
    id           INT           AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200)  NOT NULL,
    body         TEXT          NOT NULL,
    is_active    TINYINT(1)    DEFAULT 1,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Añadir business_id a tablas existentes (idempotente vía INFORMATION_SCHEMA)
-- -------------------------------------------------------------

-- productos
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'business_id');
SET @s = IF(@has = 0, 'ALTER TABLE productos ADD COLUMN business_id INT NULL AFTER id', 'SELECT 1');
PREPARE _stmt FROM @s; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- categorias
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias' AND COLUMN_NAME = 'business_id');
SET @s = IF(@has = 0, 'ALTER TABLE categorias ADD COLUMN business_id INT NULL AFTER id', 'SELECT 1');
PREPARE _stmt FROM @s; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- movimientos
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'business_id');
SET @s = IF(@has = 0, 'ALTER TABLE movimientos ADD COLUMN business_id INT NULL AFTER id', 'SELECT 1');
PREPARE _stmt FROM @s; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- alertas_stock
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alertas_stock' AND COLUMN_NAME = 'business_id');
SET @s = IF(@has = 0, 'ALTER TABLE alertas_stock ADD COLUMN business_id INT NULL AFTER id', 'SELECT 1');
PREPARE _stmt FROM @s; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;
