-- =============================================================
-- Migración: Tickets de Soporte + Facturación
--
-- Tablas: support_tickets, ticket_messages, ticket_attachments,
--         plan_prices, invoices
--
-- @author  Carlos Vico
-- @version 1.0.0
-- =============================================================

-- Support tickets
CREATE TABLE IF NOT EXISTS support_tickets (
    id             INT           AUTO_INCREMENT PRIMARY KEY,
    business_id    INT           NOT NULL,
    employee_id    INT           NULL,
    ticket_number  VARCHAR(20)   NOT NULL UNIQUE,
    subject        VARCHAR(255)  NOT NULL,
    status         ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    priority       ENUM('low','normal','high','urgent')           NOT NULL DEFAULT 'normal',
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at      TIMESTAMP     NULL DEFAULT NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_messages (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    ticket_id    INT          NOT NULL,
    sender_type  ENUM('business','superadmin') NOT NULL,
    sender_id    INT          NOT NULL,
    message      TEXT         NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_attachments (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    message_id    INT          NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    size          INT          NOT NULL DEFAULT 0,
    uploaded_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Plan pricing table
CREATE TABLE IF NOT EXISTS plan_prices (
    id            INT              AUTO_INCREMENT PRIMARY KEY,
    plan          ENUM('free','basic','pro') NOT NULL UNIQUE,
    monthly_price DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
    annual_price  DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
    features      JSON,
    updated_at    TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO plan_prices (plan, monthly_price, annual_price, features) VALUES
('free',   0.00,   0.00,   '{"max_products": 50, "max_employees": 2, "reports": false}'),
('basic', 29.99, 299.99,   '{"max_products": 500, "max_employees": 10, "reports": true}'),
('pro',   79.99, 799.99,   '{"max_products": -1, "max_employees": -1, "reports": true}')
ON DUPLICATE KEY UPDATE updated_at = updated_at;

-- Invoices
CREATE TABLE IF NOT EXISTS invoices (
    id             INT           AUTO_INCREMENT PRIMARY KEY,
    business_id    INT           NOT NULL,
    invoice_number VARCHAR(20)   NOT NULL UNIQUE,
    amount         DECIMAL(10,2) NOT NULL,
    status         ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    period_start   DATE          NULL,
    period_end     DATE          NULL,
    notes          TEXT          NULL,
    paid_at        TIMESTAMP     NULL DEFAULT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
