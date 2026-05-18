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

-- Migración: añadir alertas_email a productos (para instalaciones existentes)
ALTER TABLE productos ADD COLUMN IF NOT EXISTS alertas_email TINYINT(1) DEFAULT 1;

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
