-- =============================================================================
-- Seed: 50 productos con todos los campos completados
-- Categorías: 1=Frenos, 2=Motor, 3=Transmisión, 4=Electricidad, 5=Carrocería
-- Marcas genéricas / ampliamente conocidas en el sector moto
-- @author Carlitos6712
-- =============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ── Marcas ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO marcas (nombre, descripcion) VALUES
('NGK',          'Fabricante japonés de bujías y sensores Lambda'),
('Denso',        'Fabricante japonés de componentes de encendido y filtros'),
('Motul',        'Lubricantes y fluidos de alto rendimiento'),
('Castrol',      'Aceites y lubricantes para motocicletas'),
('K&N',          'Filtros de aire de alto flujo lavables'),
('HiFlo Filtro', 'Filtros de aceite y aire para moto'),
('Twin Air',     'Filtros de aire de espuma para offroad'),
('Brembo',       'Sistemas de frenado de competición y serie'),
('Galfer',       'Discos de freno y latiguillos metálicos'),
('EBC Brakes',   'Pastillas y discos de freno de origen británico'),
('DID',          'Cadenas de transmisión japonesas'),
('RK Excel',     'Cadenas y piñones de transmisión'),
('JT Sprockets', 'Piñones y coronas de acero y aluminio'),
('Renthal',      'Manillares, piñones y componentes de aluminio'),
('Yuasa',        'Baterías de plomo-ácido y litio para moto'),
('Bosch',        'Componentes eléctricos, bujías y sensores'),
('Osram',        'Bombillas halógenas y LED para moto'),
('Philips',      'Iluminación de precisión para vehículos'),
('Oxford',       'Accesorios y protecciones para motocicletas'),
('Athena',       'Juntas, pistones y recambios de motor');

-- ── Variables de FK ───────────────────────────────────────────────────────────
SET @id_ngk     = (SELECT id FROM marcas WHERE nombre = 'NGK'          LIMIT 1);
SET @id_denso   = (SELECT id FROM marcas WHERE nombre = 'Denso'        LIMIT 1);
SET @id_motul   = (SELECT id FROM marcas WHERE nombre = 'Motul'        LIMIT 1);
SET @id_castrol = (SELECT id FROM marcas WHERE nombre = 'Castrol'      LIMIT 1);
SET @id_kn      = (SELECT id FROM marcas WHERE nombre = 'K&N'          LIMIT 1);
SET @id_hiflo   = (SELECT id FROM marcas WHERE nombre = 'HiFlo Filtro' LIMIT 1);
SET @id_twin    = (SELECT id FROM marcas WHERE nombre = 'Twin Air'     LIMIT 1);
SET @id_brembo  = (SELECT id FROM marcas WHERE nombre = 'Brembo'       LIMIT 1);
SET @id_galfer  = (SELECT id FROM marcas WHERE nombre = 'Galfer'       LIMIT 1);
SET @id_ebc     = (SELECT id FROM marcas WHERE nombre = 'EBC Brakes'   LIMIT 1);
SET @id_did     = (SELECT id FROM marcas WHERE nombre = 'DID'          LIMIT 1);
SET @id_rk      = (SELECT id FROM marcas WHERE nombre = 'RK Excel'     LIMIT 1);
SET @id_jt      = (SELECT id FROM marcas WHERE nombre = 'JT Sprockets' LIMIT 1);
SET @id_renthal = (SELECT id FROM marcas WHERE nombre = 'Renthal'      LIMIT 1);
SET @id_yuasa   = (SELECT id FROM marcas WHERE nombre = 'Yuasa'        LIMIT 1);
SET @id_bosch   = (SELECT id FROM marcas WHERE nombre = 'Bosch'        LIMIT 1);
SET @id_osram   = (SELECT id FROM marcas WHERE nombre = 'Osram'        LIMIT 1);
SET @id_philips = (SELECT id FROM marcas WHERE nombre = 'Philips'      LIMIT 1);
SET @id_oxford  = (SELECT id FROM marcas WHERE nombre = 'Oxford'       LIMIT 1);
SET @id_athena  = (SELECT id FROM marcas WHERE nombre = 'Athena'       LIMIT 1);

-- =============================================================================
-- CATEGORÍA 1 — FRENOS (FRE-101 … FRE-110)
-- =============================================================================
INSERT INTO productos
    (nombre, descripcion, descripcion_larga, precio, stock, stock_minimo,
     codigo_ref, marca, marca_id, codigo_barras, url_proveedor, proveedor,
     ubicacion, peso, capacidad, longitud, anchura, diametro, alertas_email, categoria_id)
