-- =============================================================
-- SEED DEMO – Taller Mecánico de Motos
-- Autor: Carlos Vico
-- Cubre: categorias, marcas, proveedores, productos,
--        movimientos, pedidos, pedidos_lineas, alertas_stock, auditoria
-- =============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- -------------------------------------------------------------
-- 1. CATEGORÍAS (añadir las que faltan)
-- -------------------------------------------------------------
INSERT IGNORE INTO categorias (id, nombre, descripcion, business_id) VALUES
(1,  'Frenos',        'Pastillas, discos, latiguillos y líquido de frenos', 1),
(2,  'Motor',         'Filtros, aceites, juntas, pistones y culata',         1),
(3,  'Transmisión',   'Cadenas, piñones, coronas y kits de arrastre',        1),
(4,  'Electricidad',  'Reguladores, bombillas, baterías y fusibles',         1),
(5,  'Carrocería',    'Carenados, tapas, espejos y manetas',                 1),
(6,  'Suspensión',    'Horquillas, amortiguadores, retenes y aceite',        1),
(7,  'Neumáticos',    'Cubiertas, cámaras, válvulas y equilibrado',          1),
(8,  'Consumibles',   'Sprays, limpiacontactos, desengrasantes',             1),
(9,  'Herramientas',  'Llaves dinamométricas, extractores, medidores',       1),
(10, 'Accesorios',    'Candados, cobertores, cargadores y equipamiento',     1);

-- -------------------------------------------------------------
-- 2. PROVEEDORES
-- -------------------------------------------------------------
INSERT INTO proveedores (id, nombre, contacto, email, telefono, web, notas, activo) VALUES
(1, 'MotoDistrib S.L.',     'Ramón Pérez',    'ramon@motodistrib.es',   '963 111 222', 'https://motodistrib.es',      'Distribuidor principal zona Levante. Pedido mínimo 150 €.',   1),
(2, 'BrakeParts Europa',    'Sonia Llopis',   'sonia@brakeparts.eu',    '912 333 444', 'https://brakeparts.eu',       'Especialistas en frenos Brembo y EBC. Envío 24h.',            1),
(3, 'EléctricaMoto Pro',    'Jorge Mateu',    'jorge@electricamoto.es', '966 555 666', 'https://electricamotopro.es', 'Repuestos eléctricos y led. Factura mínima 80 €.',            1),
(4, 'NeumáticosTop',        'Ana Ferrer',     'ana@neumaticostop.com',  '960 777 888', 'https://neumaticostop.com',   'Envío gratis >200 €. Michelin, Pirelli, Dunlop.',             1),
(5, 'OilTech Ibérica',      'Luis Blanes',    'luis@oiltech.es',        '965 999 000', 'https://oiltechiberia.es',    'Motul, Castrol, Repsol. Palés desde 24 €/L.',                 1),
(6, 'Recambios Global 2000','Marta Navarro',  'marta@rg2000.com',       '911 222 333', 'https://rg2000.com',          'Catálogo amplio. Proveedor de respaldo para genéricos.',      0);

-- Vincular proveedor_id en productos existentes
UPDATE productos SET proveedor_id = 2 WHERE categoria_id = 1 AND proveedor_id IS NULL;
UPDATE productos SET proveedor_id = 1 WHERE categoria_id = 2 AND proveedor_id IS NULL;
UPDATE productos SET proveedor_id = 1 WHERE categoria_id = 3 AND proveedor_id IS NULL;
UPDATE productos SET proveedor_id = 3 WHERE categoria_id = 4 AND proveedor_id IS NULL;
UPDATE productos SET proveedor_id = 5 WHERE proveedor_id IS NULL;

