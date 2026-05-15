-- Migration 001 — 2026-05-15
-- Add project_id column to my_work_hours

DROP PROCEDURE IF EXISTS migration_001;
DELIMITER $$
CREATE PROCEDURE migration_001()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'my_work_hours'
          AND COLUMN_NAME  = 'project_id'
    ) THEN
        ALTER TABLE my_work_hours
            ADD COLUMN project_id INT UNSIGNED NULL
                COMMENT 'Progetto di riferimento'
                AFTER client_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME        = 'my_work_hours'
          AND CONSTRAINT_NAME   = 'fk_mwh_project'
    ) THEN
        ALTER TABLE my_work_hours
            ADD CONSTRAINT fk_mwh_project
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;

CALL migration_001();
DROP PROCEDURE IF EXISTS migration_001;
