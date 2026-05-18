-- ============================================================
-- Migración: Seguridad — fuerza bruta, sesiones, auditoría crítica
-- @author Carlos Vico
-- ============================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    email        VARCHAR(150),
    attempted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip   (ip_address),
    INDEX idx_time (attempted_at)
);

CREATE TABLE IF NOT EXISTS session_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_type    ENUM('superadmin','employee') NOT NULL,
    user_id      INT          NOT NULL,
    business_id  INT          NULL,
    ip_address   VARCHAR(45)  NULL,
    user_agent   VARCHAR(500) NULL,
    action       ENUM('login','logout','expired') NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE activity_logs
    ADD COLUMN IF NOT EXISTS metadata    JSON             NULL    AFTER ip_address,
    ADD COLUMN IF NOT EXISTS is_critical TINYINT(1)       DEFAULT 0 AFTER metadata;
