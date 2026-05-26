-- =============================================================
-- Sistema de Inventario para Mecánico de Motos – DDL completo
-- @author  Carlos Vico
-- @version 1.1.0
-- =============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS inventario_motos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventario_motos;

-- -------------------------------------------------------------
-- Tabla: categorias
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    descripcion TEXT,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: marcas
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS marcas (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    descripcion TEXT,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: productos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
    id                INT            AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(200)   NOT NULL,
    descripcion       TEXT,
    descripcion_larga TEXT,
    precio            DECIMAL(10,2)  DEFAULT 0.00,
    stock             INT            DEFAULT 0,
    stock_minimo      INT            DEFAULT 5,
    codigo_ref        VARCHAR(50),
    marca             VARCHAR(100),
    marca_id          INT            NULL,
    codigo_barras     VARCHAR(100),
    url_proveedor     VARCHAR(500),
    proveedor         VARCHAR(150),
    ubicacion         VARCHAR(100),
    peso              INT            NULL,
    capacidad         INT            NULL,
    longitud          INT            NULL,
    anchura           INT            NULL,
    diametro          DECIMAL(6,2)   NULL,
    alertas_email     TINYINT(1)     DEFAULT 1,
    imagen            VARCHAR(255),
    categoria_id      INT,
    created_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        TIMESTAMP      NULL DEFAULT NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (marca_id)     REFERENCES marcas(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: movimientos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimientos (
    id            INT       AUTO_INCREMENT PRIMARY KEY,
    producto_id   INT       NOT NULL,
    tipo          ENUM('entrada','salida') NOT NULL,
    cantidad      INT       NOT NULL,
    observaciones TEXT,
    usuario       VARCHAR(100) DEFAULT 'admin',
    fecha         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: alertas_stock (deduplicación de alertas de email)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alertas_stock (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    producto_id     INT NOT NULL,
    enviada_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stock_al_enviar INT,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Datos iniciales de categorías
-- -------------------------------------------------------------
INSERT IGNORE INTO categorias (id, nombre, descripcion) VALUES
(1, 'Frenos',      'Pastillas, discos, cables y líquido de frenos'),
(2, 'Motor',       'Filtros, bujías, aceites y componentes de motor'),
(3, 'Transmisión', 'Cadenas, piñones, coronas y variadores'),
(4, 'Electricidad','Bombillas, baterías, reguladores y cableado'),
(5, 'Carrocería',  'Carenados, retrovisores, manillares y accesorios');

-- -------------------------------------------------------------
-- Tabla: auditoria (historial de cambios en entidades)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria (
    id               BIGINT       AUTO_INCREMENT PRIMARY KEY,
    tabla            VARCHAR(50)  NOT NULL,
    registro_id      INT          NOT NULL,
    accion           ENUM('crear','actualizar','eliminar') NOT NULL,
    datos_anteriores JSON,
    datos_nuevos     JSON,
    usuario          VARCHAR(100) DEFAULT 'admin',
    ip               VARCHAR(45),
    fecha            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: usuarios (autenticación)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id              INT          AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    email           VARCHAR(150),
    rol             ENUM('admin','operario') DEFAULT 'operario',
    activo          TINYINT(1)   DEFAULT 1,
    last_login      TIMESTAMP    NULL DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migración idempotente para instalaciones existentes sin columna rol
-- (MySQL 8.0 no soporta IF NOT EXISTS en ADD COLUMN; usar stored procedure condicional)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol');
SET @sql = IF(@col_exists = 0, "ALTER TABLE usuarios ADD COLUMN rol ENUM('admin','operario') DEFAULT 'operario'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------
-- Tabla: intentos_login (rate limiting)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS intentos_login (
    id         INT         AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    intentos   INT         DEFAULT 1,
    bloqueado_hasta TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario por defecto: admin / admin123 (cambiar en producción)
INSERT IGNORE INTO usuarios (username, password_hash, nombre_completo, email, rol)
VALUES (
    'admin',
    '$2y$12$u511.V6/emTC2UtfekF25.u32bBNzxz8STOEunD6Er9biXLmOaUzm', -- password: admin123
    'Carlos Vico',
    'admin@es21plus.local',
    'admin'
);

-- Asegurar que el usuario admin existente tenga rol admin (instalaciones previas)
UPDATE usuarios SET rol = 'admin' WHERE username = 'admin' AND (rol IS NULL OR rol = 'operario');

-- =============================================================
-- FASE 11: Sistema de roles (superadmin / admin / employee)
-- =============================================================

-- Paso 1: ampliar el ENUM para incluir los nuevos valores antes de migrar datos
ALTER TABLE usuarios MODIFY COLUMN rol
    ENUM('superadmin','admin','operario','employee') NOT NULL DEFAULT 'employee';

-- Paso 2: migrar operario → employee
UPDATE usuarios SET rol = 'employee' WHERE rol = 'operario';

-- Paso 3: reducir el ENUM eliminando el valor obsoleto 'operario'
ALTER TABLE usuarios MODIFY COLUMN rol
    ENUM('superadmin','admin','employee') NOT NULL DEFAULT 'employee';

-- Migración idempotente: columna activo (renombrada de activo, ya existe)
-- La columna ya existe como 'activo' en instalaciones previas. Sin acción.

-- Usuario superadmin para pruebas (contraseña: superadmin123 — cambiar en producción)
INSERT IGNORE INTO usuarios (username, password_hash, nombre_completo, email, rol, activo)
VALUES (
    'superadmin',
    '$2y$12$aOI.LfKE1efqYhkjGUx8DeXf8GPyf.07jkuoVQ8iVs/yAxoW.cXWq',
    'Super Administrador',
    'superadmin@es21plus.local',
    'superadmin',
    1
);

-- Usuario employee para pruebas (contraseña: employee123 — cambiar en producción)
INSERT IGNORE INTO usuarios (username, password_hash, nombre_completo, email, rol, activo)
VALUES (
    'empleado',
    '$2y$12$ea0TAFIMAM4aswJvLYLipe7W7ALfBQDiuxovLk9d3t0csRLhbtZ8q',
    'Empleado de Prueba',
    'empleado@es21plus.local',
    'employee',
    1
);

-- ─────────────────────────────────────────────────────────────────────────────
-- Fase 11: Compatibilidad por modelo de moto
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS modelos_moto (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    marca      VARCHAR(100) NOT NULL,
    modelo     VARCHAR(100) NOT NULL,
    anio_desde YEAR         NOT NULL,
    anio_hasta YEAR         NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS compatibilidades (
    producto_id INT          NOT NULL,
    modelo_id   INT          NOT NULL,
    notas       VARCHAR(255),
    PRIMARY KEY (producto_id, modelo_id),
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (modelo_id)   REFERENCES modelos_moto(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- Multi-tenant: negocios, empleados, contacto, actividad, anuncios
-- (originadas en database/migrations/superadmin_panel.sql)
-- ─────────────────────────────────────────────────────────────────────────────
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

CREATE TABLE IF NOT EXISTS employees (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    business_id INT           NOT NULL,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('admin','employee') DEFAULT 'employee',
    is_active   TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
    id           INT           AUTO_INCREMENT PRIMARY KEY,
    business_id  INT,
    sender_name  VARCHAR(100)  NOT NULL,
    sender_email VARCHAR(150)  NOT NULL,
    subject      VARCHAR(200),
    message      TEXT          NOT NULL,
    is_read      TINYINT(1)    DEFAULT 0,
    replied_at   TIMESTAMP     NULL,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    employee_id INT,
    action      VARCHAR(200)  NOT NULL,
    entity      VARCHAR(100),
    entity_id   INT,
    ip_address  VARCHAR(45),
    metadata    JSON          NULL,
    is_critical TINYINT(1)    DEFAULT 0,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcements (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200)  NOT NULL,
    body       TEXT          NOT NULL,
    is_active  TINYINT(1)    DEFAULT 1,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- Seguridad: intentos de login, sesiones
-- (database/migrations/security.sql)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    email        VARCHAR(150),
    attempted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip   (ip_address),
    INDEX idx_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS session_logs (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    user_type   ENUM('superadmin','employee') NOT NULL,
    user_id     INT          NOT NULL,
    business_id INT          NULL,
    ip_address  VARCHAR(45)  NULL,
    user_agent  VARCHAR(500) NULL,
    action      ENUM('login','logout','expired') NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- Notificaciones y configuración global
-- (database/migrations/notifications.sql + settings.sql)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS superadmin_notifications (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    type       VARCHAR(100) NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body       TEXT,
    link       VARCHAR(255),
    is_read    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_read    (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    value       TEXT,
    type        ENUM('string','integer','boolean','json') DEFAULT 'string',
    description VARCHAR(255),
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (setting_key, value, type, description) VALUES
('app_name',            'es21plus', 'string',  'Nombre de la aplicación'),
('registration_open',   '1',        'boolean', 'Permitir registro público'),
('maintenance_mode',    '0',        'boolean', 'Modo mantenimiento'),
('maintenance_message', '',         'string',  'Mensaje de mantenimiento'),
('default_plan',        'free',     'string',  'Plan por defecto en registro'),
('trial_days',          '14',       'integer', 'Días de prueba al registrarse'),
('max_file_upload_mb',  '5',        'integer', 'Tamaño máximo de subida en MB'),
('superadmin_email',    '',         'string',  'Email de notificaciones'),
('smtp_host',           '',         'string',  'Servidor SMTP'),
('smtp_port',           '587',      'integer', 'Puerto SMTP'),
('smtp_user',           '',         'string',  'Usuario SMTP'),
('smtp_password',       '',         'string',  'Contraseña SMTP cifrada'),
('smtp_from_name',      'es21plus', 'string',  'Nombre remitente de correos')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ─────────────────────────────────────────────────────────────────────────────
-- Tickets de soporte, Facturación y Planes
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS support_tickets (
    id             INT          AUTO_INCREMENT PRIMARY KEY,
    business_id    INT          NOT NULL,
    employee_id    INT          NULL,
    ticket_number  VARCHAR(20)  NOT NULL UNIQUE,
    subject        VARCHAR(255) NOT NULL,
    status         ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    priority       ENUM('low','normal','high','urgent')           NOT NULL DEFAULT 'normal',
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at      TIMESTAMP    NULL DEFAULT NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_messages (
    id           INT         AUTO_INCREMENT PRIMARY KEY,
    ticket_id    INT         NOT NULL,
    sender_type  ENUM('business','superadmin') NOT NULL,
    sender_id    INT         NOT NULL,
    message      TEXT        NOT NULL,
    created_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_attachments (
    id            INT         AUTO_INCREMENT PRIMARY KEY,
    message_id    INT         NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    size          INT         NOT NULL DEFAULT 0,
    uploaded_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plan_prices (
    id            INT              AUTO_INCREMENT PRIMARY KEY,
    plan          ENUM('free','basic','pro') NOT NULL UNIQUE,
    monthly_price DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
    annual_price  DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
    features      JSON,
    updated_at    TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
    id             INT           AUTO_INCREMENT PRIMARY KEY,
    business_id    INT           NOT NULL,
    invoice_number VARCHAR(20)   NOT NULL UNIQUE,
    amount         DECIMAL(10,2) NOT NULL,
    status         ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    period_start   DATE          NULL,
    period_end     DATE          NULL,
    notes          TEXT          NULL,
    paid_at        TIMESTAMP     NULL DEFAULT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- Fase: Onboarding, Campañas de email, Personalización por empresa
-- @author Carlos Vico
-- ─────────────────────────────────────────────────────────────────────────────

-- Columna onboarding_completed en businesses
SET @col_onb = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'businesses' AND COLUMN_NAME = 'onboarding_completed');
SET @sql_onb = IF(@col_onb = 0, 'ALTER TABLE businesses ADD COLUMN onboarding_completed TINYINT(1) DEFAULT 0 AFTER is_active', 'SELECT 1');
PREPARE stmt FROM @sql_onb; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Columna theme_color en businesses
SET @col_tc = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'businesses' AND COLUMN_NAME = 'theme_color');
SET @sql_tc = IF(@col_tc = 0, "ALTER TABLE businesses ADD COLUMN theme_color VARCHAR(7) DEFAULT '#4F46E5' AFTER logo_path", 'SELECT 1');
PREPARE stmt FROM @sql_tc; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Columna custom_domain en businesses
SET @col_cd = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'businesses' AND COLUMN_NAME = 'custom_domain');
SET @sql_cd = IF(@col_cd = 0, 'ALTER TABLE businesses ADD COLUMN custom_domain VARCHAR(255) NULL AFTER slug', 'SELECT 1');
PREPARE stmt FROM @sql_cd; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Columna last_login en employees
SET @col_ll = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'last_login');
SET @sql_ll = IF(@col_ll = 0, 'ALTER TABLE employees ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql_ll; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tabla campañas de email masivo
CREATE TABLE IF NOT EXISTS email_campaigns (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Tabla: proveedores (Fase 12)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS proveedores (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(150) NOT NULL,
    contacto   VARCHAR(100),
    email      VARCHAR(150),
    telefono   VARCHAR(30),
    web        VARCHAR(500),
    notas      TEXT,
    activo     TINYINT(1)   DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Añadir proveedor_id FK a productos (Fase 12)
-- Usando prepared statement condicional para idempotencia en MySQL 8.0
SET @col_prov = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'proveedor_id');
SET @sql_prov = IF(@col_prov = 0,
    'ALTER TABLE productos ADD COLUMN proveedor_id INT NULL, ADD CONSTRAINT fk_productos_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt_prov FROM @sql_prov; EXECUTE stmt_prov; DEALLOCATE PREPARE stmt_prov;

-- -------------------------------------------------------------
-- Tablas: pedidos y pedidos_lineas (Fase 13)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedidos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id  INT  NULL,
    estado        ENUM('borrador','enviado','recibido','cancelado') DEFAULT 'borrador',
    notas         TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enviado_at    TIMESTAMP NULL DEFAULT NULL,
    recibido_at   TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_lineas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id   INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad    INT NOT NULL,
    precio_unit DECIMAL(10,2) NULL,
    FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
