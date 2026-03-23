-- =============================================================
-- Sistema de Inventario para Mecánico de Motos – DDL completo
-- @author  Carlos Vico
-- @version 1.1.0
-- =============================================================

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
-- Tabla: productos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
    id           INT            AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(200)   NOT NULL,
    descripcion  TEXT,
    precio       DECIMAL(10,2)  DEFAULT 0.00,
    stock        INT            DEFAULT 0,
    stock_minimo INT            DEFAULT 5,
    codigo_ref   VARCHAR(50),
    imagen       VARCHAR(255),
    categoria_id INT,
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at   TIMESTAMP      NULL DEFAULT NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
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
-- Datos iniciales de categorías
-- -------------------------------------------------------------
INSERT IGNORE INTO categorias (id, nombre, descripcion) VALUES
(1, 'Frenos',      'Pastillas, discos, cables y líquido de frenos'),
(2, 'Motor',       'Filtros, bujías, aceites y componentes de motor'),
(3, 'Transmisión', 'Cadenas, piñones, coronas y variadores'),
(4, 'Electricidad','Bombillas, baterías, reguladores y cableado'),
(5, 'Carrocería',  'Carenados, retrovisores, manillares y accesorios');

-- -------------------------------------------------------------
-- Tabla: usuarios (autenticación)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id              INT          AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    email           VARCHAR(150),
    activo          TINYINT(1)   DEFAULT 1,
    last_login      TIMESTAMP    NULL DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
INSERT IGNORE INTO usuarios (username, password_hash, nombre_completo, email)
VALUES (
    'admin',
    '$2y$12$MDmgrjjK.zTikDB2VkDiy.ZWaiWJpGWb93cPY0k8UcI0lg25ZVpRG', -- password: admin123
    'Carlos Vico',
    'admin@es21plus.local'
);