-- -------------------------------------------------------------
-- 3. PRODUCTOS DEMO (uno por categoría sin datos aún)
-- -------------------------------------------------------------
INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, codigo_ref, categoria_id, marca_id, proveedor_id, ubicacion, business_id) VALUES
-- Transmisión
('Kit cadena DID 520 DZ2 Gold 116 eslabones',  'Kit completo cadena + piñón + corona para naked 600cc', 89.90,  6,  2, 'DID-520DZ2-116', 3,  8,  1, 'A3-01', 1),
('Piñón JT 16T Z650 Kawasaki',                 'Piñón de ataque JT Sprockets acero', 18.50, 12, 4, 'JT-F308-16',     3, 17,  1, 'A3-02', 1),
('Corona JT 43T aluminio anodizado 520',        'Ligera -200g respecto OEM', 45.00, 8,   3, 'JT-R1304-43',    3, 17,  1, 'A3-03', 1),
-- Suspensión
('Retenes horquilla 43mm Kayaba set 2u',        'Para Yamaha MT-09 / R9 2024-25', 22.00, 5,  2, 'KB-4300-SEAL',   6,  1,  1, 'B1-01', 1),
('Aceite horquilla Motul Expert 10W 500ml',     'Aceite mineral para uso general', 11.50, 14, 5, 'MTL-FORK10W',    6, 21,  5, 'B1-02', 1),
('Amortiguador trasero YSS G-Sport Honda CB500','-30/+30 precarga, válvula rebote',  149.00, 2, 1, 'YSS-GSP-CB500', 6,  1,  1, 'B1-03', 1),
-- Neumáticos
('Michelin Road 6 120/70 ZR17 F',              'Delantera sport-turismo', 115.00, 4,  2, 'MCH-R6-12070F',  7,  1,  4, 'C1-01', 1),
('Michelin Road 6 180/55 ZR17 R',              'Trasera sport-turismo',   135.00, 4,  2, 'MCH-R6-18055R',  7,  1,  4, 'C1-02', 1),
('Pirelli Angel GT II 120/70 ZR17',            'GT delantera larga duración', 109.00, 3, 2, 'PIR-AGT2-12070', 7,  1,  4, 'C1-03', 1),
-- Consumibles
('Spray limpiabicadenas Motul Chain Clean 400ml','Biodegradable. No daña O-ring', 9.90, 20, 8, 'MTL-CHAIN-CLEAN', 8, 21, 5, 'D1-01', 1),
('WD-40 Specialist Moto 400ml',                 'Lubricante cadena clima húmedo', 8.50, 18, 8, 'WD40-MOTO-400',  8,  1,  1, 'D1-02', 1),
('Spray limpiador contactos CRC 200ml',         'Para bornes, conectores, interruptores', 7.20, 9, 5, 'CRC-CONTACT-200', 8, 3, 3, 'D1-03', 1),
-- Herramientas
('Llave dinamométrica 1/2" 40-200 Nm BGS',     'Con maletín y adaptadores', 79.00, 3,  1, 'BGS-DYN-200NM',  9,  1,  1, 'E1-01', 1),
('Extractor embrague universal 3 garras',       'Acero cromado vanadio', 24.00, 4,   2, 'EXT-3G-UNIV',    9,  1,  1, 'E1-02', 1),
('Medidor compresión cilindros M14/M18',        'Rango 0-300 PSI', 34.00, 2,  1, 'MED-COMP-300',   9,  1,  1, 'E1-03', 1),
-- Accesorios
('Candado disco Oxford Nemesis 14mm',           'Con alarma 120dB, avisador de traslado', 45.00, 7,  3, 'OXF-NEM-14',    10, 24, 1, 'F1-01', 1),
('Cargador batería Optimate 4 Dual',            'Para Li y AGM, con prueba de alternador', 59.00, 5, 2, 'OPT-4DUAL',     10, 33, 3, 'F1-02', 1),
('Cobertor moto interior Oxford Stormex L',     'Agua, polvo y arañazos', 38.00, 6,   2, 'OXF-STORMEX-L', 10, 24, 1, 'F1-03', 1);

-- -------------------------------------------------------------
-- 4. PRODUCTOS CON STOCK BAJO (para probar alertas dashboard)
-- -------------------------------------------------------------
INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, codigo_ref, categoria_id, marca_id, proveedor_id, business_id) VALUES
('Batería Yuasa YTZ14S 12V 11.2Ah',       'AGM sin mantenimiento. Honda CB650R', 89.00,  1, 3, 'YUA-YTZ14S',    4, 36, 3, 1),
('Retén dirección superior BMW F800GS',    'OEM BMW 31 42 7 683 870',            12.50,  0, 2, 'BMW-F800GS-RET', 6, 1,  1, 1),
('Junta culata Athena Honda CBR600RR 07',  'Con capa de grafito reforzado',       34.00,  1, 3, 'ATH-P400210850132', 2, 2, 1, 1);

