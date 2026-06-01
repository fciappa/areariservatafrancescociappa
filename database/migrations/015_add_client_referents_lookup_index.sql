-- Migration 015 - add lookup index for client_referents by user

DROP PROCEDURE IF EXISTS migration_015;
DELIMITER $$
CREATE PROCEDURE migration_015()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_referents'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_referents'
          AND INDEX_NAME = 'idx_cr_user_client'
    ) THEN
        CREATE INDEX idx_cr_user_client ON client_referents (user_id, client_id);
    END IF;
END$$
DELIMITER ;

CALL migration_015();
DROP PROCEDURE IF EXISTS migration_015;
