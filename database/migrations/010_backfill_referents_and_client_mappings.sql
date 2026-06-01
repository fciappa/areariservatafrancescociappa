-- Migration 010 - backfill referents and client mappings from existing users/project assignments

DROP PROCEDURE IF EXISTS migration_010;
DELIMITER $$
CREATE PROCEDURE migration_010()
BEGIN
    -- 1) Ensure each referent user has an entry in referents (matched by unique email).
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'referents'
    ) THEN
        INSERT INTO referents (first_name, last_name, email, phone, fiscal_code, notes, is_active)
        SELECT
            CASE
                WHEN INSTR(u.username, '.') > 0 THEN
                    CONCAT(UCASE(LEFT(SUBSTRING_INDEX(u.username, '.', 1), 1)), LCASE(SUBSTRING(SUBSTRING_INDEX(u.username, '.', 1), 2)))
                ELSE
                    CONCAT(UCASE(LEFT(u.username, 1)), LCASE(SUBSTRING(u.username, 2)))
            END AS first_name,
            CASE
                WHEN INSTR(u.username, '.') > 0 THEN
                    CONCAT(UCASE(LEFT(SUBSTRING_INDEX(u.username, '.', -1), 1)), LCASE(SUBSTRING(SUBSTRING_INDEX(u.username, '.', -1), 2)))
                ELSE
                    'Referente'
            END AS last_name,
            u.email,
            NULL,
            NULL,
            'Creato automaticamente da migration_010 (backfill da users.role=referent).',
            u.is_active
        FROM users u
        LEFT JOIN referents r ON r.email = u.email
        WHERE u.role = 'referent'
          AND r.id IS NULL;

        -- 2) Backfill users.referent_id via email match if still null.
        UPDATE users u
        JOIN referents r ON r.email = u.email
        SET u.referent_id = r.id
        WHERE u.role = 'referent'
          AND u.referent_id IS NULL;
    END IF;

    -- 3) Derive client_referents from project_referents + projects relation.
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'project_referents'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'projects'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_referents'
    ) THEN
        INSERT INTO client_referents (client_id, user_id)
        SELECT DISTINCT p.client_id, pr.user_id
        FROM project_referents pr
        JOIN projects p ON p.id = pr.project_id
        LEFT JOIN client_referents cr
          ON cr.client_id = p.client_id
         AND cr.user_id = pr.user_id
        WHERE cr.id IS NULL;
    END IF;
END$$
DELIMITER ;

CALL migration_010();
DROP PROCEDURE IF EXISTS migration_010;
