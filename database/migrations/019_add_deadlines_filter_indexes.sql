-- Migration 019 - add optimized indexes for client_deadlines filters

DROP PROCEDURE IF EXISTS migration_019;
DELIMITER $$
CREATE PROCEDURE migration_019()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
    ) THEN
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'client_deadlines'
              AND INDEX_NAME = 'idx_cd_client_active_due_date'
        ) THEN
            CREATE INDEX idx_cd_client_active_due_date
                ON client_deadlines (client_id, is_active, due_date);
        END IF;

        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'client_deadlines'
              AND COLUMN_NAME = 'project_id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'client_deadlines'
              AND INDEX_NAME = 'idx_cd_project_active_due_date'
        ) THEN
            CREATE INDEX idx_cd_project_active_due_date
                ON client_deadlines (project_id, is_active, due_date);
        END IF;
    END IF;
END$$
DELIMITER ;

CALL migration_019();
DROP PROCEDURE IF EXISTS migration_019;
