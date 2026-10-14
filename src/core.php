<?php

/*
 * Grunden som alla andra filer använder.
 * - Läser in .env (DB-uppgifter, API-adress, krypteringsnyckel).
 * - Ansluter till databaserna: den gemensamma (central) och rätt företagsdatabas,
 *   t.ex. "acme" i URL:en -> databasen company_acme.
 * - Hjälpfunktioner för JSON-svar, t.ex. json_ok($data) och json_error(403, "Ingen åtkomst").
 */

$env = parse_ini_file(__DIR__ . '/../.env');

// Hämta databasnamn från URL (?db=company_1) eller fallback till 'company_1'
$dbName = $_GET['db'] ?? $_POST['db_name'] ?? 'company_1';

try {
    $db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $dbName);
    $db->set_charset('utf8mb4');
    return $db;
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => "Kunde inte ansluta till databasen '{$dbName}': " . $e->getMessage()]);
    exit;
}