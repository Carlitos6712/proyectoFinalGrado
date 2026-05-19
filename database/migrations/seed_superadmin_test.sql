-- =============================================================
-- Seed de datos de prueba para el panel superadmin
-- @author Carlos Vico
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Negocios adicionales ─────────────────────────────────────
INSERT INTO businesses (id, name, slug, contact_email, phone, address, plan, plan_expires_at, is_active, onboarding_completed, created_at) VALUES
(2, 'Moto Sport Valencia',  'moto-sport-valencia',  'info@motosport.es',    '+34 963 111 222', 'Av. del Puerto 45, Valencia',    'pro',   '2026-12-31', 1, 1, DATE_SUB(NOW(), INTERVAL 90 DAY)),
(3, 'Taller Bikes Madrid',  'taller-bikes-madrid',  'hola@bikersmadrid.es', '+34 912 333 444', 'Calle Alcala 200, Madrid',       'basic', '2026-09-15', 1, 1, DATE_SUB(NOW(), INTERVAL 60 DAY)),
(5, 'Enduro Club Sevilla',  'enduro-club-sevilla',  'club@enduro.es',       '+34 954 555 666', 'Ronda de los Tejares 8, Sevilla','free',  '2026-07-01', 0, 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 'Repuestos Moto Norte', 'repuestos-moto-norte', 'ventas@motonorte.es',  '+34 944 777 888', 'Gran Via 55, Bilbao',            'basic', '2026-08-20', 1, 1, DATE_SUB(NOW(), INTERVAL 30 DAY));

