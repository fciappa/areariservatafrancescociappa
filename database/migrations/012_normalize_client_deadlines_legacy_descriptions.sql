-- Migration 012 - normalize legacy markers in client_deadlines descriptions

DROP PROCEDURE IF EXISTS migration_012;
DELIMITER $$
CREATE PROCEDURE migration_012()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
    ) THEN
        -- Remove duplicates on normalized logical key before mass update.
        DELETE d1
        FROM client_deadlines d1
        JOIN client_deadlines d2
          ON d1.client_id = d2.client_id
         AND d1.due_date = d2.due_date
         AND TRIM(d1.item_type) = TRIM(d2.item_type)
         AND REPLACE(REPLACE(TRIM(d1.description), ' $$', ''), ' **', '') = REPLACE(REPLACE(TRIM(d2.description), ' $$', ''), ' **', '')
         AND d1.id > d2.id;

        -- Normalize textual fields for stable, clean comparisons.
        UPDATE client_deadlines
        SET item_type = TRIM(item_type),
            description = REPLACE(REPLACE(TRIM(description), ' $$', ''), ' **', '')
        WHERE item_type <> TRIM(item_type)
           OR description <> REPLACE(REPLACE(TRIM(description), ' $$', ''), ' **', '');

        -- Ensure unique business key exists (for environments that skipped migration_011).
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'client_deadlines'
              AND INDEX_NAME = 'uq_cd_client_due_item_desc'
              AND NON_UNIQUE = 0
        ) THEN
            ALTER TABLE client_deadlines
                ADD CONSTRAINT uq_cd_client_due_item_desc
                UNIQUE (client_id, due_date, item_type, description);
        END IF;
    END IF;
END$$
DELIMITER ;

CALL migration_012();
DROP PROCEDURE IF EXISTS migration_012;
