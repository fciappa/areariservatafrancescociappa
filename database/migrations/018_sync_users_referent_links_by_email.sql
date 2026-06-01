-- Migration 018 - sync users.referent_id by normalized email and clean dangling links

DROP PROCEDURE IF EXISTS migration_018;
DELIMITER $$
CREATE PROCEDURE migration_018()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'referent_id'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'referents'
    ) THEN
        -- Link referent users by normalized email where link is missing.
        UPDATE users u
        JOIN referents r ON LOWER(TRIM(r.email)) = LOWER(TRIM(u.email))
        SET u.referent_id = r.id
        WHERE u.role = 'referent'
          AND u.referent_id IS NULL;

        -- Reset dangling links where referenced referent no longer exists.
        UPDATE users u
        LEFT JOIN referents r ON r.id = u.referent_id
        SET u.referent_id = NULL
        WHERE u.referent_id IS NOT NULL
          AND r.id IS NULL;
    END IF;
END$$
DELIMITER ;

CALL migration_018();
DROP PROCEDURE IF EXISTS migration_018;
