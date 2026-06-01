-- Migration 017 - normalize referents email (trim/lowercase) safely

DROP PROCEDURE IF EXISTS migration_017;
DELIMITER $$
CREATE PROCEDURE migration_017()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'referents'
    ) THEN
        UPDATE referents r
        LEFT JOIN referents r2
          ON r2.id <> r.id
         AND LOWER(TRIM(r2.email)) = LOWER(TRIM(r.email))
        SET r.email = LOWER(TRIM(r.email))
        WHERE r2.id IS NULL
          AND r.email <> LOWER(TRIM(r.email));
    END IF;
END$$
DELIMITER ;

CALL migration_017();
DROP PROCEDURE IF EXISTS migration_017;
