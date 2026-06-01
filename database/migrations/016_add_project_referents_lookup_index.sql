-- Migration 016 - add lookup index for project_referents by user

DROP PROCEDURE IF EXISTS migration_016;
DELIMITER $$
CREATE PROCEDURE migration_016()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'project_referents'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'project_referents'
          AND INDEX_NAME = 'idx_pr_user_project'
    ) THEN
        CREATE INDEX idx_pr_user_project ON project_referents (user_id, project_id);
    END IF;
END$$
DELIMITER ;

CALL migration_016();
DROP PROCEDURE IF EXISTS migration_016;