VALUES
(
    'Pastilla Brembo HP Sintrada Delantera',
    'Pastilla de freno sinterizada de alta temperatura para uso deportivo.',
    'Las pastillas Brembo HP Sintered están fabricadas con compuesto metálico sinterizado a alta presión. Ofrecen freno progresivo y potente desde frío, resistencia a temperaturas superiores a 500 °C y vida útil superior a las orgánicas. Compatibles con la mayoría de mordazas radiales de 4 pistones. Incluyen muelle anti-ruido.',
    28.95, 40, 8, 'FRE-101', 'Brembo', @id_brembo,
    '8020584073567', 'https://catalogo.brembo.test/hp-sintered-front',
    'DistribucionMotoES', 'A1-01', 185, NULL, 110, 52, NULL, 1, 1
),
(
    'Pastilla EBC FA Series Orgánica Trasera',
    'Pastilla orgánica de bajo ruido para uso urbano y turismo.',
    'Las pastillas EBC FA Series utilizan compuesto orgánico de aramida sin amianto. Generan menos polvo que las sinterizadas, protegen la superficie del disco y son ideales para temperaturas de trabajo moderadas (hasta 350 °C). Recomendadas para uso diario y recorridos urbanos. Embalaje doble con pastillas para los dos lados.',
    14.50, 55, 10, 'FRE-102', 'EBC Brakes', @id_ebc,
    '5030699030462', 'https://catalogo.ebcbrakes.test/fa-organic-rear',
    'MotoRecambiosIberia', 'A1-02', 120, NULL, 90, 42, NULL, 1, 1
),
(
    'Disco Galfer Wave Flotante 310 mm',
    'Disco de freno wave flotante de acero inoxidable 310 mm.',
    'El disco Galfer Wave Flotante combina una corona de acero inoxidable AISI 440B con un portadisco de aluminio anodizado 7075 T6. El diseño wave reduce el peso no suspendido, mejora la disipación térmica y limpia la pastilla de agua y suciedad en cada rotación. Homologado para uso en vía pública y circuito. Tornillería M8 incluida.',
    89.00, 18, 4, 'FRE-103', 'Galfer', @id_galfer,
    '8436012224456', 'https://catalogo.galfer.test/wave-310-floating',
    'DistribucionMotoES', 'A1-03', 780, NULL, 310, 310, 310.00, 1, 1
),
(
    'Disco EBC MD Standard 220 mm Trasero',
    'Disco de freno trasero estándar de acero inoxidable 220 mm.',
    'El disco EBC MD Standard está fabricado en acero inoxidable AISI 420 con perforaciones simétricas que reducen el efecto de vitrificación. Su espesor de 4,5 mm garantiza rigidez y durabilidad. La superficie perfilada mejora la evacuación del calor y permite trabajar con pastillas orgánicas y sinterizadas. Compatible con frenos de disco flotante.',
    42.00, 22, 5, 'FRE-104', 'EBC Brakes', @id_ebc,
    '5030699082553', 'https://catalogo.ebcbrakes.test/md-220-rear',
    'MotoRecambiosIberia', 'A1-04', 540, NULL, 220, 220, 220.00, 1, 1
),
(
    'Latiguillo Metálico Galfer Delantero 60 cm',
    'Latiguillo de freno de alta presión con malla de acero inoxidable.',
    'El latiguillo Galfer reemplaza al original de goma, eliminando la expansión del tubo bajo presión. Esto se traduce en una respuesta más directa e inmediata del freno. Fabricado con malla inoxidable trenzada y terminales bañados en cromo. Longitud estándar 600 mm con rosca M10×1. Incluye juntas de cobre. Homologado TÜV.',
    32.50, 30, 6, 'FRE-105', 'Galfer', @id_galfer,
    '8436012210008', 'https://catalogo.galfer.test/steel-brake-line-60',
    'DistribucionMotoES', 'A1-05', 95, NULL, 600, 8, NULL, 1, 1
),
(
    'Líquido de Frenos Motul DOT 5.1',
    'Fluido de frenos sintético DOT 5.1 de muy alto punto de ebullición.',
    'El Motul DOT 5.1 es un fluido de frenos 100 % sintético miscible con DOT 3 y DOT 4. Su punto de ebullición en seco supera los 270 °C y en húmedo los 185 °C, reduciendo el riesgo de vapor lock incluso en uso intensivo en circuito. Compatible con todos los sistemas hidráulicos ABS y CBS. No ataca las piezas de caucho EPDM ni los cilindros de aluminio. Formato 500 mL.',
    9.95, 80, 15, 'FRE-106', 'Motul', @id_motul,
    '3374650239552', 'https://catalogo.motul.test/dot51-500ml',
    'LubricantesNorte', 'B2-01', 530, 500, NULL, NULL, NULL, 1, 1
),
(
    'Mordaza Brembo M4 Monobloque Delantera',
    'Mordaza de freno monobloque de aluminio de 4 pistones Brembo M4.',
    'La mordaza Brembo M4 es la referencia en equipamiento de serie de muchas supermotard y naked de altas prestaciones. Fabricada en aluminio de aviación por mecanizado CNC monobloque para máxima rigidez. Los cuatro pistones de aluminio anodizado aseguran una distribución uniforme de la presión sobre la pastilla. Incluye sangrador, tornillos de fijación M10 y juntas de recambio.',
    310.00, 8, 2, 'FRE-107', 'Brembo', @id_brembo,
    '8020584053491', 'https://catalogo.brembo.test/m4-monobloc',
    'DistribucionMotoES', 'A1-06', 920, NULL, 148, 68, NULL, 1, 1
),
(
    'Kit Reparación Pinza EBC Caliper Rebuild',
    'Kit de juntas y pistones para revisión completa de pinza de freno.',
    'El kit de reparación EBC incluye cuatro pistones de aluminio anodizado, juntas tóricas de EPDM y guardapolvos de silicona para pinzas de 4 pistones Ø 28-34 mm. Indicado para mantenimiento preventivo a los 40.000 km o cuando se detecta fuga de fluido. Compatible con mordazas de doble pistón opuesto. Instrucciones detalladas incluidas.',
    24.00, 25, 5, 'FRE-108', 'EBC Brakes', @id_ebc,
    '5030699210013', 'https://catalogo.ebcbrakes.test/caliper-rebuild-kit',
    'MotoRecambiosIberia', 'A1-07', 180, NULL, 80, 60, NULL, 1, 1
),
(
    'Pastilla Galfer FD Racing Delantera Carbono',
    'Pastilla de freno de carbono para uso exclusivo en circuito.',
    'La pastilla Galfer FD Racing utiliza compuesto de carbono-carbono diseñado para trabajar a partir de 300 °C. Ofrece resistencia al fading extremo y rozamiento constante independientemente de la temperatura. Solo recomendada para circuito o competición; en uso urbano puede ser agresiva con el disco. Disponible para mordazas de 2, 4 y 6 pistones. Cada set incluye 2 pastillas.',
    52.00, 12, 3, 'FRE-109', 'Galfer', @id_galfer,
    '8436012230007', 'https://catalogo.galfer.test/fd-carbon-racing',
    'DistribucionMotoES', 'A1-08', 200, NULL, 112, 54, NULL, 1, 1
),
(
    'Sensor ABS Bosch Rueda Delantera Universal',
    'Sensor de velocidad ABS de rueda delantera tipo Hall efecto.',
    'El sensor Bosch de efecto Hall genera señal digital de velocidad de rueda con alta precisión y resolución de 1°. Resistente al polvo, agua (IP68) y altas temperaturas hasta 150 °C. Cable de 450 mm con conector sellado 2 pines. Aplicación universal para ABS de 1 y 2 canales. Compatible con centralitas Bosch 9.1/9.3 y Conti MK20.',
    38.00, 20, 4, 'FRE-110', 'Bosch', @id_bosch,
    '3165140789011', 'https://catalogo.bosch.test/wheel-abs-sensor-front',
    'ElectricaMotoPlus', 'C3-01', 95, NULL, 450, 18, NULL, 1, 1
),

