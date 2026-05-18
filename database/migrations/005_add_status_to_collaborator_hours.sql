-- Migration 005 — add status to collaborator_hours

DROP PROCEDURE IF EXISTS migration_005;
DELIMITER $$
CREATE PROCEDURE migration_005()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'collaborator_hours'
          AND COLUMN_NAME  = 'status'
    ) THEN
        ALTER TABLE collaborator_hours
            ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved'
            COMMENT 'pending = inserito dal collaboratore, in attesa di approvazione admin';
    END IF;
END$$
DELIMITER ;

CALL migration_005();
DROP PROCEDURE IF EXISTS migration_005;
