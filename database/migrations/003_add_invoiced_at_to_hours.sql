-- Migration 003 — add invoiced_at to hours tables

DROP PROCEDURE IF EXISTS migration_003;
DELIMITER $$
CREATE PROCEDURE migration_003()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'my_work_hours'
          AND COLUMN_NAME  = 'invoiced_at'
    ) THEN
        ALTER TABLE my_work_hours ADD COLUMN invoiced_at TIMESTAMP NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'collaborator_hours'
          AND COLUMN_NAME  = 'invoiced_at'
    ) THEN
        ALTER TABLE collaborator_hours ADD COLUMN invoiced_at TIMESTAMP NULL DEFAULT NULL;
    END IF;
END$$
DELIMITER ;

CALL migration_003();
DROP PROCEDURE IF EXISTS migration_003;