-- =============================================================================
-- CATEGORÍA 2 — MOTOR (MOT-101 … MOT-110)
-- =============================================================================
(
    'Bujía NGK Iridium IX CR8EIX',
    'Bujía de iridio para motores 4T de 250 a 600 cc.',
    'La bujía NGK Iridium IX CR8EIX incorpora un electrodo central de iridio Ø 0,6 mm que mejora la inflamación de la mezcla, reduce el consumo y aumenta la potencia. El electrodo de tierra con corte lateral en V favorece la propagación de la llama. Resistente a la corrosión por alto contenido de níquel. Vida útil hasta 100.000 km en condiciones normales. Precalibre de fábrica 0,8 mm.',
    12.50, 100, 20, 'MOT-101', 'NGK', @id_ngk,
    '0079751919831', 'https://catalogo.ngk.test/cr8eix-iridium',
    'DistribucionMotoES', 'B1-01', 18, NULL, 57, 20, NULL, 1, 2
),
(
    'Bujía Denso Iridium TT IU24TT',
    'Bujía doble punta de iridio y titanio para motores de alta compresión.',
    'La Denso IU24TT presenta un electrodo central de iridio Ø 0,4 mm y un electrodo de tierra con punta de titanio. Esta combinación ofrece máxima energía de chispa con mínimo desgaste. Adecuada para motores con relación de compresión superior a 12:1 y uso con combustibles premium. La rosca M14×1,25 con junta cónica garantiza un sellado perfecto sin retorqueo.',
    14.00, 80, 15, 'MOT-102', 'Denso', @id_denso,
    '4549787016421', 'https://catalogo.denso.test/iu24tt-iridium',
    'ElectricaMotoPlus', 'B1-02', 20, NULL, 57, 20, NULL, 1, 2
),
(
    'Aceite Motor Motul 7100 10W-40 4T 1 L',
    'Aceite motor 100% sintético para motos 4 tiempos con o sin embrague húmedo.',
    'El Motul 7100 10W-40 es un lubricante de síntesis total formulado con tecnología Ester Core. Protege el motor frente al desgaste desde el arranque en frío y mantiene sus propiedades durante todo el intervalo de cambio (máx. 8.000 km o 1 año). Compatible con embrague húmedo gracias a su baja fricción JASO MA2. Adecuado para motos de 4T que requieran API SJ/SL y JASO MA2.',
    14.95, 120, 20, 'MOT-103', 'Motul', @id_motul,
    '3374650221985', 'https://catalogo.motul.test/7100-10w40-1l',
    'LubricantesNorte', 'B2-02', 950, 1000, NULL, NULL, NULL, 1, 2
),
(
    'Aceite Motor Castrol Power1 Racing 4T 10W-50 1 L',
    'Lubricante 100% sintético de competición para motores 4T de alta cilindrada.',
    'Castrol Power1 Racing 4T es la elección de equipos de SBK y MotoGP para uso en calle. Formulación full-synthetic con aditivos anti-desgaste de última generación. Viscosidad 10W-50 ideal para motores con temperatura de trabajo alta (>100 °C) como bicilíndricas paralleltwin y multicilíndricas. Certificación JASO MA2 y API SL. No apto para embragues en baño de aceite que requieran JASO T903.',
    19.90, 90, 15, 'MOT-104', 'Castrol', @id_castrol,
    '4008177119309', 'https://catalogo.castrol.test/power1-racing-10w50-1l',
    'LubricantesNorte', 'B2-03', 960, 1000, NULL, NULL, NULL, 1, 2
),
(
    'Filtro de Aire K&N HA-1087 Honda CBR1000RR',
    'Filtro de aire de algodón lavable de alto flujo para Honda CBR1000RR 08-16.',
    'El filtro K&N HA-1087 reemplaza al filtro de papel original incrementando el caudal de aire hasta un 15 % sin modificar el carburador o la ECU. El medio filtrante de algodón de ocho capas con aceite especial K&N retiene las partículas de 10 µm y es reutilizable durante toda la vida de la moto con el kit de limpieza. Homologado para uso en vía pública. Marco de poliuretano moldado para ajuste perfecto.',
    49.00, 25, 5, 'MOT-105', 'K&N', @id_kn,
    '0024844281428', 'https://catalogo.kn.test/ha-1087',
    'MotoRecambiosIberia', 'B1-03', 210, NULL, 280, 190, NULL, 1, 2
),
(
    'Filtro Aceite HiFlo HF204RC Racing',
    'Filtro de aceite de alto caudal con núcleo de acero inoxidable.',
    'El HiFlo HF204RC Racing presenta un elemento filtrante de fibra de vidrio multicapa de alta eficiencia (β10 ≥ 200) en un alojamiento de aluminio con válvula antirretorno de acero inoxidable. Rosca M20×1,5. Compatible con Honda CB, CBR, CRF, Yamaha FZR, FZ, Kawasaki ZZR, ZX y Suzuki GSX-R entre otros 400+ modelos. Intervalo de cambio recomendado: 6.000 km.',
    9.50, 150, 25, 'MOT-106', 'HiFlo Filtro', @id_hiflo,
    '5020572004038', 'https://catalogo.hiflo.test/hf204rc',
    'DistribucionMotoES', 'B1-04', 125, NULL, 75, 68, 68.00, 1, 2
),
(
    'Filtro Aire Espuma Twin Air Honda CRF450R',
    'Filtro de espuma bi-densidad para motocross Honda CRF450R 19-23.',
    'El Twin Air para CRF450R está fabricado con espuma de poliuretano bi-densidad: capa exterior de mayor poro para retención de tierra gruesa y capa interior de menor poro para filtrado fino. Ofrece un caudal 20 % superior al filtro original. Se limpia con el kit Twin Air Bio Dirt Remover y se pre-acecha con Twin Air Bio Liquid Power. Marco preciso de poliuretano moldeado, sin costuras.',
    28.00, 35, 8, 'MOT-107', 'Twin Air', @id_twin,
    '8711347205920', 'https://catalogo.twinair.test/crf450r-foam-filter',
    'MotoRecambiosIberia', 'B1-05', 80, NULL, 240, 160, NULL, 1, 2
),
(
    'Kit Juntas Motor Athena Honda CB500F',
    'Kit completo de juntas para revisión de motor Honda CB500F 13-22.',
    'El kit Athena P400210850100 incluye todas las juntas necesarias para una revisión de motor completa: junta de culata MLS multicapa de acero, juntas de tapa de válvulas, cárter, bomba de aceite, caja de cambios y tapones de drenaje. Los materiales empleados son acero inoxidable, silicona de alta temperatura y grafito expandido. Compatible con las revisiones de 40.000 y 80.000 km.',
    68.00, 12, 3, 'MOT-108', 'Athena', @id_athena,
    '8005953045218', 'https://catalogo.athena.test/cb500f-full-gasket-kit',
    'MotoRecambiosIberia', 'B1-06', 340, NULL, NULL, NULL, NULL, 1, 2
),
(
    'Pistón Athena Ø 96 mm KTM 450 EXC-F STD',
    'Pistón de reemplazo estándar con segmentos para KTM 450 EXC-F.',
    'Pistón Athena forjado en aleación de aluminio 4032 con tratamiento de superficie DLC (Diamond Like Carbon) en la falda para reducir la fricción. Incluye bulón de titanio, segmento de compresión y segmento rascador. Relación de compresión 12,4:1. Diámetro nominal 96,00 mm; disponible en sobremedida +0,1 y +0,5. Apto para KTM 450 EXC-F, SX-F, XCF-W 2012-2022.',
    145.00, 8, 2, 'MOT-109', 'Athena', @id_athena,
    '8005953198121', 'https://catalogo.athena.test/piston-ktm450excf-std',
    'MotoRecambiosIberia', 'B1-07', 390, NULL, NULL, NULL, 96.00, 1, 2
),
(
    'Bujía NGK Racing CR9EKB Doble Platino',
    'Bujía de platino para motores 4T de competición hasta 15.000 rpm.',
    'La NGK CR9EKB Racing incorpora electrodos de platino en ambos lados con geometría de corte en U para autoextracción de carbono. La resistencia de 5 kΩ reduce las interferencias con la electrónica. Ideal para motores modificados, con camshafts de altas alzadas o mapeados agresivos. Rango térmico 9 para motores de alta compresión. Rosca M14×1,25 cónica sin junta.',
    18.00, 60, 12, 'MOT-110', 'NGK', @id_ngk,
    '0079751929892', 'https://catalogo.ngk.test/cr9ekb-racing',
    'DistribucionMotoES', 'B1-08', 22, NULL, 57, 20, NULL, 1, 2
),

