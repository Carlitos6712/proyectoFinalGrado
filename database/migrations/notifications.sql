-- ============================================================
-- Migración: Notificaciones internas del superadmin
-- @author Carlos Vico
-- ============================================================

CREATE TABLE IF NOT EXISTS superadmin_notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    type       VARCHAR(100)  NOT NULL,
    title      VARCHAR(200)  NOT NULL,
    body       TEXT,
    link       VARCHAR(255),
    is_read    TINYINT(1)    DEFAULT 0,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
);
