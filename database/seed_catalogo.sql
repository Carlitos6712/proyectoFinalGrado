-- =============================================================
-- Catálogo completo de productos – es21plus
-- Taller mecánico de motos · ~75 referencias reales
--
-- Categorías:
--   1 = Frenos
--   2 = Motor
--   3 = Transmisión
--   4 = Electricidad
--   5 = Carrocería
--
-- Ejecutar desde proyecto:
--   docker exec -i inventario_motos_db bash -c \
--     "MYSQL_PWD=luigi21plus mysql -uroot --default-character-set=utf8mb4 inventario_motos" \
--     < database/seed_catalogo.sql
--
-- @author Carlos Vico
-- @author miguelrechefdez
-- =============================================================

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- CATEGORÍA 1 · FRENOS
-- ─────────────────────────────────────────────────────────────
INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, codigo_ref, categoria_id) VALUES

('Pastilla freno delantera Brembo SC',
 'Pastilla sinterizada Brembo SC para uso en pista y calle. Par delantero. Compatible deportivas 600-1000cc. Alta resistencia al fade térmico.',
 38.90, 14, 5, 'FRE-003', 1),

('Pastilla freno trasera EBC FA69',
 'Pastilla orgánica EBC para freno trasero. Baja generación de polvo y ruido. Apta para uso urbano y carretera.',
 18.50, 20, 6, 'FRE-004', 1),

('Disco freno flotante Galfer 320mm Wave',
 'Disco Wave Galfer de acero inoxidable 320mm. Diseño radial anti-deformación. Compatible con pinzas Brembo y Nissin.',
 112.00,  5, 3, 'FRE-005', 1),

('Disco freno trasero Galfer 240mm',
 'Disco trasero Galfer liso 240mm. Acero inoxidable tratado térmicamente. Compatible con la mayoría de naked y trail.',
 64.50,  7, 3, 'FRE-006', 1),

('Líquido de frenos Castrol React DOT 4',
 'Líquido de frenos sintético DOT 4. Punto de ebullición seco 265°C / húmedo 165°C. Botella 500ml.',
 9.95, 25, 8, 'FRE-007', 1),

('Líquido de frenos Motul DOT 5.1',
 'Líquido de frenos no silicona DOT 5.1. Punto ebullición seco 270°C. Mayor rendimiento que DOT 4. 500ml.',
 12.80, 18, 6, 'FRE-008', 1),

('Cable freno delantero universal 120cm',
 'Cable de freno delantero trenzado 120cm. Acero galvanizado funda PVC. Extremos con niple y terminal regulable.',
 8.40, 16, 5, 'FRE-009', 1),

('Pinza freno Brembo P4 30/34 monobloc',
 'Pinza de freno monobrida Brembo P4 30/34. Aluminio anodizado negro. Attacco axial. 2 pastillas incluidas.',
 189.00,  3, 2, 'FRE-010', 1),

('Kit revisión bomba freno delantera',
 'Kit juntas y retenes para bomba de freno delantera. Incluye émbolo, muelle y tapa depósito. Universal 11mm.',
 14.20, 10, 4, 'FRE-011', 1),

('Latiguillos freno acero trenzado par',
 'Par de latiguillos de freno en acero inoxidable trenzado. Reducen la expansión del latiguillo original. 600–750mm.',
 34.90,  8, 3, 'FRE-012', 1),

-- ─────────────────────────────────────────────────────────────
-- CATEGORÍA 2 · MOTOR
-- ─────────────────────────────────────────────────────────────

('Filtro aceite HiFlo Yamaha R6/R1',
 'Filtro de aceite alta filtración HiFlo. Compatible Yamaha R6 2003-2020 y R1 2004-2015. Rosca M20x1.5.',
 8.20, 35, 10, 'MOT-004', 2),

('Filtro aceite Hiflofiltro Honda CBR',
 'Filtro aceite HiFlo para Honda CBR 600RR, CBR 1000RR y CB 600F Hornet. Rosca M20x1.5.',
 7.90, 32, 10, 'MOT-005', 2),

('Filtro aire K&N Kawasaki ZX-6R',
 'Filtro de aire reutilizable K&N para Kawasaki ZX-6R 2007-2020. Aumenta el flujo de aire 10%. Lavable.',
 54.90,  6, 3, 'MOT-006', 2),

('Filtro aire espuma Twin Air universal 340x420',
 'Filtro de espuma universal Twin Air 340x420mm. Para caja de filtro de aire. Lavable y reutilizable.',
 18.90, 12, 4, 'MOT-007', 2),

