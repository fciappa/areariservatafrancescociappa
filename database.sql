-- ============================================================
--  Area Riservata - Francesco Ciappa
--  Database Schema - MySQL 8+
--  Tecnologie: Vue 3 + Vite + MySQL | Auth: JWT
-- ============================================================
--
--  NOTA HASH ADMIN
--  La password di default dell'admin è: Admin@2024
--  Prima di importare il file, genera il hash bcrypt con:
--
--  Node.js:
--    node -e "const b=require('bcrypt');b.hash('Admin@2024',10).then(console.log)"
--
--  PHP:
--    php -r "echo password_hash('Admin@2024', PASSWORD_BCRYPT);"
--
--  Python:
--    python -c "import bcrypt; print(bcrypt.hashpw(b'Admin@2024', bcrypt.gensalt(10)).decode())"
--
--  Sostituisci CAMBIA_QUESTO_HASH con il valore ottenuto.
-- ============================================================

CREATE DATABASE IF NOT EXISTS areariservatafc_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE areariservatafc_db;

-- -----------------------------------------------------------
-- COLLABORATORI
-- -----------------------------------------------------------
CREATE TABLE collaborators (
    id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    first_name   VARCHAR(100)     NOT NULL,
    last_name    VARCHAR(100)     NOT NULL,
    email        VARCHAR(255)     NOT NULL UNIQUE,
    phone        VARCHAR(30),
    fiscal_code  VARCHAR(20),
    notes        TEXT,
    is_active    BOOLEAN          NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------
-- UTENTI  (admin + account collaboratore)
-- -----------------------------------------------------------
CREATE TABLE users (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(100)    NOT NULL UNIQUE,
    email            VARCHAR(255)    NOT NULL UNIQUE,
    password_hash    VARCHAR(255)    NOT NULL,
    role             ENUM('admin','collaborator') NOT NULL DEFAULT 'collaborator',
    collaborator_id  INT UNSIGNED    NULL COMMENT 'Collegato a collaborators se role=collaborator',
    is_active        BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_collaborator
        FOREIGN KEY (collaborator_id) REFERENCES collaborators(id)
        ON DELETE SET NULL
);

-- -----------------------------------------------------------
-- CLIENTI
-- -----------------------------------------------------------
CREATE TABLE clients (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    company_name  VARCHAR(255)    NOT NULL,
    vat_number    VARCHAR(20)     NOT NULL UNIQUE  COMMENT 'Partita IVA',
    email         VARCHAR(255),
    phone         VARCHAR(30),
    address       TEXT,
    city          VARCHAR(100),
    postal_code   VARCHAR(10),
    country       VARCHAR(100)    DEFAULT 'Italia',
    notes         TEXT,
    is_active     BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------
-- PROGETTI
-- -----------------------------------------------------------
CREATE TABLE projects (
    id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    client_id    INT UNSIGNED     NOT NULL,
    name         VARCHAR(255)     NOT NULL,
    description  TEXT,
    status       ENUM('active','on_hold','completed','archived') NOT NULL DEFAULT 'active',
    start_date   DATE             NOT NULL,
    end_date     DATE             NULL                COMMENT 'NULL = progetto aperto',
    notes        TEXT,
    is_active    BOOLEAN          NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_proj_client FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- -----------------------------------------------------------
-- TARIFFARIO
-- -----------------------------------------------------------
CREATE TABLE tariffs (
    id             INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150)    NOT NULL,
    hourly_rate    DECIMAL(10,2)   NOT NULL            COMMENT 'Tariffa oraria in EUR',
    valid_from     DATE            NOT NULL,
    valid_to       DATE            NULL                COMMENT 'NULL = nessuna scadenza',
    is_default     BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'Tariffa proposta di default (fallback globale)',
    tax_inclusive  BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'TRUE = 4% già incluso; FALSE = 4% da aggiungere',
    notes          TEXT,
    created_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------
-- ASSEGNAZIONE TARIFFE → PROGETTI (+ opzionale collaboratore)
--
--  Logica di risoluzione tariffa:
--   1. Cerca (project_id, collaborator_id) → tariffa specifica
--   2. Se non trovata, cerca (project_id, NULL) → tariffa di default per il progetto
--   3. Se non trovata, usa la tariffa con is_default = TRUE (fallback globale)
-- -----------------------------------------------------------
CREATE TABLE tariff_assignments (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    tariff_id        INT UNSIGNED    NOT NULL,
    project_id       INT UNSIGNED    NOT NULL,
    collaborator_id  INT UNSIGNED    NULL  COMMENT 'NULL = valida per tutti i collaboratori del progetto',
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ta_project_collab (project_id, collaborator_id),
    CONSTRAINT fk_ta_tariff  FOREIGN KEY (tariff_id)       REFERENCES tariffs(id)       ON DELETE CASCADE,
    CONSTRAINT fk_ta_project FOREIGN KEY (project_id)      REFERENCES projects(id)      ON DELETE CASCADE,
    CONSTRAINT fk_ta_collab  FOREIGN KEY (collaborator_id) REFERENCES collaborators(id) ON DELETE CASCADE
);

-- -----------------------------------------------------------
-- ORE DEI COLLABORATORI (lavorate per me)
-- -----------------------------------------------------------
CREATE TABLE collaborator_hours (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    collaborator_id  INT UNSIGNED    NOT NULL,
    project_id       INT UNSIGNED    NULL  COMMENT 'Progetto di riferimento',
    tariff_id        INT UNSIGNED    NOT NULL,
    work_date        DATE            NOT NULL,
    hours            DECIMAL(5,2)    NOT NULL,
    description      TEXT,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ch_collab   FOREIGN KEY (collaborator_id) REFERENCES collaborators(id),
    CONSTRAINT fk_ch_project  FOREIGN KEY (project_id)      REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_ch_tariff   FOREIGN KEY (tariff_id)       REFERENCES tariffs(id)
);

-- -----------------------------------------------------------
-- ORE MIE (lavorate per un cliente, riferite a un progetto)
-- -----------------------------------------------------------
CREATE TABLE my_work_hours (
    id           INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    client_id    INT UNSIGNED    NOT NULL,
    project_id   INT UNSIGNED    NULL  COMMENT 'Progetto di riferimento',
    tariff_id    INT UNSIGNED    NOT NULL,
    work_date    DATE            NOT NULL,
    hours        DECIMAL(5,2)    NOT NULL,
    description  TEXT,
    created_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mwh_client   FOREIGN KEY (client_id)  REFERENCES clients(id),
    CONSTRAINT fk_mwh_project  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_mwh_tariff   FOREIGN KEY (tariff_id)  REFERENCES tariffs(id)
);

-- -----------------------------------------------------------
-- FATTURE (simulate)
-- -----------------------------------------------------------
CREATE TABLE invoices (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(50)     NOT NULL UNIQUE         COMMENT 'Es. 2024/001',
    client_id       INT UNSIGNED    NOT NULL,
    invoice_date    DATE            NOT NULL,
    stamp_duty      DECIMAL(10,2)   NOT NULL DEFAULT 2.00   COMMENT 'Bollo 2,00 EUR',
    subtotal        DECIMAL(10,2)   NOT NULL DEFAULT 0.00   COMMENT 'Totale imponibile',
    tax_amount      DECIMAL(10,2)   NOT NULL DEFAULT 0.00   COMMENT '4% calcolato',
    total           DECIMAL(10,2)   NOT NULL DEFAULT 0.00   COMMENT 'subtotal + tax_amount + stamp_duty',
    notes           TEXT,
    status          ENUM('draft','issued','paid') NOT NULL DEFAULT 'draft',
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_client FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- -----------------------------------------------------------
-- RIGHE FATTURA
--
--  Logica 4%:
--    tax_inclusive = TRUE  → imponibile = line_total / 1.04  |  tax = line_total - imponibile
--    tax_inclusive = FALSE → imponibile = hourly_rate * hours |  tax = imponibile * 0.04
-- -----------------------------------------------------------
CREATE TABLE invoice_items (
    id             INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    invoice_id     INT UNSIGNED    NOT NULL,
    work_hour_id   INT UNSIGNED    NULL   COMMENT 'Ref a my_work_hours (opzionale)',
    description    VARCHAR(255)    NOT NULL,
    tariff_id      INT UNSIGNED    NOT NULL,
    hours          DECIMAL(5,2)    NOT NULL,
    hourly_rate    DECIMAL(10,2)   NOT NULL,
    tax_inclusive  BOOLEAN         NOT NULL DEFAULT FALSE,
    line_total     DECIMAL(10,2)   NOT NULL  COMMENT 'hourly_rate * hours (lordo)',
    created_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ii_invoice    FOREIGN KEY (invoice_id)   REFERENCES invoices(id)      ON DELETE CASCADE,
    CONSTRAINT fk_ii_work_hour  FOREIGN KEY (work_hour_id) REFERENCES my_work_hours(id) ON DELETE SET NULL,
    CONSTRAINT fk_ii_tariff     FOREIGN KEY (tariff_id)    REFERENCES tariffs(id)
);

-- -----------------------------------------------------------
-- REFRESH TOKEN JWT
-- -----------------------------------------------------------
CREATE TABLE refresh_tokens (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(512)    NOT NULL UNIQUE,
    expires_at  TIMESTAMP       NOT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------------
-- INDICI UTILI PER LE QUERY PIÙ FREQUENTI
-- -----------------------------------------------------------
CREATE INDEX idx_collab_hours_date      ON collaborator_hours (collaborator_id, work_date);
CREATE INDEX idx_collab_hours_project   ON collaborator_hours (project_id, work_date);
CREATE INDEX idx_my_work_hours_date     ON my_work_hours      (client_id, work_date);
CREATE INDEX idx_my_work_hours_project  ON my_work_hours      (project_id, work_date);
CREATE INDEX idx_invoices_date          ON invoices           (client_id, invoice_date);
CREATE INDEX idx_invoices_status        ON invoices           (status);
CREATE INDEX idx_projects_client        ON projects           (client_id);
CREATE INDEX idx_tariff_assignments     ON tariff_assignments (project_id, collaborator_id);
CREATE INDEX idx_refresh_tokens_user    ON refresh_tokens     (user_id);

-- -----------------------------------------------------------
-- UTENTE ADMIN
--
--  ⚠  SOSTITUIRE "CAMBIA_QUESTO_HASH" con il hash bcrypt
--     della password Admin@2024 (vedi istruzioni in cima).
-- -----------------------------------------------------------
INSERT INTO users (username, email, password_hash, role, is_active)
VALUES (
    'admin',
    'admin@areariservata.local',
    '$2b$10$UkmQxKNlPOtX3OS.EPPBQeFuYQ5As5G3v2kZGZizvb7XY4dArxblC',
    'admin',
    TRUE
);


CREATE DATABASE IF NOT EXISTS areariservatafc_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE areariservatafc_db;

-- -----------------------------------------------------------
-- COLLABORATORI
-- -----------------------------------------------------------
CREATE TABLE collaborators (
    id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    first_name   VARCHAR(100)     NOT NULL,
    last_name    VARCHAR(100)     NOT NULL,
    email        VARCHAR(255)     NOT NULL UNIQUE,
    phone        VARCHAR(30),
    fiscal_code  VARCHAR(20),
    notes        TEXT,
    is_active    BOOLEAN          NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------
-- UTENTI  (admin + account collaboratore)
-- -----------------------------------------------------------
CREATE TABLE users (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(100)    NOT NULL UNIQUE,
    email            VARCHAR(255)    NOT NULL UNIQUE,
    password_hash    VARCHAR(255)    NOT NULL,
    role             ENUM('admin','collaborator') NOT NULL DEFAULT 'collaborator',
    collaborator_id  INT UNSIGNED    NULL COMMENT 'Collegato a collaborators se role=collaborator',
    is_active        BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_collaborator
        FOREIGN KEY (collaborator_id) REFERENCES collaborators(id)
        ON DELETE SET NULL
);

-- -----------------------------------------------------------
-- CLIENTI
-- -----------------------------------------------------------
CREATE TABLE clients (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    company_name  VARCHAR(255)    NOT NULL,
    vat_number    VARCHAR(20)     NOT NULL UNIQUE  COMMENT 'Partita IVA',
    email         VARCHAR(255),
    phone         VARCHAR(30),
    address       TEXT,
    city          VARCHAR(100),
    postal_code   VARCHAR(10),
    country       VARCHAR(100)    DEFAULT 'Italia',
    notes         TEXT,
    is_active     BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------
-- TARIFFARIO
-- -----------------------------------------------------------
CREATE TABLE tariffs (
    id             INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150)    NOT NULL,
    hourly_rate    DECIMAL(10,2)   NOT NULL            COMMENT 'Tariffa oraria in EUR',
    valid_from     DATE            NOT NULL,
    valid_to       DATE            NULL                COMMENT 'NULL = nessuna scadenza',
    is_default     BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'Tariffa proposta di default',
    tax_inclusive  BOOLEAN         NOT NULL DEFAULT FALSE COMMENT 'TRUE = 4% già incluso; FALSE = 4% da aggiungere',
    notes          TEXT,
    created_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Assicura che ci sia al massimo una tariffa di default attiva
-- (gestito a livello applicativo; il flag è comunque qui)

-- -----------------------------------------------------------
-- ASSEGNAZIONE TARIFFE → COLLABORATORI
-- -----------------------------------------------------------
CREATE TABLE tariff_collaborators (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    tariff_id        INT UNSIGNED    NOT NULL,
    collaborator_id  INT UNSIGNED    NOT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tariff_collaborator (tariff_id, collaborator_id),
    CONSTRAINT fk_tc_tariff  FOREIGN KEY (tariff_id)       REFERENCES tariffs(id)       ON DELETE CASCADE,
    CONSTRAINT fk_tc_collab  FOREIGN KEY (collaborator_id) REFERENCES collaborators(id) ON DELETE CASCADE
);

-- -----------------------------------------------------------
-- ASSEGNAZIONE TARIFFE → CLIENTI
-- -----------------------------------------------------------
CREATE TABLE tariff_clients (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    tariff_id   INT UNSIGNED    NOT NULL,
    client_id   INT UNSIGNED    NOT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tariff_client (tariff_id, client_id),
    CONSTRAINT fk_tcs_tariff  FOREIGN KEY (tariff_id)  REFERENCES tariffs(id)  ON DELETE CASCADE,
    CONSTRAINT fk_tcs_client  FOREIGN KEY (client_id)  REFERENCES clients(id)  ON DELETE CASCADE
);

-- -----------------------------------------------------------
-- ORE DEI COLLABORATORI (lavorate per me)
-- -----------------------------------------------------------
CREATE TABLE collaborator_hours (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    collaborator_id  INT UNSIGNED    NOT NULL,
    tariff_id        INT UNSIGNED    NOT NULL,
    work_date        DATE            NOT NULL,
    hours            DECIMAL(5,2)    NOT NULL,
    description      TEXT,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ch_collab  FOREIGN KEY (collaborator_id) REFERENCES collaborators(id),
    CONSTRAINT fk_ch_tariff  FOREIGN KEY (tariff_id)       REFERENCES tariffs(id)
);

-- -----------------------------------------------------------
-- ORE MIE (lavorate per un cliente)
-- -----------------------------------------------------------
CREATE TABLE my_work_hours (
    id           INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    client_id    INT UNSIGNED    NOT NULL,
    tariff_id    INT UNSIGNED    NOT NULL,
    work_date    DATE            NOT NULL,
    hours        DECIMAL(5,2)    NOT NULL,
    description  TEXT,
    created_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mwh_client  FOREIGN KEY (client_id)  REFERENCES clients(id),
    CONSTRAINT fk_mwh_tariff  FOREIGN KEY (tariff_id)  REFERENCES tariffs(id)
);

-- -----------------------------------------------------------
-- FATTURE (simulate)
-- -----------------------------------------------------------
CREATE TABLE invoices (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(50)     NOT NULL UNIQUE         COMMENT 'Es. 2024/001',
    client_id       INT UNSIGNED    NOT NULL,
    invoice_date    DATE            NOT NULL,
    stamp_duty      DECIMAL(10,2)   NOT NULL DEFAULT 2.00   COMMENT 'Bollo 2,00 EUR',
    subtotal        DECIMAL(10,2)   NOT NULL DEFAULT 0.00   COMMENT 'Totale imponibile',
    tax_amount      DECIMAL(10,2)   NOT NULL DEFAULT 0.00   COMMENT '4% calcolato',
    total           DECIMAL(10,2)   NOT NULL DEFAULT 0.00   COMMENT 'subtotal + tax_amount + stamp_duty',
    notes           TEXT,
    status          ENUM('draft','issued','paid') NOT NULL DEFAULT 'draft',
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_client FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- -----------------------------------------------------------
-- RIGHE FATTURA
--
--  Logica 4%:
--    tax_inclusive = TRUE  → imponibile = line_total / 1.04  |  tax = line_total - imponibile
--    tax_inclusive = FALSE → imponibile = hourly_rate * hours |  tax = imponibile * 0.04
-- -----------------------------------------------------------
CREATE TABLE invoice_items (
    id             INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    invoice_id     INT UNSIGNED    NOT NULL,
    work_hour_id   INT UNSIGNED    NULL   COMMENT 'Ref a my_work_hours (opzionale)',
    description    VARCHAR(255)    NOT NULL,
    tariff_id      INT UNSIGNED    NOT NULL,
    hours          DECIMAL(5,2)    NOT NULL,
    hourly_rate    DECIMAL(10,2)   NOT NULL,
    tax_inclusive  BOOLEAN         NOT NULL DEFAULT FALSE,
    line_total     DECIMAL(10,2)   NOT NULL  COMMENT 'hourly_rate * hours (lordo)',
    created_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ii_invoice    FOREIGN KEY (invoice_id)   REFERENCES invoices(id)      ON DELETE CASCADE,
    CONSTRAINT fk_ii_work_hour  FOREIGN KEY (work_hour_id) REFERENCES my_work_hours(id) ON DELETE SET NULL,
    CONSTRAINT fk_ii_tariff     FOREIGN KEY (tariff_id)    REFERENCES tariffs(id)
);

-- -----------------------------------------------------------
-- REFRESH TOKEN JWT
-- -----------------------------------------------------------
CREATE TABLE refresh_tokens (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(512)    NOT NULL UNIQUE,
    expires_at  TIMESTAMP       NOT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------------
-- INDICI UTILI PER LE QUERY PIÙ FREQUENTI
-- -----------------------------------------------------------
CREATE INDEX idx_collab_hours_date      ON collaborator_hours (collaborator_id, work_date);
CREATE INDEX idx_my_work_hours_date     ON my_work_hours      (client_id, work_date);
CREATE INDEX idx_invoices_date          ON invoices           (client_id, invoice_date);
CREATE INDEX idx_invoices_status        ON invoices           (status);
CREATE INDEX idx_refresh_tokens_user    ON refresh_tokens     (user_id);

-- -----------------------------------------------------------
-- UTENTE ADMIN
--
--  ⚠  SOSTITUIRE "CAMBIA_QUESTO_HASH" con il hash bcrypt
--     della password Admin@2024 (vedi istruzioni in cima).
-- -----------------------------------------------------------
INSERT INTO users (username, email, password_hash, role, is_active)
VALUES (
    'admin',
    'admin@areariservata.local',
    '$2b$10$UkmQxKNlPOtX3OS.EPPBQeFuYQ5As5G3v2kZGZizvb7XY4dArxblC',
    'admin',
    TRUE
);
