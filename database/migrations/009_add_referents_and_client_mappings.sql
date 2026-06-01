-- Migration 009 - referents registry, user link, client mappings, project-linked deadlines

DROP PROCEDURE IF EXISTS migration_009;
DELIMITER $$
CREATE PROCEDURE migration_009()
BEGIN
    CREATE TABLE IF NOT EXISTS referents (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        first_name   VARCHAR(100) NOT NULL,
        last_name    VARCHAR(100) NOT NULL,
        email        VARCHAR(255) NOT NULL UNIQUE,
        phone        VARCHAR(30),
        fiscal_code  VARCHAR(20),
        notes        TEXT,
        is_active    BOOLEAN NOT NULL DEFAULT TRUE,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'referent_id'
    ) THEN
        ALTER TABLE users
            ADD COLUMN referent_id INT UNSIGNED NULL AFTER collaborator_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'referent_id'
          AND REFERENCED_TABLE_NAME = 'referents'
    ) THEN
        ALTER TABLE users
            ADD CONSTRAINT fk_users_referent
                FOREIGN KEY (referent_id) REFERENCES referents(id)
                ON DELETE SET NULL;
    END IF;

    CREATE TABLE IF NOT EXISTS client_referents (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id  INT UNSIGNED NOT NULL,
        user_id    INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_client_referent (client_id, user_id),
        CONSTRAINT fk_cr_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        CONSTRAINT fk_cr_user   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_referents'
          AND INDEX_NAME = 'uq_client_referent'
          AND NON_UNIQUE = 0
    ) THEN
        ALTER TABLE client_referents
            ADD CONSTRAINT uq_client_referent UNIQUE (client_id, user_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_referents'
          AND COLUMN_NAME = 'client_id'
          AND REFERENCED_TABLE_NAME = 'clients'
    ) THEN
        ALTER TABLE client_referents
            ADD CONSTRAINT fk_cr_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_referents'
          AND COLUMN_NAME = 'user_id'
          AND REFERENCED_TABLE_NAME = 'users'
    ) THEN
        ALTER TABLE client_referents
            ADD CONSTRAINT fk_cr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
          AND COLUMN_NAME = 'project_id'
    ) THEN
        ALTER TABLE client_deadlines
            ADD COLUMN project_id INT UNSIGNED NULL AFTER client_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
          AND COLUMN_NAME = 'project_id'
          AND REFERENCED_TABLE_NAME = 'projects'
    ) THEN
        ALTER TABLE client_deadlines
            ADD CONSTRAINT fk_cd_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_referents'
          AND INDEX_NAME = 'idx_cr_user'
    ) THEN
        CREATE INDEX idx_cr_user ON client_referents (user_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'client_deadlines'
          AND INDEX_NAME = 'idx_cd_project_due_date'
    ) THEN
        CREATE INDEX idx_cd_project_due_date ON client_deadlines (project_id, due_date);
    END IF;
END$$
DELIMITER ;

CALL migration_009();
DROP PROCEDURE IF EXISTS migration_009;
