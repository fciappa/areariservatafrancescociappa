-- Migration 014 - normalize users role links (collaborator_id/referent_id)

DROP PROCEDURE IF EXISTS migration_014;
DELIMITER $$
CREATE PROCEDURE migration_014()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
    ) THEN
        -- collaborator_id is valid only for collaborator users.
        UPDATE users
        SET collaborator_id = NULL
        WHERE role IN ('admin', 'referent')
          AND collaborator_id IS NOT NULL;

        -- referent_id logic applies only if column exists (introduced in migration_009).
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'referent_id'
        ) THEN
            -- referent_id is valid only for referent users.
            UPDATE users
            SET referent_id = NULL
            WHERE role <> 'referent'
              AND referent_id IS NOT NULL;

            -- Backfill referent_id for referent users via email match when possible.
            IF EXISTS (
                SELECT 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'referents'
            ) THEN
                UPDATE users u
                JOIN referents r ON r.email = u.email
                SET u.referent_id = r.id
                WHERE u.role = 'referent'
                  AND u.referent_id IS NULL;
            END IF;
        END IF;
    END IF;
END$$
DELIMITER ;

CALL migration_014();
DROP PROCEDURE IF EXISTS migration_014;
