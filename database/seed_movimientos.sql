-- =============================================================
-- Movimientos de ejemplo – es21plus
-- Historial de entradas y salidas para probar la funcionalidad
--
-- Fechas relativas a CURDATE() → siempre visibles en el gráfico
-- de los últimos 30 días sin importar cuándo se ejecute.
--
-- Ejecutar (PowerShell):
--   Get-Content database/seed_movimientos.sql | docker exec -i inventario_motos_db bash -c "MYSQL_PWD=luigi21plus mysql -uroot --default-character-set=utf8mb4 inventario_motos"
--
-- @author Carlos Vico
-- @author miguelrechefdez
-- =============================================================

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- Entradas (aprovisionamiento) – días 28..15 atrás
-- ─────────────────────────────────────────────────────────────
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 20, 'Pedido proveedor Brembo – factura #2026-001', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 28 DAY) FROM productos WHERE codigo_ref = 'FRE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 10, 'Pedido proveedor Galfer – factura #2026-002', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 27 DAY) FROM productos WHERE codigo_ref = 'FRE-005';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 40, 'Reposición mensual filtros Honda', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 25 DAY) FROM productos WHERE codigo_ref = 'MOT-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 30, 'Reposición mensual bujías NGK', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 25 DAY) FROM productos WHERE codigo_ref = 'MOT-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 15, 'Pedido aceite Motul – temporada primavera', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 22 DAY) FROM productos WHERE codigo_ref = 'MOT-003';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 12, 'Reposición kits cadena DID Gold', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 20 DAY) FROM productos WHERE codigo_ref = 'TRA-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 10, 'Stock inicial baterías Yuasa YTZ10S', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 19 DAY) FROM productos WHERE codigo_ref = 'ELE-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 25, 'Pedido bombillas Osram Night Breaker', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 18 DAY) FROM productos WHERE codigo_ref = 'ELE-007';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 5, 'Pedido carenados Yamaha R6 temporada', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 17 DAY) FROM productos WHERE codigo_ref = 'CAR-001';

-- ─────────────────────────────────────────────────────────────
-- Salidas (ventas / reparaciones) – días 16..8 atrás
-- ─────────────────────────────────────────────────────────────
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 2, 'Venta cliente – revisión frenos Yamaha R1', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 16 DAY) FROM productos WHERE codigo_ref = 'FRE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 1, 'Reparación taller – cambio disco delantero ZX-6R', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 15 DAY) FROM productos WHERE codigo_ref = 'FRE-005';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 5, 'Mantenimiento revisión 10.000km – 5 motos', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 14 DAY) FROM productos WHERE codigo_ref = 'MOT-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 8, 'Venta mostrador bujías sueltas', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 13 DAY) FROM productos WHERE codigo_ref = 'MOT-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 4, 'Cambios aceite taller – semana del 7', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 12 DAY) FROM productos WHERE codigo_ref = 'MOT-003';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 3, 'Venta kit transmisión – Kawasaki ZX636', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 11 DAY) FROM productos WHERE codigo_ref = 'TRA-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 2, 'Venta piñones sueltos mostrador', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 10 DAY) FROM productos WHERE codigo_ref = 'TRA-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 1, 'Avería eléctrica Honda CB600 – regulador quemado', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 9 DAY) FROM productos WHERE codigo_ref = 'ELE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 2, 'Venta baterías – cliente flota scooters', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 8 DAY) FROM productos WHERE codigo_ref = 'ELE-002';

-- ─────────────────────────────────────────────────────────────
-- Segunda reposición (stock bajo) – días 7..5 atrás
-- ─────────────────────────────────────────────────────────────
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 15, 'Reposición urgente – stock mínimo superado', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 7 DAY) FROM productos WHERE codigo_ref = 'MOT-003';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 8, 'Reposición reguladores – proveedor Electrosport', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 6 DAY) FROM productos WHERE codigo_ref = 'ELE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 6, 'Reposición carenados temporada primavera', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 5 DAY) FROM productos WHERE codigo_ref = 'CAR-001';

-- ─────────────────────────────────────────────────────────────
-- Movimientos recientes – días 4..0 (esta semana)
-- ─────────────────────────────────────────────────────────────
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 3, 'Revisión anual flota empresa – 3 motos', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 4 DAY) FROM productos WHERE codigo_ref = 'MOT-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 2, 'Cambio pastillas cliente habitual', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 3 DAY) FROM productos WHERE codigo_ref = 'FRE-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 20, 'Pedido trimestral – proveedor NGK España', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 2 DAY) FROM productos WHERE codigo_ref = 'MOT-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 1, 'Reparación transmisión – Yamaha MT-07', 'miguelrechefdez',
       DATE_SUB(CURDATE(), INTERVAL 2 DAY) FROM productos WHERE codigo_ref = 'TRA-001';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 4, 'Cambio bombillas revisión ITV – 4 motos', 'Carlos Vico',
       DATE_SUB(CURDATE(), INTERVAL 1 DAY) FROM productos WHERE codigo_ref = 'ELE-007';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 6, 'Venta aceite temporada – mostrador', 'Carlos Vico',
       CURDATE() FROM productos WHERE codigo_ref = 'MOT-003';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'entrada', 10, 'Reposición piñones – pedido proveedor JT', 'miguelrechefdez',
       CURDATE() FROM productos WHERE codigo_ref = 'TRA-002';

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
SELECT id, 'salida', 3, 'Cambio piñones desgastados – 3 motos revisión', 'Carlos Vico',
       CURDATE() FROM productos WHERE codigo_ref = 'TRA-002';