('Aceite Motul 7100 10W-40 4L',
 'Aceite motor 100% sintético Motul 7100 para transmisión común. Protección extrema en alta temperatura. 4 litros.',
 49.95,  9, 4, 'MOT-008', 2),

('Aceite Repsol Moto Racing 4T 10W-40 4L',
 'Aceite de competición Repsol 4T 10W-40 sintético. Tecnología Honda Racing. 4 litros.',
 44.90, 11, 4, 'MOT-009', 2),

('Aceite motor 2T Castrol Power 1 TTS 1L',
 'Aceite de 2 tiempos Castrol Power 1 TTS totalmente sintético. Mezcla a gasolina para moto 2T. 1 litro.',
 14.95, 20, 6, 'MOT-010', 2),

('Bujía NGK CR9EK estándar',
 'Bujía estándar NGK CR9EK. Compatible con la mayoría de motos deportivas 4T. Electrodo niquelado.',
 4.80, 45, 12, 'MOT-011', 2),

('Bujía Denso IU22 Iridium Power',
 'Bujía de iridio Denso IU22. Electrodo iridio 0.4mm. Mayor durabilidad y eficiencia de combustión.',
 11.50, 28, 8, 'MOT-012', 2),

('Kit juntas motor completo Kawasaki ZX636',
 'Kit completo de juntas para motor Kawasaki ZX636 B/C 2003-2006. Incluye junta culata, base cilindro y tapas.',
 89.00,  4, 2, 'MOT-013', 2),

('Correa distribución Gates Honda CBF 125',
 'Correa de distribución Gates para Honda CBF 125 2009-2015. 94 dientes. Reforzada con fibra de aramida.',
 22.40,  8, 3, 'MOT-014', 2),

('Filtro combustible universal 6mm',
 'Filtro de combustible en línea. Cuerpo transparente para inspección visual. Conexión 6mm. Pack de 2.',
 3.90, 40, 12, 'MOT-015', 2),

('Válvula admisión Vespa GTS 125',
 'Válvula de admisión para Vespa GTS 125 ie 2007-2016. Acero inoxidable endurecido. Diámetro cabeza 27mm.',
 19.80,  6, 3, 'MOT-016', 2),

('Empaque junta culata Honda CB500F',
 'Junta de culata Honda CB500F 2013-2018. Material multicapa metálico (MLS). Grosor 0.6mm. OEM 12251-MJW-J00.',
 34.50,  5, 2, 'MOT-017', 2),

('Termostato motor Suzuki GSX-R 600/750',
 'Termostato de apertura 71°C para Suzuki GSX-R 600 2006-2010 y GSX-R 750 2006-2010.',
 16.90,  9, 3, 'MOT-018', 2),

('Sensor temperatura agua Honda CB1000R',
 'Sensor temperatura refrigerante Honda CB1000R 2008-2017. Hilo NTC. Conector 2 pines. OEM 37870-MFN-D01.',
 28.50,  4, 2, 'MOT-019', 2),

-- ─────────────────────────────────────────────────────────────
-- CATEGORÍA 3 · TRANSMISIÓN
-- ─────────────────────────────────────────────────────────────

('Cadena DID 520 ERV3 Gold 110 eslabones',
 'Cadena de transmisión DID 520 ERV3 dorada. 110 eslabones. Retén X-ring. Resistencia de rotura 3650kg.',
 74.90,  9, 3, 'TRA-003', 3),

('Cadena RK 525 GXW 110 eslabones',
 'Cadena RK 525 GXW cadena de alta resistencia con retén XW. Incluye eslabón de unión rápida. 110 eslabones.',
 82.50,  6, 3, 'TRA-004', 3),

('Kit transmisión Yamaha MT-07 2014-2020',
 'Kit completo cadena + piñón delantero 16T + corona 45T. Yamaha MT-07 2014-2020. Cadena DID 520VX3.',
 149.00,  5, 2, 'TRA-005', 3),

('Kit transmisión Kawasaki Z900 2017-2022',
 'Kit completo DID para Kawasaki Z900. Cadena 525VX3 Gold + piñón 17T + corona 41T.',
 164.00,  4, 2, 'TRA-006', 3),

('Corona aluminio 7075 Renthal 42T',
 'Corona de aluminio aeronáutico Renthal 7075-T6 anodizado. 42 dientes paso 525. Peso 280g.',
 68.00,  7, 3, 'TRA-007', 3),

('Corona acero 520 45T JT Sprockets',
 'Corona de acero JT Sprockets 520 45T. Tratamiento térmico inductivo. Compatible Kawasaki 636 / Z650.',
 32.90, 10, 4, 'TRA-008', 3),

