-- ============================================================
-- Migración: Configuración global del sistema
-- @author Carlos Vico
-- ============================================================

CREATE TABLE IF NOT EXISTS system_settings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100)  NOT NULL UNIQUE,
    value       TEXT,
    type        ENUM('string','integer','boolean','json') DEFAULT 'string',
    description VARCHAR(255),
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_settings (setting_key, value, type, description) VALUES
('app_name',           'es21plus', 'string',  'Nombre de la aplicación'),
('registration_open',  '1',        'boolean', 'Permitir registro público'),
('maintenance_mode',   '0',        'boolean', 'Modo mantenimiento'),
('maintenance_message','',         'string',  'Mensaje de mantenimiento'),
('default_plan',       'free',     'string',  'Plan por defecto en registro'),
('trial_days',         '14',       'integer', 'Días de prueba al registrarse'),
('max_file_upload_mb', '5',        'integer', 'Tamaño máximo de subida en MB'),
('superadmin_email',   '',         'string',  'Email de notificaciones'),
('smtp_host',          '',         'string',  'Servidor SMTP'),
('smtp_port',          '587',      'integer', 'Puerto SMTP'),
('smtp_user',          '',         'string',  'Usuario SMTP'),
('smtp_password',      '',         'string',  'Contraseña SMTP cifrada'),
('smtp_from_name',     'es21plus', 'string',  'Nombre remitente de correos')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
