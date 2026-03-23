-- Productos de prueba para es21plus
-- Ejecutar: docker exec -i inventario_motos_db mysql -uroot -plugi21plus inventario_motos < database/seed_productos.sql

INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, codigo_ref, categoria_id) VALUES
('Pastilla de freno delantera Brembo', 'Pastillas de freno de alto rendimiento para disco delantero. Compatibles con la mayoria de motos deportivas.', 28.50, 15, 5, 'FRE-001', 1),
('Disco de freno flotante 310mm', 'Disco de freno flotante de acero inoxidable, diametro 310mm. Alta resistencia al calor.', 89.99, 4, 5, 'FRE-002', 1),
('Filtro de aceite HiFlo Honda', 'Filtro de aceite de alta filtracion compatible con motores Honda 4T. Rosca M20x1.5.', 8.75, 30, 10, 'MOT-001', 2),
('Bujia NGK Iridium CR8EIX', 'Bujia de iridio NGK para motores 4 tiempos. Mayor durabilidad y arranque en frio mejorado.', 12.40, 22, 8, 'MOT-002', 2),
('Aceite Motul 300V 10W-40 1L', 'Aceite de motor 100% sintetico de competicion. Maxima proteccion a altas temperaturas.', 19.95, 3, 6, 'MOT-003', 2),
('Kit cadena DID 520 VX3 Gold', 'Kit completo de transmision (cadena + pinon conductor + corona) dorado. Para deportivas 600cc.', 129.90, 8, 3, 'TRA-001', 3),
('Pinon conductor 15T acero', 'Pinon de acero tratado termicamente, 15 dientes, paso 525. Compatible con Kawasaki, Suzuki, Yamaha.', 18.50, 12, 4, 'TRA-002', 3),
('Regulador rectificador universal 12V', 'Regulador de voltaje 12V 35A para motos de 4 tiempos. Instalacion plug and play en la mayoria de modelos.', 34.90, 2, 5, 'ELE-001', 4),
('Bateria Yuasa YTZ10S 12V 8.6Ah', 'Bateria AGM sin mantenimiento. Alta potencia de arranque en frio (190A). Para deportivas y naked.', 79.95, 6, 3, 'ELE-002', 4),
('Carenado lateral izquierdo Yamaha R6 2020', 'Carenado ABS de alta calidad para Yamaha R6 modelo 2020. Prepintado en blanco, listo para pintar.', 145.00, 1, 2, 'CAR-001', 5);