-- =============================================================================
-- CATEGORÍA 3 — TRANSMISIÓN (TRA-101 … TRA-110)
-- =============================================================================
(
    'Cadena DID 520ERV3 Gold 120 Eslabones',
    'Cadena de transmisión 520 XW-Ring dorada de alta performance.',
    'La DID 520ERV3 es la referencia de equipamiento original en motos de competición de fábrica. Utiliza retenes XW-Ring de cuatro labios que ofrecen una estanqueidad superior a los O-Ring clásicos con un 30 % menos de resistencia interna. La placa exterior está tratada con revestimiento de zinc y la cadena pasa por un proceso de preestirado para reducir el rodaje inicial. Incluye pasador de unión tipo clip y remache.',
    78.00, 20, 4, 'TRA-101', 'DID', @id_did,
    '4528547202812', 'https://catalogo.did.test/520erv3-gold-120',
    'DistribucionMotoES', 'C1-01', 890, NULL, NULL, NULL, NULL, 1, 3
),
(
    'Cadena RK 525GXW XW-Ring 118 Eslabones Plateada',
    'Cadena 525 XW-Ring plateada RK para naked y sport-touring.',
    'La RK 525GXW XW-Ring combina retenes de cuatro labios con un diseño de placa exterior optimizado en túnel para reducir peso sin comprometer la resistencia a tracción (34.000 N). Paso 525, 118 eslabones, apta para motos de hasta 160 cv con relaciones de transmisión estándar. Se suministra con eslabón de cierre remacho y herramienta de ajuste de fábrica. Lubricación de fábrica con grasa MoS2.',
    65.00, 18, 4, 'TRA-102', 'RK Excel', @id_rk,
    '4943691503271', 'https://catalogo.rkexcel.test/525gxw-118-silver',
    'MotoRecambiosIberia', 'C1-02', 870, NULL, NULL, NULL, NULL, 1, 3
),
(
    'Piñón JT JTF1263 15T Acero Honda CB600F',
    'Piñón de ataque 15 dientes acero para Honda CB600F Hornet.',
    'El piñón JT JTF1263.15 está fabricado en acero de aleación 16MnCrS5 con tratamiento térmico de cementación y temple para una dureza superficial de 60 HRC. El diseño de perfil de diente optimizado reduce el nivel de ruido y aumenta la vida útil respecto al original. Paso 530. Montaje directo en árbol de salida de caja de cambios mediante cuñero y tuerca M24×1,5. Peso 310 g.',
    18.50, 45, 8, 'TRA-103', 'JT Sprockets', @id_jt,
    '7527899132658', 'https://catalogo.jtsprockets.test/jtf1263-15t',
    'DistribucionMotoES', 'C1-03', 310, NULL, NULL, NULL, NULL, 1, 3
),
(
    'Corona JT JTR1313 42T Aluminio 7075 Honda CBR600RR',
    'Corona trasera 42T de aluminio ligero para Honda CBR600RR 03-12.',
    'La corona JT JTR1313.42 está mecanizada en aluminio aeronáutico 7075-T6 con tratamiento de anodizado duro Ø2 µm para máxima resistencia a la abrasión. Frente a las coronas de acero, reduce el peso no suspendido en 450 g. Paso 520. Agujerado en 5 pernos M10 con patrón PCD 130 mm. Incluye tornillería de aluminio inoxidable. Adecuada para uso en circuito.',
    52.00, 22, 4, 'TRA-104', 'JT Sprockets', @id_jt,
    '7527899654327', 'https://catalogo.jtsprockets.test/jtr1313-42t-alu',
    'DistribucionMotoES', 'C1-04', 380, NULL, NULL, NULL, NULL, 1, 3
),
(
    'Piñón Renthal 428 14T Acero Yamaha YZF-R125',
    'Piñón delantero de acero templado Renthal para Yamaha YZF-R125.',
    'El piñón Renthal para YZF-R125 utiliza acero 16MnCr5 con cementación profunda y tratamiento de fosfatado para resistencia a la corrosión. El perfil del diente en involuta optimizado reduce el par de arranque y el consumo. Apto para cadenas de paso 428. Montaje en estriado 17×28,5 mm con tuerca M22×1. Peso 190 g. Compatible también con MT-125 y RC125 del mismo período.',
    15.00, 60, 10, 'TRA-105', 'Renthal', @id_renthal,
    '4897028321450', 'https://catalogo.renthal.test/428-14t-steel',
    'MotoRecambiosIberia', 'C1-05', 190, NULL, NULL, NULL, NULL, 1, 3
),
(
    'Kit de Transmisión DID 520VX3 Cadena + Piñones',
    'Kit transmisión completo cadena DID 520VX3 + piñón JT + corona JT.',
    'Pack de transmisión completo: cadena DID 520VX3 O-Ring 112 eslabones, piñón JT 15T y corona JT 45T compatibles con Honda CBR500R, CB500F y CB500X 2013-2022. Resistencia a tracción de la cadena: 24.500 N. El set se suministra con eslabón de cierre clip, lubricante de cadena en spray 100 mL y hoja de instrucciones de montaje. Permite reducir el cambio de los tres elementos en una sola operación.',
    115.00, 14, 3, 'TRA-106', 'DID', @id_did,
    '4528547530011', 'https://catalogo.did.test/520vx3-kit-cbr500r',
    'DistribucionMotoES', 'C1-06', 1250, NULL, NULL, NULL, NULL, 1, 3
),
(
    'Aceite Transmisión Motul Transoil Expert 10W-40',
    'Aceite para caja de cambios de motos 2T y transmisiones separadas.',
    'El Motul Transoil Expert 10W-40 está formulado con base mineral de alta viscosidad para la lubricación de cajas de cambios de motos de 2 tiempos con colector separado o transmisiones dedicadas. Su viscosidad optimizada para engranajes de paso corto reduce el consumo de potencia y minimiza el desgaste de los árboles de cambio. Aditivos EP antiespumantes. Formato 500 mL.',
    8.50, 90, 15, 'TRA-107', 'Motul', @id_motul,
    '3374650014002', 'https://catalogo.motul.test/transoil-expert-10w40-500ml',
    'LubricantesNorte', 'B2-04', 510, 500, NULL, NULL, NULL, 1, 3
),
(
    'Grasa de Cadena Motul Chain Lube Road 150 mL',
    'Spray lubricante para cadenas en uso en carretera con retenes.',
    'El Motul Chain Lube Road está formulado con una base de litio complejo espesada con PTFE para una adherencia excepcional a la cadena incluso a alta velocidad. Su viscosidad media garantiza la penetración entre los eslabones interiores sin lanzamiento excesivo. Compatible con retenes O-Ring, X-Ring y W-Ring. No ataca el caucho de las ruedas. Aerosol con tubo flexible de aplicación precisa. 150 mL.',
    6.50, 110, 20, 'TRA-108', 'Motul', @id_motul,
    '3374650238548', 'https://catalogo.motul.test/chain-lube-road-150ml',
    'LubricantesNorte', 'B2-05', 185, 150, NULL, NULL, NULL, 1, 3
),
(
    'Corona Renthal 520 42T Aluminio Kawasaki Z900',
    'Corona trasera ligera de aluminio 7075 para Kawasaki Z900 2017+.',
    'La corona Renthal para Z900 ofrece una reducción de 450 g respecto a la corona de acero original. Fabricada en 7075-T6 con anodizado tipo III de 20 µm para protección frente a la abrasión y la corrosión. Paso 520, 42 dientes, patrón de 6 pernos M10. El perfil de diente con asimetría Renthal Ultralite reduce el nivel de ruido en 3 dB. Tornillería Torx incluida.',
    68.00, 16, 3, 'TRA-109', 'Renthal', @id_renthal,
    '4897028401458', 'https://catalogo.renthal.test/520-42t-z900',
    'MotoRecambiosIberia', 'C1-07', 390, NULL, NULL, NULL, NULL, 1, 3
),
(
    'Cadena RK 520MXZ4 Motocross 116 Eslabones',
    'Cadena de transmisión sin retenes para motocross y enduro.',
    'La RK 520MXZ4 es una cadena sin retenes de alta resistencia para uso en competición offroad. Los pasadores están tratados con nitruro de cromo y las placas son de acero de alta resistencia (HRC 58-62). Su diseño sin retenes reduce la fricción interna al mínimo, lo que se traduce en más potencia disponible en la rueda. Paso 520, 116 eslabones, resistencia a tracción 24.400 N. Se suministra con eslabón clip.',
    42.00, 30, 6, 'TRA-110', 'RK Excel', @id_rk,
    '4943691104512', 'https://catalogo.rkexcel.test/520mxz4-116',
    'MotoRecambiosIberia', 'C1-08', 700, NULL, NULL, NULL, NULL, 1, 3
),