('Piñón delantero JT 16T paso 525',
 'Piñón delantero JT Sprockets 16 dientes paso 525. Acero 10B38. Compatible con múltiples modelos.',
 16.50, 18, 5, 'TRA-009', 3),

('Tensor cadena automático universal',
 'Tensor de cadena automático de aluminio CNC. Compatible con pit bikes y minicross 50-140cc.',
 14.90, 10, 4, 'TRA-010', 3),

('Eslabón de unión rápida DID 520 Gold',
 'Eslabón de unión rápida DID 520 Gold para cadenas X-ring. Pack de 2 unidades.',
 8.90, 30, 8, 'TRA-011', 3),

('Eslabón de unión rápida RK 525',
 'Eslabón de cierre rápido RK para cadenas 525 con retén. Compatible RK y DID. Pack de 2.',
 9.50, 25, 8, 'TRA-012', 3),

('Grasa cadena Motul Chain Lube Road 400ml',
 'Lubricante de cadena Motul Chain Lube Road. Penetración total, alta adherencia. Resistente al agua. 400ml.',
 11.90, 22, 7, 'TRA-013', 3),

('Grasa cadena Castrol Chain Lube Off-Road 400ml',
 'Lubricante cadena para off-road Castrol. Formulación extra adhesiva. Resistente barro y agua. 400ml.',
 10.50, 18, 6, 'TRA-014', 3),

-- ─────────────────────────────────────────────────────────────
-- CATEGORÍA 4 · ELECTRICIDAD
-- ─────────────────────────────────────────────────────────────

('Batería Yuasa YTX9-BS 12V 8Ah',
 'Batería AGM sin mantenimiento Yuasa YTX9-BS. Corriente de arranque 120A en frío. Para naked y scooters 125cc.',
 62.90,  8, 3, 'ELE-004', 4),

('Batería Bosch M6 YTZ14S 12V 11.2Ah',
 'Batería Bosch AGM YTZ14S. 190A CCA. Para deportivas y adventure 750-1200cc. Libre de mantenimiento.',
 89.00,  5, 2, 'ELE-005', 4),

('Cargador batería Optimate 4 Dual',
 'Cargador / mantenedor de batería Optimate 4 Dual 12V. Diagnóstico automático. Para baterías 3-50Ah.',
 49.90,  6, 2, 'ELE-006', 4),

('Bombilla H7 Osram Night Breaker 200',
 'Bombilla halógena H7 Osram Night Breaker 200% más luz. 12V 55W. Par de bombillas. Homologada E1.',
 21.90, 15, 5, 'ELE-007', 4),

('Bombilla H4 Philips X-tremeVision Moto',
 'Bombilla H4 Philips X-tremeVision +130% luz. 12V 60/55W. Para motos con faro H4 proyector o reflector.',
 18.50, 18, 5, 'ELE-008', 4),

('Kit LED H7 Osram LEDriving HL Gen2',
 'Kit conversión LED H7 Osram LEDriving HL Gen2. 6000K. Plug & play. Sin ventilador. Par de bombillas.',
 79.90,  6, 3, 'ELE-009', 4),

('Regulador rectificador Yamaha FZ6/FZS',
 'Regulador rectificador de voltaje para Yamaha FZ6 2004-2009 y FZS 1000 2001-2005. OEM 5SL-81960-00.',
 42.00,  4, 2, 'ELE-010', 4),

('Relé arranque universal 12V 30A',
 'Relé electromagnético 12V 30A para circuito de arranque. 4 terminales. Compatible con la mayoría de motos.',
 6.90, 25, 8, 'ELE-011', 4),

('Bobina encendido Kawasaki ER-6N/ER-6F',
 'Bobina de encendido Kawasaki ER-6N 2009-2015 y ER-6F 2012-2015. OEM 21121-0023. Con cable y capuchón.',
 34.50,  5, 2, 'ELE-012', 4),

('Sensor posición cigüeñal Suzuki GSX-R1000',
 'Sensor CKP para Suzuki GSX-R1000 2001-2006. 2 pines. OEM 32160-40F10.',
 38.90,  4, 2, 'ELE-013', 4),

('Indicador intermitente LED homologado par',
 'Par de intermitentes LED universales E-Mark. Cuerpo aluminio negro. Base 10mm. Resistencia integrada anti-flash.',
 24.90, 12, 4, 'ELE-014', 4),

