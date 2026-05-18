-- =============================================================
-- Migración: Multi-tenancy — Índices, FKs y business_id NOT NULL
-- @author   Carlos Vico
-- @version  1.0.0
-- Ejecutar UNA sola vez DESPUÉS de superadmin_panel.sql
-- =============================================================

SET NAMES utf8mb4;
USE inventario_motos;

-- -------------------------------------------------------------
-- Índices para filtrado rápido por business_id
-- -------------------------------------------------------------
ALTER TABLE productos     ADD INDEX IF NOT EXISTS idx_biz_productos    (business_id);
ALTER TABLE categorias    ADD INDEX IF NOT EXISTS idx_biz_categorias   (business_id);
ALTER TABLE movimientos   ADD INDEX IF NOT EXISTS idx_biz_movimientos  (business_id);
ALTER TABLE alertas_stock ADD INDEX IF NOT EXISTS idx_biz_alertas      (business_id);

-- -------------------------------------------------------------
-- Clave foránea hacia businesses (ON DELETE CASCADE)
-- Sólo si no existen ya
-- -------------------------------------------------------------
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos'
    AND CONSTRAINT_NAME = 'fk_productos_business');
SET @s = IF(@has = 0,
    'ALTER TABLE productos ADD CONSTRAINT fk_productos_business
     FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _fk FROM @s; EXECUTE _fk; DEALLOCATE PREPARE _fk;

SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias'
    AND CONSTRAINT_NAME = 'fk_categorias_business');
SET @s = IF(@has = 0,
    'ALTER TABLE categorias ADD CONSTRAINT fk_categorias_business
     FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _fk FROM @s; EXECUTE _fk; DEALLOCATE PREPARE _fk;

SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos'
    AND CONSTRAINT_NAME = 'fk_movimientos_business');
SET @s = IF(@has = 0,
    'ALTER TABLE movimientos ADD CONSTRAINT fk_movimientos_business
     FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _fk FROM @s; EXECUTE _fk; DEALLOCATE PREPARE _fk;

SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alertas_stock'
    AND CONSTRAINT_NAME = 'fk_alertas_stock_business');
SET @s = IF(@has = 0,
    'ALTER TABLE alertas_stock ADD CONSTRAINT fk_alertas_stock_business
     FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _fk FROM @s; EXECUTE _fk; DEALLOCATE PREPARE _fk;
