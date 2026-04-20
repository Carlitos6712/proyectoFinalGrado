-- =============================================================
-- Movimientos de ejemplo – es21plus
-- Historial de entradas y salidas para probar la funcionalidad
--
-- Usa subconsultas por codigo_ref para no depender de IDs fijos.
--
-- Ejecutar:
--   Get-Content database/seed_movimientos.sql | docker exec -i inventario_motos_db bash -c "MYSQL_PWD=luigi21plus mysql -uroot --default-character-set=utf8mb4 inventario_motos"
--
-- @author Carlos Vico
-- @author miguelrechefdez
-- =============================================================

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- Entradas iniciales de stock (aprovisionamiento)
-- ─────────────────────────────────────────────────────────────
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 20, 'Pedido proveedor Brembo – factura #2024-001', 'Carlos Vico',
       '2025-01-10 09:15:00' FROM productos WHERE codigo_ref = 'FRE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 10, 'Pedido proveedor Galfer – factura #2024-002', 'Carlos Vico',
       '2025-01-10 09:30:00' FROM productos WHERE codigo_ref = 'FRE-005';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 40, 'Reposición mensual filtros Honda', 'Carlos Vico',
       '2025-01-12 10:00:00' FROM productos WHERE codigo_ref = 'MOT-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 30, 'Reposición mensual bujías NGK', 'Carlos Vico',
       '2025-01-12 10:15:00' FROM productos WHERE codigo_ref = 'MOT-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 15, 'Pedido aceite Motul – temporada primavera', 'Carlos Vico',
       '2025-01-15 08:45:00' FROM productos WHERE codigo_ref = 'MOT-003';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 12, 'Reposición kits cadena DID Gold', 'miguelrechefdez',
       '2025-01-18 11:00:00' FROM productos WHERE codigo_ref = 'TRA-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 10, 'Stock inicial baterías Yuasa YTZ10S', 'Carlos Vico',
       '2025-01-20 09:00:00' FROM productos WHERE codigo_ref = 'ELE-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 25, 'Pedido bombillas Osram Night Breaker', 'miguelrechefdez',
       '2025-02-01 10:30:00' FROM productos WHERE codigo_ref = 'ELE-007';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 5, 'Pedido carenados Yamaha R6 temporada', 'Carlos Vico',
       '2025-02-05 09:00:00' FROM productos WHERE codigo_ref = 'CAR-001';

-- ─────────────────────────────────────────────────────────────
-- Salidas por ventas / reparaciones taller
-- ─────────────────────────────────────────────────────────────
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 2, 'Venta cliente – revisión frenos Yamaha R1', 'Carlos Vico',
       '2025-01-20 11:00:00' FROM productos WHERE codigo_ref = 'FRE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 1, 'Reparación taller – cambio disco delantero Kawasaki ZX-6R', 'miguelrechefdez',
       '2025-01-22 14:30:00' FROM productos WHERE codigo_ref = 'FRE-005';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 5, 'Mantenimiento revisión 10.000km – 5 motos', 'Carlos Vico',
       '2025-01-25 09:00:00' FROM productos WHERE codigo_ref = 'MOT-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 8, 'Venta mostrador bujías sueltas', 'miguelrechefdez',
       '2025-01-28 16:00:00' FROM productos WHERE codigo_ref = 'MOT-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 4, 'Cambios aceite taller – semana 4 enero', 'Carlos Vico',
       '2025-01-31 12:00:00' FROM productos WHERE codigo_ref = 'MOT-003';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 3, 'Venta kit transmisión – Kawasaki ZX636', 'Carlos Vico',
       '2025-02-03 10:45:00' FROM productos WHERE codigo_ref = 'TRA-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 2, 'Venta piñones sueltos', 'miguelrechefdez',
       '2025-02-06 13:00:00' FROM productos WHERE codigo_ref = 'TRA-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 1, 'Avería eléctrica Honda CB600 – regulador quemado', 'Carlos Vico',
       '2025-02-08 11:30:00' FROM productos WHERE codigo_ref = 'ELE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 2, 'Venta baterías – cliente flota scooters', 'miguelrechefdez',
       '2025-02-10 10:00:00' FROM productos WHERE codigo_ref = 'ELE-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 4, 'Cambio bombillas revisión ITV – 4 motos', 'Carlos Vico',
       '2025-02-12 09:30:00' FROM productos WHERE codigo_ref = 'ELE-007';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 1, 'Reparación golpe lateral – carenado Yamaha R6', 'miguelrechefdez',
       '2025-02-14 15:00:00' FROM productos WHERE codigo_ref = 'CAR-001';

-- ─────────────────────────────────────────────────────────────
-- Segunda reposición (stock bajo)
-- ─────────────────────────────────────────────────────────────
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 15, 'Reposición urgente – stock mínimo superado', 'Carlos Vico',
       '2025-02-15 08:00:00' FROM productos WHERE codigo_ref = 'MOT-003';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 8, 'Pedido reposición reguladores – proveedor Electrosport', 'miguelrechefdez',
       '2025-02-16 09:00:00' FROM productos WHERE codigo_ref = 'ELE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 6, 'Reposición carenados temporada primavera', 'Carlos Vico',
       '2025-02-20 10:00:00' FROM productos WHERE codigo_ref = 'CAR-001';

-- ─────────────────────────────────────────────────────────────
-- Movimientos recientes (marzo–abril 2025)
-- ─────────────────────────────────────────────────────────────
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 3, 'Revisión anual flota empresa – 3 motos', 'Carlos Vico',
       '2025-03-05 10:00:00' FROM productos WHERE codigo_ref = 'MOT-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 2, 'Cambio pastillas cliente habitual', 'miguelrechefdez',
       '2025-03-10 11:30:00' FROM productos WHERE codigo_ref = 'FRE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 20, 'Pedido trimestral – proveedor NGK España', 'Carlos Vico',
       '2025-03-15 09:00:00' FROM productos WHERE codigo_ref = 'MOT-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 1, 'Reparación transmisión – Yamaha MT-07', 'miguelrechefdez',
       '2025-03-20 14:00:00' FROM productos WHERE codigo_ref = 'TRA-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 6, 'Venta aceite temporada primavera – mostrador', 'Carlos Vico',
       '2025-04-01 10:00:00' FROM productos WHERE codigo_ref = 'MOT-003';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 3, 'Cambio piñones desgastados – 3 motos revisión', 'miguelrechefdez',
       '2025-04-05 11:00:00' FROM productos WHERE codigo_ref = 'TRA-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 10, 'Reposición stock piñones – pedido proveedor JT', 'Carlos Vico',
       '2025-04-10 09:30:00' FROM productos WHERE codigo_ref = 'TRA-002';
