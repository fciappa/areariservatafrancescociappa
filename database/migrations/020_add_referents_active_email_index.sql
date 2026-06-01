-- Migration 020 - add lookup index for active referents by email

DROP PROCEDURE IF EXISTS migration_020;
DELIMITER $$
CREATE PROCEDURE migration_020()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'referents'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'referents'
          AND INDEX_NAME = 'idx_referents_active_email'
    ) THEN
        CREATE INDEX idx_referents_active_email ON referents (is_active, email);
    END IF;
END$$
DELIMITER ;

CALL migration_020();
DROP PROCEDURE IF EXISTS migration_020;
