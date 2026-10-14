<?php

/*
 * Grunden som alla andra filer använder.
 * - Läser in .env (DB-uppgifter, API-adress, krypteringsnyckel).
 * - Ansluter till databaserna: den gemensamma (central) och rätt företagsdatabas,
 *   t.ex. "acme" i URL:en -> databasen company_acme.
 * - Hjälpfunktioner för JSON-svar, t.ex. json_ok($data) och json_error(403, "Ingen åtkomst").
 */

$env = parse_ini_file(__DIR__ . '/../.env');

try {
    $db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], 'company_1');
    $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit('Kunde inte ansluta till databasen');
}