-- -------------------------------------------------------------
-- 5. MOVIMIENTOS (entradas y salidas realistas)
-- -------------------------------------------------------------
-- Obtener IDs recién insertados para las primeras referencias
SET @kit_cadena   = (SELECT id FROM productos WHERE codigo_ref = 'DID-520DZ2-116' LIMIT 1);
SET @michelin_f   = (SELECT id FROM productos WHERE codigo_ref = 'MCH-R6-12070F'  LIMIT 1);
SET @michelin_r   = (SELECT id FROM productos WHERE codigo_ref = 'MCH-R6-18055R'  LIMIT 1);
SET @bateria      = (SELECT id FROM productos WHERE codigo_ref = 'YUA-YTZ14S'     LIMIT 1);
SET @candado      = (SELECT id FROM productos WHERE codigo_ref = 'OXF-NEM-14'     LIMIT 1);
SET @aceite_fork  = (SELECT id FROM productos WHERE codigo_ref = 'MTL-FORK10W'    LIMIT 1);
SET @wd40         = (SELECT id FROM productos WHERE codigo_ref = 'WD40-MOTO-400'  LIMIT 1);
SET @optimate     = (SELECT id FROM productos WHERE codigo_ref = 'OPT-4DUAL'      LIMIT 1);

INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha, business_id) VALUES
-- Entradas (reposición stock)
(@kit_cadena,  'entrada', 10, 'Reposición mensual – albaran MDB-2024-0315', 'admin', '2026-04-01 09:00:00', 1),
(@michelin_f,  'entrada',  6, 'Pedido #P-001 recibido completo',            'admin', '2026-04-02 10:30:00', 1),
(@michelin_r,  'entrada',  6, 'Pedido #P-001 recibido completo',            'admin', '2026-04-02 10:31:00', 1),
(@candado,     'entrada',  8, 'Compra directa feria Motoh! 2026',           'admin', '2026-04-05 11:00:00', 1),
(@optimate,    'entrada',  5, 'Nuevo producto catálogo primavera',           'admin', '2026-04-06 09:15:00', 1),
(@aceite_fork, 'entrada', 10, 'Reposición stock mínimo',                    'admin', '2026-04-08 08:45:00', 1),
(@wd40,        'entrada', 20, 'Oferta lote 20u proveedor MotoDistrib',       'admin', '2026-04-10 10:00:00', 1),
(@bateria,     'entrada',  4, 'Pedido urgente temporada',                   'admin', '2026-04-12 14:00:00', 1),
-- Salidas (ventas / uso en taller)
(@kit_cadena,  'salida',   2, 'Montaje Kawasaki Z650 matrícula 1234-ABC',   'admin', '2026-04-15 10:00:00', 1),
(@michelin_f,  'salida',   1, 'Venta mostrador cliente Pedro García',       'admin', '2026-04-16 11:30:00', 1),
(@michelin_r,  'salida',   1, 'Venta mostrador cliente Pedro García',       'admin', '2026-04-16 11:31:00', 1),
(@candado,     'salida',   1, 'Venta online pedido #WEB-0087',               'admin', '2026-04-18 16:00:00', 1),
(@wd40,        'salida',   2, 'Uso taller – cadenas semana 16',              'admin', '2026-04-20 09:00:00', 1),
(@bateria,     'salida',   3, 'Sustitución baterías inicio temporada',       'admin', '2026-04-22 10:00:00', 1),
(@aceite_fork, 'salida',   3, 'Revisión horquillas Honda CBR500 x3',        'admin', '2026-04-25 11:00:00', 1),
(@optimate,    'salida',   2, 'Venta mostrador – temporada verano',          'admin', '2026-04-28 12:00:00', 1),
-- Mayo 2026
(@michelin_f,  'salida',   1, 'Venta mostrador cliente Laura Tomás',        'admin', '2026-05-03 10:00:00', 1),
(@kit_cadena,  'salida',   1, 'Montaje Yamaha MT-07 – OT-2026-0512',        'admin', '2026-05-10 09:30:00', 1),
(@wd40,        'salida',   3, 'Uso taller semana 19',                        'admin', '2026-05-12 08:00:00', 1),
(@candado,     'salida',   2, 'Venta pack seguridad x2 cliente empresa',     'admin', '2026-05-15 15:00:00', 1),
(@aceite_fork, 'salida',   3, 'Revisiones programadas semana 20',            'admin', '2026-05-20 10:00:00', 1),
(@kit_cadena,  'entrada', 10, 'Reposición – pedido #P-005',                  'admin', '2026-05-22 09:00:00', 1),
(@michelin_f,  'entrada',  4, 'Pedido #P-006 parcial',                       'admin', '2026-05-22 09:05:00', 1),
(@michelin_r,  'entrada',  4, 'Pedido #P-006 parcial',                       'admin', '2026-05-22 09:06:00', 1);

