-- Migración: marcas como entidad + campos dimensiones en productos
-- @author Carlitos6712
SET NAMES utf8mb4;

-- ── Columnas de dimensiones ────────────────────────────────────────────────────
ALTER TABLE productos
  ADD COLUMN peso      INT          NULL AFTER ubicacion,
  ADD COLUMN capacidad INT          NULL AFTER peso,
  ADD COLUMN longitud  INT          NULL AFTER capacidad,
  ADD COLUMN anchura   INT          NULL AFTER longitud,
  ADD COLUMN diametro  DECIMAL(6,2) NULL AFTER anchura;

-- ── Tabla marcas ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS marcas (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  descripcion TEXT,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Poblar marcas desde los valores distintos ya existentes en productos
INSERT INTO marcas (nombre)
SELECT DISTINCT marca FROM productos
WHERE marca IS NOT NULL AND marca <> ''
ORDER BY marca;

-- ── FK marca_id en productos ────────────────────────────────────────────────────
ALTER TABLE productos ADD COLUMN marca_id INT NULL AFTER categoria_id;

UPDATE productos p
  JOIN marcas m ON p.marca = m.nombre
  SET p.marca_id = m.id;

ALTER TABLE productos
  ADD CONSTRAINT fk_productos_marca FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE SET NULL;

-- ── Seed de dimensiones (solo productos donde aplica por nombre) ────────────────
-- Aceites 1 L
UPDATE productos SET capacidad = 1000 WHERE id IN (5, 55);
-- Aceites 4 L
UPDATE productos SET capacidad = 4000 WHERE id IN (53, 54);
-- Líquidos de frenos 500 ml
UPDATE productos SET capacidad = 500  WHERE id IN (43, 44);
-- Lubricantes cadena 400 ml
UPDATE productos SET capacidad = 400  WHERE id IN (75, 76);
-- Discos de freno (diámetro en mm)
UPDATE productos SET diametro = 310 WHERE id = 2;
UPDATE productos SET diametro = 320 WHERE id = 41;
UPDATE productos SET diametro = 240 WHERE id = 42;
-- Bujías (rosca M14 → diámetro 14 mm)
UPDATE productos SET diametro = 14 WHERE id IN (4, 56, 57);
-- Baterías (peso g, longitud mm, anchura mm)
UPDATE productos SET peso = 2400, longitud = 150, anchura = 87 WHERE id = 9;
UPDATE productos SET peso = 2700, longitud = 150, anchura = 87 WHERE id = 77;
UPDATE productos SET peso = 3800, longitud = 150, anchura = 87 WHERE id = 78;
-- Pinza freno Brembo P4
UPDATE productos SET peso = 740 WHERE id = 46;
-- Manillar Renthal Fatbar
UPDATE productos SET longitud = 800, diametro = 28, peso = 580 WHERE id = 96;
-- Cable freno delantero 120 cm
UPDATE productos SET longitud = 1200 WHERE id = 45;
-- Asiento Saddlemen
UPDATE productos SET peso = 1200 WHERE id = 105;
-- Filtro aire Twin Air (longitud × anchura en mm)
UPDATE productos SET longitud = 420, anchura = 340 WHERE id = 52;
-- Puños Oxford (longitud 130 mm, diámetro interior 22 mm)
UPDATE productos SET longitud = 130, diametro = 22 WHERE id IN (97, 98);
-- Manillar Renthal (anchura = longitud ya asignada)
UPDATE productos SET anchura = 800 WHERE id = 96;
-- Cadena DID 520 VX3 Gold 120 eslabones → 120 × 15,875 mm ≈ 1905 mm
UPDATE productos SET longitud = 1905 WHERE id = 6;
-- Cadena DID 520 ERV3 110 eslabones → 110 × 15,875 ≈ 1746 mm
UPDATE productos SET longitud = 1746 WHERE id = 65;
-- Cadena RK 525 GXW 110 eslabones → 110 × 15,875 ≈ 1746 mm
UPDATE productos SET longitud = 1746 WHERE id = 66;
-- Correa distribución Gates (longitud típica CBF 125)
UPDATE productos SET longitud = 805 WHERE id = 59;
-- Latiguillos freno (longitud aprox. 380 mm)
UPDATE productos SET longitud = 380 WHERE id = 48;

SELECT CONCAT('Marcas creadas: ', COUNT(*))            AS marcas      FROM marcas;
SELECT CONCAT('Productos con marca_id: ', COUNT(*))    AS vinculados  FROM productos WHERE marca_id IS NOT NULL;
SELECT CONCAT('Productos con dimensiones: ', COUNT(*)) AS dimensiones FROM productos WHERE peso IS NOT NULL OR capacidad IS NOT NULL OR longitud IS NOT NULL OR diametro IS NOT NULL;
