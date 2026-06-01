-- Migration 011 - harden client_deadlines uniqueness (cleanup + unique key)

DROP PROCEDURE IF EXISTS migration_011;
DELIMITER $$
CREATE PROCEDURE migration_011()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
    ) THEN
        -- Normalize textual fields to reduce accidental duplicates.
        UPDATE client_deadlines
        SET item_type = TRIM(item_type),
            description = TRIM(description)
        WHERE item_type <> TRIM(item_type)
           OR description <> TRIM(description);

        -- Keep oldest row when duplicates exist on logical business key.
        DELETE d1
        FROM client_deadlines d1
        JOIN client_deadlines d2
          ON d1.client_id = d2.client_id
         AND d1.due_date = d2.due_date
         AND d1.item_type = d2.item_type
         AND d1.description = d2.description
         AND d1.id > d2.id;

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

CALL migration_011();
DROP PROCEDURE IF EXISTS migration_011;