-- Movimientos para productos existentes (IDs 1-12)
INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha, business_id) VALUES
(1,  'entrada', 20, 'Reposición trimestral Brembo',              'admin', '2026-03-01 09:00:00', 1),
(1,  'salida',   5, 'Montaje BMW R1250GS – OT-0423',            'admin', '2026-03-15 10:00:00', 1),
(1,  'salida',   3, 'Venta mostrador',                           'admin', '2026-04-10 11:00:00', 1),
(3,  'entrada', 40, 'Lote HiFlo Honda filtros',                  'admin', '2026-03-05 08:00:00', 1),
(3,  'salida',  10, 'Revisiones Honda CBR semana 11',            'admin', '2026-03-20 09:00:00', 1),
(5,  'entrada', 12, 'Compra Motul 300V lote primavera',          'admin', '2026-04-01 09:00:00', 1),
(5,  'salida',   4, 'Cambios aceite superbike x4',               'admin', '2026-04-18 10:00:00', 1),
(5,  'salida',   5, 'Cambios aceite superbike x5',               'admin', '2026-05-05 10:00:00', 1),
(8,  'entrada',  5, 'Reposición eléctrica – reguladores',        'admin', '2026-04-15 09:00:00', 1),
(8,  'salida',   3, 'Montajes varios semana 17',                 'admin', '2026-04-28 11:00:00', 1),
(11, 'entrada', 15, 'Kit LED H4 temporada verano',               'admin', '2026-04-20 09:00:00', 1),
(11, 'salida',   5, 'Ventas mostrador',                          'admin', '2026-05-02 10:00:00', 1);

-- -------------------------------------------------------------
-- 6. PEDIDOS A PROVEEDORES
-- -------------------------------------------------------------
INSERT INTO pedidos (id, proveedor_id, estado, notas, created_at, enviado_at, recibido_at) VALUES
(1, 1, 'recibido',  'Pedido mensual abril. Cadenas + piñones.', '2026-04-01 08:00:00', '2026-04-01 09:00:00', '2026-04-03 11:00:00'),
(2, 4, 'recibido',  'Neumáticos Michelin Road 6 temporada.',     '2026-04-01 08:30:00', '2026-04-01 09:30:00', '2026-04-02 10:00:00'),
(3, 2, 'enviado',   'Pastillas Brembo y discos Galfer.',         '2026-05-10 09:00:00', '2026-05-10 10:00:00', NULL),
(4, 5, 'borrador',  'Revisión aceites para junio.',              '2026-05-20 11:00:00', NULL,                  NULL),
(5, 1, 'enviado',   'Reposición cadenas DID y kits.',            '2026-05-21 09:00:00', '2026-05-21 10:00:00', NULL),
(6, 3, 'cancelado', 'Batería cancelada – cliente desistió.',     '2026-05-15 14:00:00', NULL,                  NULL),
(7, 4, 'borrador',  'Pirelli Angel GT II para agosto.',          '2026-05-24 10:00:00', NULL,                  NULL);

