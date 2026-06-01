-- Migration 013 - add performance indexes for referent/user and deadlines queries

DROP PROCEDURE IF EXISTS migration_013;
DELIMITER $$
CREATE PROCEDURE migration_013()
BEGIN
    -- users: common filters/join paths (role/is_active, referent_id)
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
    ) THEN
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND INDEX_NAME = 'idx_users_role_active'
        ) THEN
            CREATE INDEX idx_users_role_active ON users (role, is_active);
        END IF;

        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'referent_id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND INDEX_NAME = 'idx_users_referent_id'
        ) THEN
            CREATE INDEX idx_users_referent_id ON users (referent_id);
        END IF;
    END IF;

    -- client_deadlines: common API filters/sorts (client/project/active + due_date)
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
    ) THEN
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'client_deadlines'
              AND COLUMN_NAME = 'project_id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'client_deadlines'
              AND INDEX_NAME = 'idx_cd_client_project_due_date'
        ) THEN
            CREATE INDEX idx_cd_client_project_due_date
                ON client_deadlines (client_id, project_id, due_date);
        END IF;

        IF NOT EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'client_deadlines'
              AND INDEX_NAME = 'idx_cd_active_due_date'
        ) THEN
            CREATE INDEX idx_cd_active_due_date
                ON client_deadlines (is_active, due_date);
        END IF;
    END IF;
END$$
DELIMITER ;

CALL migration_013();
DROP PROCEDURE IF EXISTS migration_013;