-- =============================================================================
-- CATEGORÍA 4 — ELECTRICIDAD (ELE-101 … ELE-110)
-- =============================================================================
(
    'Batería Yuasa YTX14-BS AGM 12V 12Ah',
    'Batería AGM sellada de plomo-ácido libre de mantenimiento 12V 12Ah.',
    'La batería Yuasa YTX14-BS es el estándar de referencia para motos de 400 a 1200 cc. Tecnología AGM (Absorbed Glass Mat) con electrolito absorbido en separador de fibra de vidrio: no derrama ácido, puede montarse en cualquier ángulo y no requiere rellenado de agua. Corriente de arranque en frío (CCA) 200 A. Dimensiones 150×87×145 mm. Viene activada y cargada, lista para instalar.',
    68.00, 30, 6, 'ELE-101', 'Yuasa', @id_yuasa,
    '5050694000179', 'https://catalogo.yuasa.test/ytx14-bs',
    'ElectricaMotoPlus', 'C3-02', 3800, NULL, 150, 87, NULL, 1, 4
),
(
    'Batería Yuasa YTZ10S Litio-Ion 12V 8,6Ah',
    'Batería de litio-ion ultraligera YTZ10S 12V 8,6Ah.',
    'La Yuasa YTZ10S Li-Ion pesa solo 0,9 kg frente a los 2,5 kg de la equivalente de plomo. Ofrece una descarga de alta corriente de 150 A y soporta hasta 2.000 ciclos de carga/descarga. Ideal para motos donde el peso no suspendido y la centralización de masas son críticos. Compatible con sistemas de carga entre 13,8 V y 14,7 V. No requiere cargador especial. Dimensiones 113×70×87 mm.',
    109.00, 18, 3, 'ELE-102', 'Yuasa', @id_yuasa,
    '5050694063113', 'https://catalogo.yuasa.test/ytz10s-lithium',
    'ElectricaMotoPlus', 'C3-03', 900, NULL, 113, 70, NULL, 1, 4
),
(
    'Bombilla Osram H4 Night Breaker 200 60/55W',
    'Bombilla halógena H4 con hasta 200% más de luz que la estándar.',
    'La Osram Night Breaker 200 utiliza tecnología de quemador con gas de xenón y filamento de precisión a 3.200 K para ofrecer un haz blanco-azulado de alta luminosidad. Aumenta la visibilidad de la carretera en un 200 % frente a la bombilla halógena de referencia y proyecta el haz 25 m más lejos. Aprobada para uso en vía pública en toda Europa. Vida útil 150 h. Casquillo P43t. Se vende en blíster doble.',
    18.00, 70, 12, 'ELE-103', 'Osram', @id_osram,
    '4052899992306', 'https://catalogo.osram.test/h4-nightbreaker200',
    'ElectricaMotoPlus', 'C3-04', 35, NULL, 90, 30, NULL, 1, 4
),
(
    'Bombilla Philips X-tremeVision LED H7 6000K',
    'Bombilla LED retrofit H7 homologada 6000K 20W (equiv. 55W).',
    'La Philips X-tremeVision LED H7 ofrece una temperatura de color de 6000 K para una luz blanca diurna que mejora el contraste en condiciones de lluvia y niebla. Potencia de entrada 20 W con rendimiento lumínico equivalente a 55 W halógeno gracias a sus LEDs de última generación con driver integrado. Homologada ECE R10, R112 y R128. Plug-and-play en la mayoría de ópticas sin codificación CANbus. Vida útil > 12.000 h.',
    45.00, 40, 8, 'ELE-104', 'Philips', @id_philips,
    '8727900419381', 'https://catalogo.philips.test/h7-xtremevision-led',
    'ElectricaMotoPlus', 'C3-05', 80, NULL, 95, 35, NULL, 1, 4
),
(
    'Regulador Rectificador Bosch Universal 12V 35A',
    'Regulador rectificador de onda completa 12V 35A para generadores trifásicos.',
    'El regulador-rectificador Bosch convierte la corriente alterna del estátor trifásico en 12 V DC estabilizados entre 13,5 V y 14,8 V. Disipación de calor mejorada mediante carcasa de aluminio con aletas. Protección contra inversión de polaridad e instalación para picos de tensión de hasta 80 V. Intensidad nominal 35 A; pico 45 A durante 5 s. Conector Sumitomo 6 pines incluido. Temperatura de trabajo -40 °C a +150 °C.',
    55.00, 22, 4, 'ELE-105', 'Bosch', @id_bosch,
    '3165140801896', 'https://catalogo.bosch.test/rectifier-12v-35a',
    'ElectricaMotoPlus', 'C3-06', 285, NULL, 110, 80, NULL, 1, 4
),
(
    'Bobina de Encendido NGK HT-Lead 7 mm Universal',
    'Cable de alta tensión NGK de 7 mm para sistemas de encendido CDI e inductivo.',
    'El cable de alta tensión NGK 7 mm utiliza conductor de silicona trenzada con resistencia de supresión de interferencias de 5 kΩ/m y aislamiento para voltajes de hasta 40.000 V. La cubierta exterior es de EPDM de alta temperatura resistente a aceite, ozono y temperaturas de -40 °C a +250 °C. Extremos provistos de terminal resistivo de 5 kΩ y capuchón de bujía con junta de caucho. Longitud 90 cm; cortable a medida.',
    12.00, 55, 10, 'ELE-106', 'NGK', @id_ngk,
    '0079751993207', 'https://catalogo.ngk.test/ht-lead-7mm',
    'DistribucionMotoES', 'C3-07', 65, NULL, 900, 7, NULL, 1, 4
),
(
    'Sensor Lambda Bosch Universal LSU 4.2',
    'Sonda lambda wideband LSU 4.2 para control de mezcla en EFI.',
    'El sensor Bosch LSU 4.2 es el componente original en la mayoría de ECUs de aftermarket (Haltech, MoTeC, Ecumaster). Mide la relación lambda entre 0,65 y infinito (rango equivalente a 9,5-20 AFR gasolina). El elemento cerámico calentado internamente alcanza temperatura de trabajo en < 20 s. Resistencia de calefactor 7-11 Ω. Conector Bosch 5 pines. Rosca M18×1,5. Longitud total 130 mm con cable de 450 mm.',
    62.00, 15, 3, 'ELE-107', 'Bosch', @id_bosch,
    '3165140823072', 'https://catalogo.bosch.test/lsu42-lambda',
    'ElectricaMotoPlus', 'C3-08', 110, NULL, 130, 22, NULL, 1, 4
),
(
    'Bombilla Osram W5W LED Cool White 6000K (x10)',
    'Bombillas LED W5W 6000K para indicadores, tablero y luces de posición.',
    'Pack de 10 bombillas Osram LEDriving® W5W 6000K para sustitución de las W5W de tungsteno en cuadros de instrumentos, pilotos de posición y luces interiores. Potencia 0,5 W (equiv. 5 W tungsteno). Flujo luminoso 18 lm por unidad. Vida útil > 30.000 h. Temperatura de color 6000 K. Casquillo W2,1×9,5d. Plug-and-play sin resistencias adicionales en la mayoría de aplicaciones.',
    12.50, 85, 15, 'ELE-108', 'Osram', @id_osram,
    '4052899388093', 'https://catalogo.osram.test/w5w-led-6000k-x10',
    'ElectricaMotoPlus', 'C3-09', 25, NULL, 28, 9, NULL, 1, 4
),
(
    'Cargador Batería Oxford Oximiser 3X 12V 3A',
    'Cargador inteligente Oxford Oximiser 3X para baterías de plomo y GEL.',
    'El cargador Oxford Oximiser 3X incorpora 8 etapas automáticas de carga: comprobación, desulfatación, precarga, carga de absorción, recarga, ecualización, flotación y mantenimiento. Detecta automáticamente baterías de 6/12 V y selecciona el perfil de carga adecuado. Corriente máxima 3 A. Compatible con baterías de plomo-ácido (estándar, AGM, GEL) de 1,2 a 240 Ah. Cable de 1,5 m con acoples de pinzas y anillos soldados. Protección IP65.',
    42.00, 25, 5, 'ELE-109', 'Oxford', @id_oxford,
    '5030009009015', 'https://catalogo.oxford.test/oximiser-3x',
    'MotoRecambiosIberia', 'C3-10', 420, NULL, 145, 75, NULL, 1, 4
),
(
    'Interruptor Kill Switch Universal Manillar 22 mm',
    'Interruptor de corte de motor (kill switch) para manillar 22 mm.',
    'Kill switch de acción positiva con contacto normalmente cerrado (NC) para corte de encendido al pulsar. Cuerpo de plástico ABS reforzado con UV, sello de caucho para estanqueidad IPX4. Montaje en manillar de Ø 22 mm mediante abrazadera de aluminio con tornillo M5. Cable de 300 mm con terminal Faston 6,3 mm. Compatible con CDI, bobina inductiva y sistemas de inyección electrónica. Negro.',
    8.00, 95, 15, 'ELE-110', 'Oxford', @id_oxford,
    '5030009214528', 'https://catalogo.oxford.test/kill-switch-22mm',
    'MotoRecambiosIberia', 'C3-11', 75, NULL, 55, 40, NULL, 1, 4
),