-- ─── Empleados nuevos negocios ───────────────────────────────
INSERT INTO employees (business_id, name, email, password, role, position, is_active, created_at) VALUES
(2, 'Rafael Torres',  'rafael@motosport.es',   '$2y$12$0qL.W5NaSPT0ViUxEy/ZQux.9Wxa/BMQRncU5.4cUlPs0YJVFt8Hi', 'admin',    'Jefe de taller',  1, DATE_SUB(NOW(), INTERVAL 90 DAY)),
(2, 'Marta Perez',    'marta@motosport.es',    '$2y$12$0qL.W5NaSPT0ViUxEy/ZQux.9Wxa/BMQRncU5.4cUlPs0YJVFt8Hi', 'employee', 'Mecanica',        1, DATE_SUB(NOW(), INTERVAL 85 DAY)),
(3, 'Jorge Blanco',   'jorge@bikersmadrid.es', '$2y$12$0qL.W5NaSPT0ViUxEy/ZQux.9Wxa/BMQRncU5.4cUlPs0YJVFt8Hi', 'admin',    'Propietario',     1, DATE_SUB(NOW(), INTERVAL 60 DAY)),
(5, 'Ana Ruiz',       'ana@enduro.es',         '$2y$12$0qL.W5NaSPT0ViUxEy/ZQux.9Wxa/BMQRncU5.4cUlPs0YJVFt8Hi', 'admin',    'Administradora',  1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 'Luis Fernandez', 'luis@motonorte.es',     '$2y$12$0qL.W5NaSPT0ViUxEy/ZQux.9Wxa/BMQRncU5.4cUlPs0YJVFt8Hi', 'admin',    'Gerente',         1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(6, 'Sofia Gil',      'sofia@motonorte.es',    '$2y$12$0qL.W5NaSPT0ViUxEy/ZQux.9Wxa/BMQRncU5.4cUlPs0YJVFt8Hi', 'employee', 'Recepcionista',   1, DATE_SUB(NOW(), INTERVAL 28 DAY));

-- ─── Tickets de soporte ───────────────────────────────────────
INSERT INTO support_tickets (business_id, employee_id, ticket_number, subject, status, priority, created_at, updated_at) VALUES
(1, 1,  'TK-2026-00001', 'No puedo exportar el inventario a PDF',              'resolved',    'normal', DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(2, 12, 'TK-2026-00002', 'Error al subir logo de empresa',                     'closed',      'low',    DATE_SUB(NOW(), INTERVAL 38 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY)),
(3, 14, 'TK-2026-00003', 'El grafico de movimientos no carga datos',           'resolved',    'high',   DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY)),
(1, 3,  'TK-2026-00004', 'Solicitud de cambio de plan a Pro',                  'in_progress', 'normal', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(6, 17, 'TK-2026-00005', 'Empleado no recibe email de invitacion',             'open',        'high',   DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 9,  'TK-2026-00006', 'Como añadir categorias personalizadas',              'open',        'low',    DATE_SUB(NOW(), INTERVAL 5  DAY), DATE_SUB(NOW(), INTERVAL 5  DAY)),
(5, 15, 'TK-2026-00007', 'Acceso denegado al modulo de movimientos',           'open',        'urgent', DATE_SUB(NOW(), INTERVAL 2  DAY), DATE_SUB(NOW(), INTERVAL 2  DAY)),
(2, 12, 'TK-2026-00008', 'Consulta sobre limite de empleados en plan Pro',     'open',        'normal', DATE_SUB(NOW(), INTERVAL 1  DAY), DATE_SUB(NOW(), INTERVAL 1  DAY));

-- ─── Mensajes de ticket ───────────────────────────────────────
INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, created_at) VALUES
(1, 'business',   1,  'Al exportar a PDF el navegador muestra pagina en blanco. Probado en Firefox y Chrome.', DATE_SUB(NOW(), INTERVAL 45 DAY)),
(1, 'superadmin', 1,  'Identificado el problema con PDF para mas de 200 productos. Fix publicado esta semana.', DATE_SUB(NOW(), INTERVAL 43 DAY)),
(1, 'business',   1,  'Perfecto, gracias. Esperamos el fix.',                                                    DATE_SUB(NOW(), INTERVAL 42 DAY)),
(1, 'superadmin', 1,  'Fix desplegado. Confirma que ahora funciona correctamente.',                              DATE_SUB(NOW(), INTERVAL 40 DAY)),
(2, 'business',   12, 'El logo de 3.2 MB da error. Cual es el limite?',                                         DATE_SUB(NOW(), INTERVAL 38 DAY)),
(2, 'superadmin', 1,  'Limite 5 MB. Solo JPG, PNG o WebP. Si persiste, comprime el archivo.',                   DATE_SUB(NOW(), INTERVAL 37 DAY)),
(2, 'business',   12, 'Era formato HEIC. Ya funciona, gracias.',                                                 DATE_SUB(NOW(), INTERVAL 36 DAY)),
(3, 'business',   14, 'El grafico de barras del dashboard siempre aparece vacio aunque haya movimientos.',       DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3, 'superadmin', 1,  'Corregido: faltaba session_start() en la API del grafico. Desplegado.',                  DATE_SUB(NOW(), INTERVAL 26 DAY)),
(4, 'business',   3,  'Queremos ampliar el equipo y necesitamos mas funcionalidades. Podemos cambiar a Pro?',    DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4, 'superadmin', 1,  'Por supuesto. Te generamos factura por la diferencia proporcional.',                      DATE_SUB(NOW(), INTERVAL 18 DAY)),
(5, 'business',   17, 'Añadi un empleado pero dice que no recibio el email con la contrasena temporal.',         DATE_SUB(NOW(), INTERVAL 12 DAY)),
(6, 'business',   9,  'Hola, soy nuevo. Como puedo crear categorias para organizar mis productos?',             DATE_SUB(NOW(), INTERVAL 5  DAY)),
(7, 'business',   15, 'Un empleado obtiene acceso denegado en Movimientos. Su rol es employee.',                 DATE_SUB(NOW(), INTERVAL 2  DAY)),
(8, 'business',   12, 'Cuantos empleados se pueden tener en plan Pro? Hay limite de productos?',                 DATE_SUB(NOW(), INTERVAL 1  DAY));

-- ─── Facturas ─────────────────────────────────────────────────
INSERT INTO invoices (business_id, invoice_number, amount, status, period_start, period_end, notes, paid_at, created_at) VALUES
(1, 'FAC-2026-00001', 29.00, 'paid',      '2026-01-01', '2026-01-31', 'Suscripcion Basic - enero 2026',           DATE_SUB(NOW(), INTERVAL 120 DAY), DATE_SUB(NOW(), INTERVAL 125 DAY)),
(1, 'FAC-2026-00002', 29.00, 'paid',      '2026-02-01', '2026-02-28', 'Suscripcion Basic - febrero 2026',         DATE_SUB(NOW(), INTERVAL 90  DAY), DATE_SUB(NOW(), INTERVAL 95  DAY)),
(2, 'FAC-2026-00003', 79.00, 'paid',      '2026-01-01', '2026-03-31', 'Suscripcion Pro - Q1 2026',                DATE_SUB(NOW(), INTERVAL 85  DAY), DATE_SUB(NOW(), INTERVAL 90  DAY)),
(3, 'FAC-2026-00004', 29.00, 'paid',      '2026-02-01', '2026-02-28', 'Suscripcion Basic - febrero 2026',         DATE_SUB(NOW(), INTERVAL 70  DAY), DATE_SUB(NOW(), INTERVAL 72  DAY)),
(1, 'FAC-2026-00005', 29.00, 'paid',      '2026-03-01', '2026-03-31', 'Suscripcion Basic - marzo 2026',           DATE_SUB(NOW(), INTERVAL 60  DAY), DATE_SUB(NOW(), INTERVAL 62  DAY)),
(6, 'FAC-2026-00006', 29.00, 'paid',      '2026-03-01', '2026-03-31', 'Suscripcion Basic - marzo 2026',           DATE_SUB(NOW(), INTERVAL 50  DAY), DATE_SUB(NOW(), INTERVAL 52  DAY)),
(2, 'FAC-2026-00007', 79.00, 'paid',      '2026-04-01', '2026-06-30', 'Suscripcion Pro - Q2 2026',                DATE_SUB(NOW(), INTERVAL 40  DAY), DATE_SUB(NOW(), INTERVAL 45  DAY)),
(3, 'FAC-2026-00008', 29.00, 'paid',      '2026-04-01', '2026-04-30', 'Suscripcion Basic - abril 2026',           DATE_SUB(NOW(), INTERVAL 35  DAY), DATE_SUB(NOW(), INTERVAL 37  DAY)),
(1, 'FAC-2026-00009', 29.00, 'paid',      '2026-04-01', '2026-04-30', 'Suscripcion Basic - abril 2026',           DATE_SUB(NOW(), INTERVAL 33  DAY), DATE_SUB(NOW(), INTERVAL 35  DAY)),
(6, 'FAC-2026-00010', 29.00, 'paid',      '2026-04-01', '2026-04-30', 'Suscripcion Basic - abril 2026',           DATE_SUB(NOW(), INTERVAL 30  DAY), DATE_SUB(NOW(), INTERVAL 32  DAY)),
(1, 'FAC-2026-00011', 29.00, 'pending',   '2026-05-01', '2026-05-31', 'Suscripcion Basic - mayo 2026',            NULL,                              DATE_SUB(NOW(), INTERVAL 5   DAY)),
(3, 'FAC-2026-00012', 29.00, 'pending',   '2026-05-01', '2026-05-31', 'Suscripcion Basic - mayo 2026',            NULL,                              DATE_SUB(NOW(), INTERVAL 4   DAY)),
(4, 'FAC-2026-00013',  0.00, 'paid',      '2026-05-01', '2026-05-31', 'Plan Free - sin cargo',                    NOW(),                             DATE_SUB(NOW(), INTERVAL 3   DAY)),
(6, 'FAC-2026-00014', 29.00, 'cancelled', '2026-05-01', '2026-05-31', 'Suscripcion Basic mayo (anulada)',          NULL,                              DATE_SUB(NOW(), INTERVAL 2   DAY));

-- ─── Campañas de email ────────────────────────────────────────
INSERT INTO email_campaigns (subject, body_html, target_plan, target_status, status, scheduled_at, sent_at, recipients_count, created_at) VALUES
('Bienvenida a es21plus',
 '<h2>Bienvenido a es21plus!</h2><p>Ya tienes acceso completo a tu panel. Primeros pasos: añade categorias, marcas y productos.</p>',
 'all', 'active', 'sent', NULL, DATE_SUB(NOW(), INTERVAL 60 DAY), 6, DATE_SUB(NOW(), INTERVAL 61 DAY)),
('Nuevo: Exportacion PDF del inventario',
 '<h2>Novedad en es21plus!</h2><p>Ya puedes exportar tu inventario completo en PDF con un solo clic desde Productos.</p>',
 'basic', 'active', 'sent', NULL, DATE_SUB(NOW(), INTERVAL 30 DAY), 3, DATE_SUB(NOW(), INTERVAL 31 DAY)),
('[Accion requerida] Tu prueba termina en 7 dias',
 '<h2>Tu prueba gratuita esta a punto de terminar</h2><p>Selecciona un plan para continuar sin interrupciones.</p>',
 'free', 'active', 'sent', NULL, DATE_SUB(NOW(), INTERVAL 10 DAY), 2, DATE_SUB(NOW(), INTERVAL 10 DAY)),
('Novedades mayo 2026 - Onboarding y personalizacion',
 '<h2>Novedades de mayo 2026</h2><ul><li>Onboarding guiado en 2 pasos</li><li>Perfil de empresa con color de marca</li><li>Campañas de email masivo</li><li>Mejoras multi-tenant</li></ul>',
 'all', 'active', 'sent', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), 6, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('Oferta: Plan Pro al 20% este mes',
 '<h2>Oferta exclusiva para clientes Basic</h2><p>Actualiza al plan Pro con 20% de descuento este mes.</p>',
 'basic', 'active', 'scheduled', DATE_ADD(NOW(), INTERVAL 3 DAY), NULL, 0, NOW()),
('Encuesta de satisfaccion',
 '<h2>Como va tu experiencia?</h2><p>Dedicanos 2 minutos para mejorar el servicio.</p>',
 'all', 'active', 'draft', NULL, NULL, 0, NOW());

-- ─── Anuncios ─────────────────────────────────────────────────
INSERT INTO announcements (title, body, is_active, created_at) VALUES
('Mantenimiento programado - domingo 25 mayo 02:00-04:00',
 'El sistema estara en mantenimiento el domingo 25 de mayo entre las 2:00 y las 4:00. El acceso puede ser intermitente.',
 0, DATE_SUB(NOW(), INTERVAL 10 DAY)),
('Nueva funcion: Exportacion PDF disponible',
 'Ya puedes exportar tu inventario en PDF desde Productos > Exportar.',
 0, DATE_SUB(NOW(), INTERVAL 5 DAY)),
('Bienvenido a la version 2.0 de es21plus',
 'Hemos lanzado la v2.0 con onboarding guiado, personalizacion de marca, campanas de email y mejoras multi-tenant.',
 1, NOW());

-- ─── Mensajes de contacto ─────────────────────────────────────
INSERT INTO contact_messages (business_id, sender_name, sender_email, subject, message, is_read, replied_at, created_at) VALUES
(NULL, 'Marcos Iglesias', 'marcos@ejemplo.es',   'Consulta sobre precios y planes',
 'Somos un taller con 3 mecanicos. Que plan recomendais? Hay descuento por pago anual?',
 1, DATE_SUB(NOW(), INTERVAL 28 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2,    'Rafael Torres',   'rafael@motosport.es',  'Fallo al importar CSV masivo',
 'Al importar un CSV con 500 productos el sistema carga y luego da error 500. El fichero pesa 45 KB.',
 1, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY)),
(NULL, 'Patricia Vega',   'patricia@vega.com',    'Peticion de demo personalizada',
 'Tenemos una cadena de 4 talleres y queremos una demo para ver si es21plus cubre nuestras necesidades.',
 1, NULL, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3,    'Jorge Blanco',    'jorge@bikersmadrid.es','Integracion con TPV',
 'Usamos un TPV Verifone. Existe alguna API para sincronizar ventas con el inventario?',
 0, NULL, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(NULL, 'Elena Castro',    'elena@tallermoto.es',  'No puedo registrarme',
 'Al crear cuenta aparece el mensaje El registro esta temporalmente cerrado. Cuando estara disponible?',
 0, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6,    'Luis Fernandez',  'luis@motonorte.es',    'Solicitud de factura en papel',
 'Necesitamos facturas en papel para contabilidad. Podeis enviarlas por correo postal ademas del PDF?',
 0, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ─── Intentos de login fallidos ───────────────────────────────
INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES
('185.220.101.45', 'admin@tallerdemo.local',  DATE_SUB(NOW(), INTERVAL 5 DAY)),
('185.220.101.45', 'admin@tallerdemo.local',  DATE_SUB(NOW(), INTERVAL 5 DAY)),
('185.220.101.45', 'admin@tallerdemo.local',  DATE_SUB(NOW(), INTERVAL 5 DAY)),
('185.220.101.45', 'admin@tallerdemo.local',  DATE_SUB(NOW(), INTERVAL 5 DAY)),
('185.220.101.45', 'admin@tallerdemo.local',  DATE_SUB(NOW(), INTERVAL 5 DAY)),
('192.168.1.100',  'root@tallerdemo.local',   DATE_SUB(NOW(), INTERVAL 3 DAY)),
('192.168.1.100',  'superadmin@es21plus.com', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('10.0.0.55',      'jorge@bikersmadrid.es',   DATE_SUB(NOW(), INTERVAL 1 DAY)),
('10.0.0.55',      'jorge@bikersmadrid.es',   DATE_SUB(NOW(), INTERVAL 1 DAY)),
('77.111.245.23',  'info@motosport.es',        NOW());

-- ─── Activity logs ────────────────────────────────────────────
INSERT INTO activity_logs (business_id, employee_id, action, entity, entity_id, ip_address, metadata, is_critical, created_at) VALUES
(1,    1,  'login',           'employee',  1,  '192.168.1.10', NULL, 0, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(1,    3,  'producto.crear',  'productos', 1,  '192.168.1.10', '{"nombre":"Filtro de aceite Honda CBR"}', 0, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1,    1,  'producto.editar', 'productos', 5,  '192.168.1.10', '{"campo":"precio","antes":8.50,"despues":9.90}', 0, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2,    12, 'login',           'employee',  12, '80.58.22.44',  NULL, 0, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2,    12, 'marca.crear',     'marcas',    1,  '80.58.22.44',  '{"nombre":"Akrapovic"}', 0, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3,    14, 'login',           'employee',  14, '81.47.5.200',  NULL, 0, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1,    1,  'movimiento',      'movimientos',1, '192.168.1.10', '{"tipo":"entrada","cantidad":20}', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6,    17, 'login',           'employee',  17, '91.125.8.14',  NULL, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4,    9,  'onboarding',      'businesses',4,  '88.19.4.201',  '{"step":"completed"}', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(NULL, NULL,'settings.update','system',   NULL,'127.0.0.1',   '{"key":"registration_open","value":"1"}', 1, NOW());

-- ─── Auditoria ────────────────────────────────────────────────
INSERT INTO auditoria (tabla, registro_id, accion, datos_anteriores, datos_nuevos, usuario, ip, fecha) VALUES
('productos',  1,  'crear',     NULL, '{"nombre":"Filtro de aceite","precio":8.50,"stock":20}', 'Admin Taller Demo', '192.168.1.10', DATE_SUB(NOW(), INTERVAL 80 DAY)),
('productos',  2,  'crear',     NULL, '{"nombre":"Pastillas de freno","precio":18.00,"stock":10}', 'Admin Taller Demo', '192.168.1.10', DATE_SUB(NOW(), INTERVAL 79 DAY)),
('productos',  1,  'actualizar','{"precio":8.50}', '{"precio":9.90}', 'Admin Taller Demo', '192.168.1.10', DATE_SUB(NOW(), INTERVAL 30 DAY)),
('categorias', 1,  'crear',     NULL, '{"nombre":"Repuestos","descripcion":"Piezas de recambio"}', 'Admin Taller Demo', '192.168.1.10', DATE_SUB(NOW(), INTERVAL 90 DAY)),
('marcas',     1,  'crear',     NULL, '{"nombre":"Brembo","descripcion":"Frenos y pastillas"}', 'Admin Taller Demo', '192.168.1.10', DATE_SUB(NOW(), INTERVAL 88 DAY)),
('marcas',     5,  'actualizar','{"nombre":"Honda Motors"}', '{"nombre":"Honda"}', 'Carlos Mecanico', '192.168.1.11', DATE_SUB(NOW(), INTERVAL 20 DAY)),
('productos',  15, 'eliminar',  '{"nombre":"Guante viejo modelo","stock":0}', NULL, 'Admin Taller Demo', '192.168.1.10', DATE_SUB(NOW(), INTERVAL 10 DAY)),
('categorias', 3,  'actualizar','{"nombre":"Accesorios"}', '{"nombre":"Accesorios y equipamiento"}', 'Laura Supervisora', '192.168.1.12', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- ─── Notificaciones superadmin adicionales ────────────────────
INSERT INTO superadmin_notifications (type, title, body, is_read, created_at) VALUES
('ticket',   'Nuevo ticket urgente',       'Enduro Club Sevilla: acceso denegado al modulo movimientos (urgente)', 0, DATE_SUB(NOW(), INTERVAL 2  DAY)),
('business', 'Nuevo negocio registrado',   'Enduro Club Sevilla se registro con plan Free',                        0, DATE_SUB(NOW(), INTERVAL 10 DAY)),
('billing',  'Factura pendiente de pago',  'Taller Bikes Madrid: FAC-2026-00012 pendiente hace 4 dias',            0, DATE_SUB(NOW(), INTERVAL 4  DAY)),
('security', 'Multiples intentos fallidos','5 intentos fallidos desde IP 185.220.101.45',                          0, DATE_SUB(NOW(), INTERVAL 5  DAY)),
('campaign', 'Campana enviada',            'Novedades mayo 2026 enviada a 6 destinatarios correctamente',          1, DATE_SUB(NOW(), INTERVAL 2  DAY));

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'SEED COMPLETADO' AS resultado;
