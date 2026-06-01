-- Migration 008 - seed deadlines shown in screenshot for client Enpabil

DROP PROCEDURE IF EXISTS migration_008;
DELIMITER $$
CREATE PROCEDURE migration_008()
BEGIN
    DECLARE v_client_id INT UNSIGNED;

    IF NOT EXISTS (SELECT 1 FROM clients WHERE company_name = 'Enpabil') THEN
        INSERT INTO clients (company_name, vat_number, email, country, notes)
        VALUES ('Enpabil', 'ENPABIL-SEED', NULL, 'Italia', 'Cliente creato automaticamente per import scadenze.');
    END IF;

    SELECT id INTO v_client_id
    FROM clients
    WHERE company_name = 'Enpabil'
    ORDER BY id ASC
    LIMIT 1;

    INSERT INTO client_deadlines (
        client_id, due_date, item_type, description, linked_to,
        avada_version, php_version, mysql_version, wp_version,
        test_email, notes, line_ref, amount
    )
    SELECT v_client_id, x.due_date, x.item_type, x.description, x.linked_to,
           x.avada_version, x.php_version, x.mysql_version, x.wp_version,
           x.test_email, x.notes, x.line_ref, x.amount
    FROM (
        SELECT DATE('2027-01-18') AS due_date, 'dominio con redirect' AS item_type, 'federnetwork.com' AS description, NULL AS linked_to, NULL AS avada_version, NULL AS php_version, NULL AS mysql_version, NULL AS wp_version, NULL AS test_email, NULL AS notes, NULL AS line_ref, 14.99 AS amount
        UNION ALL SELECT DATE('2027-01-18'), 'dominio con redirect', 'federnetwork.it', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 11.99
        UNION ALL SELECT DATE('2026-02-05'), 'database', 'Sql519889', 'goldwelfare.org', NULL, NULL, '5.7', NULL, NULL, NULL, NULL, 15.00
        UNION ALL SELECT DATE('2026-04-07'), 'dominio', 'enpabilsalute.org', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 14.99
        UNION ALL SELECT DATE('2026-07-20'), 'dominio con email', 'cnasp.it', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 16.49
        UNION ALL SELECT DATE('2026-11-29'), 'hosting basic linux', 'federnetwork.com', NULL, NULL, '5.3', 'NO DB', 'NO WP', NULL, 'SITO HTML SENZA DB', NULL, 56.49
        UNION ALL SELECT DATE('2026-11-29'), 'hosting basic linux', 'federnetwork.it', NULL, NULL, '5.3', 'NO DB', 'NO WP', NULL, 'SITO HTML SENZA DB', NULL, 34.99
        UNION ALL SELECT DATE('2027-01-17'), 'database', 'Sql1606358', 'enpabilsalute.com', NULL, NULL, '5.7', NULL, NULL, NULL, NULL, 15.00
        UNION ALL SELECT DATE('2027-01-18'), 'database + backup', 'Sql1332089', 'enpabil.com / areariservata', NULL, NULL, '5.7', NULL, NULL, NULL, NULL, 15.00
        UNION ALL SELECT DATE('2027-01-18'), 'hosting basic linux', 'enpabil.com', NULL, '7.3.33', '5.7', NULL, NULL, NULL, NULL, NULL, 96.98
        UNION ALL SELECT DATE('2027-01-18'), 'Terzo Livello con Hosting Linux', 'areariservata.enpabil.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 15.00
        UNION ALL SELECT DATE('2027-01-18'), 'Terzo Livello con Hosting Linux', 'areariservataapi.enpabil.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 15.00
        UNION ALL SELECT DATE('2027-01-18'), 'Terzo Livello con Hosting Linux', 'contabilita.enpabil.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 15.00
        UNION ALL SELECT DATE('2027-01-18'), 'dominio con gestione DNS + email', 'enpabil.it', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 16.49
        UNION ALL SELECT DATE('2027-01-18'), 'dominio con gestione DNS + email', 'enpabil.org', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20.49
        UNION ALL SELECT DATE('2027-01-18'), 'dominio con redirect', 'popolodellarete.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 14.99
        UNION ALL SELECT DATE('2027-01-18'), 'dominio con redirect', 'popolodellarete.net', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 14.99
        UNION ALL SELECT DATE('2027-01-18'), 'database + backup', 'Sql600835', 'popolodellarete.org', NULL, NULL, '5.5', NULL, NULL, 'RINNOVARE SENZA BACKUP!!!', NULL, 15.00
        UNION ALL SELECT DATE('2027-01-21'), 'hosting basic linux', 'enpabilsalute.com', NULL, '7.13.2', '8.4.12', '5.7', NULL, NULL, NULL, NULL, 34.99
        UNION ALL SELECT DATE('2027-01-21'), 'dominio', 'enpabilsalute.it', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 11.99
        UNION ALL SELECT DATE('2027-01-28'), 'hosting basic linux', 'goldwelfare.com', NULL, '7.3', '8.4.12', '5.7', NULL, NULL, NULL, NULL, 34.99
        UNION ALL SELECT DATE('2027-02-02'), 'dominio', 'goldwelfare.it', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 11.99
        UNION ALL SELECT DATE('2027-02-02'), 'dominio', 'goldwelfare.org', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 14.99
        UNION ALL SELECT DATE('2027-06-04'), 'dominio con email', 'confesercizi.it', NULL, NULL, 'NO', 'NO', 'NO', NULL, NULL, NULL, 16.49
        UNION ALL SELECT DATE('2027-06-07'), 'hosting basic linux', 'confesercizi.org', NULL, '7.13.2', '8.4.12', '5.7', '6.8.2', NULL, NULL, NULL, 32.99
        UNION ALL SELECT DATE('2027-06-29'), 'database', 'Sql1451433', 'confesercizi.org', NULL, NULL, '5.7', NULL, NULL, NULL, NULL, 10.00
        UNION ALL SELECT DATE('2027-04-13'), 'database', 'Sql1929969', 'sanitarieriunite.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL
    ) x
    WHERE NOT EXISTS (
        SELECT 1
        FROM client_deadlines d
        WHERE d.client_id = v_client_id
          AND d.due_date = x.due_date
                    AND TRIM(d.item_type) = TRIM(x.item_type)
                    AND REPLACE(REPLACE(TRIM(d.description), ' $$', ''), ' **', '') = REPLACE(REPLACE(TRIM(x.description), ' $$', ''), ' **', '')
    );
END$$
DELIMITER ;

CALL migration_008();
DROP PROCEDURE IF EXISTS migration_008;
