-- Migration 022 - add index for projects status/client filters

DROP PROCEDURE IF EXISTS migration_022;
DELIMITER $$
CREATE PROCEDURE migration_022()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'projects'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'projects'
          AND INDEX_NAME = 'idx_projects_client_status_active'
    ) THEN
        CREATE INDEX idx_projects_client_status_active ON projects (client_id, status, is_active);
    END IF;
END$$
DELIMITER ;

CALL migration_022();
DROP PROCEDURE IF EXISTS migration_022;
