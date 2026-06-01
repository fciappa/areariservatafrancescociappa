-- Migration 021 - add lookup index for active clients by company_name

DROP PROCEDURE IF EXISTS migration_021;
DELIMITER $$
CREATE PROCEDURE migration_021()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
          AND INDEX_NAME = 'idx_clients_active_company'
    ) THEN
        CREATE INDEX idx_clients_active_company ON clients (is_active, company_name);
    END IF;
END$$
DELIMITER ;

CALL migration_021();
DROP PROCEDURE IF EXISTS migration_021;
