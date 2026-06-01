-- Migration 024 - normalize clients company_name spacing safely

DROP PROCEDURE IF EXISTS migration_024;
DELIMITER $$
CREATE PROCEDURE migration_024()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
    ) THEN
        UPDATE clients c
        LEFT JOIN clients c2
          ON c2.id <> c.id
         AND TRIM(c2.company_name) = TRIM(c.company_name)
        SET c.company_name = TRIM(c.company_name)
        WHERE c2.id IS NULL
          AND c.company_name <> TRIM(c.company_name);
    END IF;
END$$
DELIMITER ;

CALL migration_024();
DROP PROCEDURE IF EXISTS migration_024;
