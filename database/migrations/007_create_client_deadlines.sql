-- Migration 007 - create client_deadlines table

DROP PROCEDURE IF EXISTS migration_007;
DELIMITER $$
CREATE PROCEDURE migration_007()
BEGIN
    CREATE TABLE IF NOT EXISTS client_deadlines (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id      INT UNSIGNED NOT NULL,
        due_date       DATE NOT NULL,
        item_type      VARCHAR(120) NOT NULL,
        description    VARCHAR(255) NOT NULL,
        linked_to      VARCHAR(255) NULL,
        avada_version  VARCHAR(30) NULL,
        php_version    VARCHAR(30) NULL,
        mysql_version  VARCHAR(30) NULL,
        wp_version     VARCHAR(30) NULL,
        test_email     VARCHAR(60) NULL,
        notes          VARCHAR(500) NULL,
        line_ref       VARCHAR(60) NULL,
        amount         DECIMAL(10,2) NULL,
        is_active      BOOLEAN NOT NULL DEFAULT TRUE,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_cd_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    );

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
          AND INDEX_NAME = 'idx_cd_client_due_date'
    ) THEN
        CREATE INDEX idx_cd_client_due_date ON client_deadlines (client_id, due_date);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
          AND INDEX_NAME = 'idx_cd_due_date'
    ) THEN
        CREATE INDEX idx_cd_due_date ON client_deadlines (due_date);
    END IF;
END$$
DELIMITER ;

CALL migration_007();
DROP PROCEDURE IF EXISTS migration_007;
