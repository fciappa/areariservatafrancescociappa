-- Migration 004 — add paid_at to collab_invoices

DROP PROCEDURE IF EXISTS migration_004;
DELIMITER $$
CREATE PROCEDURE migration_004()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'collab_invoices'
          AND COLUMN_NAME  = 'paid_at'
    ) THEN
        ALTER TABLE collab_invoices ADD COLUMN paid_at DATE NULL DEFAULT NULL COMMENT 'Data pagamento indicata dal collaboratore';
    END IF;
END$$
DELIMITER ;

CALL migration_004();
DROP PROCEDURE IF EXISTS migration_004;
