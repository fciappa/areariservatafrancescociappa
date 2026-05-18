-- Migration 002 — 2026-05-18
-- Add collab_invoices and collab_invoice_items tables

DROP PROCEDURE IF EXISTS migration_002;
DELIMITER $$
CREATE PROCEDURE migration_002()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'collab_invoices'
    ) THEN
        CREATE TABLE collab_invoices (
            id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
            collaborator_id INT UNSIGNED    NOT NULL,
            invoice_number  VARCHAR(50)     NOT NULL,
            invoice_date    DATE            NOT NULL,
            subtotal        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            tax_amount      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            total           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            notes           TEXT,
            status          ENUM('draft','sent','paid') NOT NULL DEFAULT 'draft',
            created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ci_collab FOREIGN KEY (collaborator_id) REFERENCES collaborators(id)
        );
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'collab_invoice_items'
    ) THEN
        CREATE TABLE collab_invoice_items (
            id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
            collab_invoice_id INT UNSIGNED    NOT NULL,
            collab_hour_id    INT UNSIGNED    NULL COMMENT 'Ref a collaborator_hours (opzionale)',
            description       VARCHAR(255)    NOT NULL,
            tariff_id         INT UNSIGNED    NOT NULL,
            hours             DECIMAL(5,2)    NOT NULL,
            hourly_rate       DECIMAL(10,2)   NOT NULL,
            tax_inclusive     BOOLEAN         NOT NULL DEFAULT FALSE,
            line_total        DECIMAL(10,2)   NOT NULL,
            created_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_cii_invoice FOREIGN KEY (collab_invoice_id) REFERENCES collab_invoices(id) ON DELETE CASCADE
        );
    END IF;
END$$
DELIMITER ;

CALL migration_002();
DROP PROCEDURE IF EXISTS migration_002;