('Cable bujía NGK Racing RC-YM803 set 4',
 'Set de 4 cables de bujía NGK Racing para Yamaha R6 2003-2005. Núcleo espiral silicona. Baja resistencia.',
 44.90,  5, 2, 'ELE-015', 4),

('Fusible de cuchilla mini 10A pack 10',
 'Pack 10 fusibles de cuchilla mini 10A (rojo). Universales para instalaciоnes eléctricas de moto.',
 3.20, 50, 15, 'ELE-016', 4),

('Interruptor emergencia moto universal',
 'Interruptor de corte de corriente universal 12V para moto. IP67. Cuerpo aluminio CNC. Botón rojo.',
 12.90, 14, 4, 'ELE-017', 4),

-- ─────────────────────────────────────────────────────────────
-- CATEGORÍA 5 · CARROCERÍA
-- ─────────────────────────────────────────────────────────────

('Carenado frontal Kawasaki ZX-6R 2007-2008',
 'Carenado delantero completo Kawasaki ZX-6R 2007-2008. Plástico ABS termoplástico. Prepintado blanco.',
 138.00,  2, 1, 'CAR-002', 5),

('Carenado bajo motor Yamaha R1 2015-2019',
 'Carenado bajo motor Yamaha R1 2015-2019. ABS. Incluye tornillería de montaje. Prepintado negro mate.',
 95.00,  3, 1, 'CAR-003', 5),

('Cola trasera Yamaha MT-09 2017-2020',
 'Cola trasera / asiento trasero Yamaha MT-09 Tracer 2017-2020. ABS. Color original silver bullet.',
 78.50,  2, 1, 'CAR-004', 5),

('Retrovisor izquierdo universal CNC plegable',
 'Retrovisor izquierdo universal aluminio CNC. Plegable manualmente. Rosca M10 derecha. Cristal convexo.',
 28.90, 10, 4, 'CAR-005', 5),

('Par retrovisores Honda CB650R 2019-2022',
 'Par de retrovisores originales tipo Honda CB650R 2019-2022. Estructura aluminio, brazo corto. Rosca M10.',
 64.00,  4, 2, 'CAR-006', 5),

('Manillar aluminio Renthal Fatbar 28mm negro',
 'Manillar Renthal Fatbar 28mm aluminio 7075-T6 anodizado negro. Alzada 83mm. Para naked y supermoto.',
 89.90,  5, 2, 'CAR-007', 5),

('Puños manillar Oxford Primo espuma',
 'Puños de manillar Oxford Primo. Espuma de alta densidad. Diámetro 22mm. Antideslizantes. Negro.',
 14.90, 20, 6, 'CAR-008', 5),

('Puños calefactables Oxford Hot Grips Essential',
 'Puños calefactables Oxford Hot Grips Essential Sport. 22mm / 25mm. 5 niveles temperatura. Controlador LED.',
 59.90,  6, 2, 'CAR-009', 5),

('Protector depósito resina epoxi universal',
 'Protector lateral de depósito en resina 3D universal. Par. Adherencia por presión. 20x15cm. Negro carbono.',
 18.90, 15, 5, 'CAR-010', 5),

('Tapa depósito CNC Ducati Monster 821/1200',
 'Tapa de depósito mecanizada CNC para Ducati Monster 821 2014-2020 y 1200 2014-2020. Aluminio rojo.',
 42.00,  3, 2, 'CAR-011', 5),

('Guardabarros trasero corto universal ABS',
 'Guardabarros corto trasero universal ABS. Portamatrícula integrado con luz LED. Para naked y streetfighter.',
 38.90,  7, 3, 'CAR-012', 5),

('Pasarruedas trasero Suzuki GSX-R 600/750 06-10',
 'Pasarruedas trasero Suzuki GSX-R 600/750 2006-2010. Nylon reforzado negro. Incluye tornillería.',
 22.50,  5, 2, 'CAR-013', 5),

('Tornillería carenado kit acero inox 30 piezas',
 'Kit tornillería para fijación de carenados. 30 piezas acero inoxidable M5/M6. Cabeza Allen. Universal.',
 12.90, 20, 6, 'CAR-014', 5),

('Adhesivo antivibracion espuma doble cara 5mm',
 'Rollo adhesivo de espuma doble cara 5mm para fijación carenados y protección de vibraciones. 5m x 20mm.',
 7.50, 18, 5, 'CAR-015', 5),

('Asiento Saddlemen corto Honda CB750 Nighthawk',
 'Asiento Saddlemen Tracker corto para Honda CB750 Nighthawk 1991-2003. Vinilo cosido. Espuma HD.',
 159.00,  2, 1, 'CAR-016', 5);
