-- Migration 006 — add referent role and project_referents mapping

DROP PROCEDURE IF EXISTS migration_006;
DELIMITER $$
CREATE PROCEDURE migration_006()
BEGIN
    -- Extend users.role enum to include referent
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'role'
          AND COLUMN_TYPE NOT LIKE '%''referent''%'
    ) THEN
        ALTER TABLE users
            MODIFY COLUMN role ENUM('admin', 'collaborator', 'referent') NOT NULL DEFAULT 'collaborator';
    END IF;

    -- Project <-> Referent many-to-many
    CREATE TABLE IF NOT EXISTS project_referents (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        user_id    INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_project_referent (project_id, user_id),
        CONSTRAINT fk_pr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_pr_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
    );

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'project_referents'
          AND INDEX_NAME = 'idx_project_referents_user'
    ) THEN
        CREATE INDEX idx_project_referents_user ON project_referents (user_id);
    END IF;
END$$
DELIMITER ;

CALL migration_006();
DROP PROCEDURE IF EXISTS migration_006;
