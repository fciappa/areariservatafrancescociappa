-- Migration 023 - add lookup index on tariff_assignments by tariff_id

DROP PROCEDURE IF EXISTS migration_023;
DELIMITER $$
CREATE PROCEDURE migration_023()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tariff_assignments'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tariff_assignments'
          AND INDEX_NAME = 'idx_ta_tariff_project_collab'
    ) THEN
        CREATE INDEX idx_ta_tariff_project_collab
            ON tariff_assignments (tariff_id, project_id, collaborator_id);
    END IF;
END$$
DELIMITER ;

CALL migration_023();
DROP PROCEDURE IF EXISTS migration_023;
