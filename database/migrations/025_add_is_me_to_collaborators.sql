-- Migration 025 - add collaborators.is_me flag (single selected collaborator representing "me")

DROP PROCEDURE IF EXISTS migration_025;
DELIMITER $$
CREATE PROCEDURE migration_025()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'collaborators'
    ) THEN
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'collaborators'
              AND COLUMN_NAME = 'is_me'
        ) THEN
            ALTER TABLE collaborators
                ADD COLUMN is_me BOOLEAN NOT NULL DEFAULT FALSE AFTER is_active;
        END IF;

                IF NOT EXISTS (
                        SELECT 1
                        FROM information_schema.STATISTICS
                        WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = 'collaborators'
                            AND INDEX_NAME = 'idx_collaborators_is_me'
                ) THEN
            CREATE INDEX idx_collaborators_is_me ON collaborators (is_me);
        END IF;

        IF (
            SELECT COUNT(*)
            FROM collaborators
            WHERE is_me = TRUE
        ) > 1 THEN
            UPDATE collaborators
            SET is_me = FALSE
            WHERE id NOT IN (
                SELECT id_to_keep FROM (
                    SELECT id AS id_to_keep
                    FROM collaborators
                    WHERE is_me = TRUE
                    ORDER BY updated_at DESC, id DESC
                    LIMIT 1
                ) keep_row
            );
        END IF;
    END IF;
END$$
DELIMITER ;

CALL migration_025();
DROP PROCEDURE IF EXISTS migration_025;