-- =============================================================================
-- CATEGORÍA 5 — CARROCERÍA (CAR-101 … CAR-110)
-- =============================================================================
(
    'Manillar Renthal Fatbar 968 Ø 28 mm Aluminio',
    'Manillar oversize Renthal Fatbar 28 mm para enduro y MX.',
    'El Renthal Fatbar 968 es el manillar de referencia en enduro de fábrica (KTM, Husqvarna, Honda HRC). Fabricado en aleación de aluminio 7075-T6 con sección central de Ø 28,6 mm y brazos de Ø 22 mm para compatibilidad con controles de serie. Alzada 80 mm, retroceso 50 mm, anchura 803 mm. Curvatura 968 (alta). Tratamiento de anodizado duro negro. Peso 355 g. Incluye pad de protección.',
    82.00, 20, 4, 'CAR-101', 'Renthal', @id_renthal,
    '4897028680012', 'https://catalogo.renthal.test/fatbar-968-28mm',
    'DistribucionMotoES', 'D1-01', 355, NULL, 803, 28, 28.60, 1, 5
),
(
    'Protector Manillar Oxford OX601 Pro Muff Neopreno',
    'Manguitos de manillar Oxford Pro Muff en neopreno térmico.',
    'Los Oxford Pro Muff están fabricados en neopreno de 6 mm de alta densidad con forro interior de polar. Reducen la temperatura percibida en las manos hasta 15 °C en condiciones de frío y lluvia, alargando la autonomía de conducción en invierno. Apertura velcro ancha para enguantados y bolsillo interior para calentadores de puño eléctrico. Montaje en manillar de 22-28 mm sin herramientas. Talla única.',
    28.00, 38, 6, 'CAR-102', 'Oxford', @id_oxford,
    '5030009006014', 'https://catalogo.oxford.test/ox601-pro-muff',
    'MotoRecambiosIberia', 'D1-02', 310, NULL, 280, 180, NULL, 1, 5
),
(
    'Espejo Retrovisor Universal Ovalado M10 Derecho',
    'Retrovisor ovalado cromado de rosca M10 para manillar derecho.',
    'Espejo retrovisor de reemplazo con cuerpo de nylon ABS cromado y cristal convexo antideslumbrante de Ø 95×62 mm. Rosca vástago M10×1,25 derecha estándar (para lado derecho del manillar). Ajuste esférico de 360° sin herramientas. Compatible con la mayoría de naked, custom y scooters con rosca M10. Se adapta también a portaespejos M10 con contratuerca. Tapa de rosca anti-vibración incluida.',
    14.50, 50, 8, 'CAR-103', 'Oxford', @id_oxford,
    '5030009103411', 'https://catalogo.oxford.test/oval-mirror-m10-right',
    'MotoRecambiosIberia', 'D1-03', 220, NULL, 175, 100, NULL, 1, 5
),
(
    'Cubierta Moto Oxford Aquatex 1000D Talla M',
    'Funda impermeable Oxford Aquatex 1000D para motos de carretera talla M.',
    'La funda Aquatex de Oxford está fabricada en poliéster 1000D con capa de PU de alta densidad que supera los 5.000 mm de presión de agua. La costura termosellada elimina las filtraciones en los puntos de unión. Incluye bolsillo ventral con candado elastizado, reflectantes de 360° para visibilidad nocturna y funda de almacenaje reutilizable. Talla M: apta para naked y sport de 400-1000 cc. Lavable a máquina a 30 °C.',
    65.00, 22, 4, 'CAR-104', 'Oxford', @id_oxford,
    '5030009027019', 'https://catalogo.oxford.test/aquatex-1000d-m',
    'MotoRecambiosIberia', 'D1-04', 1650, NULL, NULL, NULL, NULL, 1, 5
),
(
    'Recambio Pantalla Casco Ahumada Universal 650 mm',
    'Pantalla ahumada de recambio para casco integral con abertura 650 mm.',
    'Pantalla de recambio fabricada en policarbonato óptico de 3 mm tratado con capa anti-arañazos hard-coat y capa anti-UV. Transmisión luminosa ≈20 % (Categoría 3) para condiciones de sol intenso. Compatible con cascos con ranura de pantalla de 650 mm y sistema de fijación por botón lateral. Perfil plano sin curvatura añadida. Homologada ECE 22.06. No incluye mecanismo de fijación.',
    22.00, 45, 8, 'CAR-105', 'Oxford', @id_oxford,
    '5030009412113', 'https://catalogo.oxford.test/visor-tinted-650mm',
    'MotoRecambiosIberia', 'D1-05', 95, NULL, 650, 120, NULL, 1, 5
),
(
    'Cubre Barro Trasero Universal Acero Galvanizado',
    'Guardabarros trasero de acero galvanizado de uso universal.',
    'Guardabarros trasero fabricado en chapa de acero galvanizado de 1,5 mm de espesor con refuerzo en las esquinas para resistencia a impactos de piedras. Longitud total 380 mm, anchura 130 mm. Agujeros de fijación Ø 8 mm en patrón 50×100 mm. Pre-galvanizado con 85 µm de zinc electrolítico y acabado powder-coat negro mate. Compatible con ruedas traseras de 17" a 21" con guardabarros de 4 pernos. Tornillería M8 incluida.',
    34.00, 28, 5, 'CAR-106', 'Oxford', @id_oxford,
    '5030009330318', 'https://catalogo.oxford.test/rear-mudguard-steel',
    'DistribucionMotoES', 'D1-06', 880, NULL, 380, 130, NULL, 1, 5
),
(
    'Agarradero Pasajero Universal Aluminio Pulido',
    'Asa de pasajero en aluminio pulido CNC para naked universales.',
    'Agarradero de pasajero mecanizado en aluminio 6061-T6 con acabado pulido espejo. Diseño ergonómico con perfil tubular Ø 22 mm y curva de sujeción amplia para comodidad del pasajero. Fijación mediante 4 tornillos M8 en subchasis. Kit de montaje incluye espaciadores de aluminio para diferentes profundidades de subchasis (10-30 mm). Longitud total 340 mm. Peso 380 g.',
    48.00, 18, 3, 'CAR-107', 'Renthal', @id_renthal,
    '4897028712019', 'https://catalogo.renthal.test/passenger-grab-alu',
    'DistribucionMotoES', 'D1-07', 380, NULL, 340, 22, NULL, 1, 5
),
(
    'Protector Depósito Oxford Tank Bag Magnetico 23L',
    'Bolsa depósito magnética Oxford con capacidad de 23 litros.',
    'La Tank Bag Oxford Tank Roll de 23 L está fabricada en nylon 600D con base rígida con imanes N50 de neodimio (6 unidades de Ø 40 mm) para adherencia en depósitos de acero. Incluye bolsillo frontal transparente para mapa/smartphone, compartimento trasero para impermeable y correa ajustable adicional. Apertura lateral cremallera YKK. Carcasa extensible de 13 L a 23 L. Funda lluvia incluida.',
    75.00, 16, 3, 'CAR-108', 'Oxford', @id_oxford,
    '5030009058006', 'https://catalogo.oxford.test/tank-bag-23l-magnetic',
    'MotoRecambiosIberia', 'D1-08', 1200, 23000, NULL, NULL, NULL, 1, 5
),
(
    'Cadena Antirrobo Oxford Boss 12mm×1m',
    'Cadena antirrobo de eslabones cuadrados 12 mm con funda neopreno 1 m.',
    'La cadena Boss de Oxford está fabricada con eslabones de acero templado de sección cuadrada de 12 mm, el perfil más resistente al ataque con cizalla y cizalla de impacto. La funda de neopreno de 6 mm protege la pintura y el cromo de la moto al contacto. Compatible con los candados Oxford Boss y Tuff-Lock. Longitud: 1 metro; peso total: 4,3 kg. Se puede combinar con cualquier candado de arco Ø 10-16 mm.',
    85.00, 14, 3, 'CAR-109', 'Oxford', @id_oxford,
    '5030009080007', 'https://catalogo.oxford.test/boss-chain-12mm-1m',
    'MotoRecambiosIberia', 'D1-09', 4300, NULL, 1000, 12, NULL, 1, 5
),
(
    'Tope Moto Oxford Paddock Stand Trasero Universal',
    'Atril trasero Oxford para mantenimiento con almohadillas en Y.',
    'El atril trasero Oxford Paddock Stand presenta una estructura de acero tubular 32 mm lacado en negro con brazos en Y intercambiables (adaptadores para bobinas y eje pasante incluidos). Rango de altura regulable 210-310 mm. Almohadillas de poliuretano de alta densidad para proteger los brazos oscilantes. Capacidad de carga 220 kg. Anchura de base 600 mm para máxima estabilidad. Compatible con motos de 125 cc a 1400 cc.',
    52.00, 20, 4, 'CAR-110', 'Oxford', @id_oxford,
    '5030009098002', 'https://catalogo.oxford.test/paddock-stand-rear',
    'DistribucionMotoES', 'D1-10', 5200, NULL, 620, 600, NULL, 1, 5
);