-- Líneas de pedido
INSERT INTO pedidos_lineas (pedido_id, producto_id, cantidad, precio_unit) VALUES
-- Pedido 1 – MotoDistrib recibido
(1, @kit_cadena, 10, 72.50),
(1, (SELECT id FROM productos WHERE codigo_ref='JT-F308-16'  LIMIT 1), 10, 15.20),
(1, (SELECT id FROM productos WHERE codigo_ref='JT-R1304-43' LIMIT 1),  8, 38.00),
-- Pedido 2 – Michelin recibido
(2, @michelin_f, 6, 98.00),
(2, @michelin_r, 6, 115.00),
-- Pedido 3 – BrakeParts enviado
(3, 1,  10, 12.80),
(3, (SELECT id FROM productos WHERE codigo_ref='ATH-P400210850132' LIMIT 1), 5, 28.50),
-- Pedido 4 – OilTech borrador
(4, 5,  12, 31.00),
(4, @aceite_fork, 10, 9.20),
-- Pedido 5 – MotoDistrib enviado
(5, @kit_cadena, 10, 72.50),
(5, (SELECT id FROM productos WHERE codigo_ref='PIR-AGT2-12070' LIMIT 1), 4, 91.00),
-- Pedido 7 – Pirelli borrador
(7, (SELECT id FROM productos WHERE codigo_ref='PIR-AGT2-12070' LIMIT 1), 8, 91.00),
(7, @michelin_r, 6, 115.00);

-- -------------------------------------------------------------
-- 7. ALERTAS DE STOCK (para productos críticos)
-- -------------------------------------------------------------
INSERT INTO alertas_stock (producto_id, enviada_at, stock_al_enviar) VALUES
(@bateria,     '2026-04-23 10:05:00', 1),
((SELECT id FROM productos WHERE codigo_ref='BMW-F800GS-RET' LIMIT 1), '2026-05-01 09:00:00', 0),
(2,  '2026-03-01 08:00:00', 3),
(5,  '2026-05-06 10:05:00', 3),
(8,  '2026-04-29 11:05:00', 2);

-- -------------------------------------------------------------
-- 8. AUDITORÍA – registros de ejemplo
-- -------------------------------------------------------------
INSERT INTO auditoria (tabla, registro_id, accion, datos_anteriores, datos_nuevos, usuario, ip, fecha) VALUES
('productos', 1, 'actualizar',
 '{"precio":"13.50","stock":10}',
 '{"precio":"15.90","stock":15}',
 'admin', '192.168.1.10', '2026-04-10 09:00:00'),
('productos', 2, 'actualizar',
 '{"stock":7}',
 '{"stock":4}',
 'admin', '192.168.1.10', '2026-04-15 11:00:00'),
('categorias', 6, 'crear',
 NULL,
 '{"nombre":"Suspensión","descripcion":"Horquillas y amortiguadores"}',
 'admin', '192.168.1.10', '2026-04-01 08:00:00'),
('proveedores', 1, 'crear',
 NULL,
 '{"nombre":"MotoDistrib S.L.","contacto":"Ramón Pérez"}',
 'admin', '192.168.1.10', '2026-04-01 08:01:00'),
('proveedores', 6, 'actualizar',
 '{"activo":1}',
 '{"activo":0}',
 'admin', '192.168.1.11', '2026-05-05 10:30:00'),
('pedidos', 6, 'actualizar',
 '{"estado":"borrador"}',
 '{"estado":"cancelado"}',
 'admin', '192.168.1.10', '2026-05-15 14:05:00'),
('productos', 5, 'actualizar',
 '{"precio":"29.90"}',
 '{"precio":"31.50"}',
 'admin', '192.168.1.10', '2026-05-01 09:00:00');

SET foreign_key_checks = 1;

SELECT 'SEED DEMO COMPLETADO' AS resultado;
SELECT 'proveedores' AS tabla, COUNT(*) AS registros FROM proveedores
UNION SELECT 'pedidos',       COUNT(*) FROM pedidos
UNION SELECT 'pedidos_lineas',COUNT(*) FROM pedidos_lineas
UNION SELECT 'movimientos',   COUNT(*) FROM movimientos
UNION SELECT 'alertas_stock', COUNT(*) FROM alertas_stock
UNION SELECT 'auditoria',     COUNT(*) FROM auditoria
UNION SELECT 'categorias',    COUNT(*) FROM categorias
UNION SELECT 'productos',     COUNT(*) FROM productos WHERE deleted_at IS NULL;
