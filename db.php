<?php

/*
 * Grunden som alla andra filer använder.
 * - Läser in .env (DB-uppgifter, API-adress, krypteringsnyckel).
 * - Ansluter till databaserna: den gemensamma (central) och rätt företagsdatabas,
 *   t.ex. "acme" i URL:en -> databasen company_acme.
 * - Hjälpfunktioner för JSON-svar, t.ex. json_ok($data) och json_error(403, "Ingen åtkomst").
 */

// Läs in inställningar från .env-filen i projektets rot
$env = parse_ini_file(__DIR__ . '/.env');

$host = $env['DB_HOST'] ?? 'db';
$user = $env['DB_USER'] ?? 'db';
$pass = $env['DB_PASS'] ?? 'db';
$name = $env['DB_NAME'] ?? 'company_1';

$db = new mysqli($host, $user, $pass, $name);

if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Databasanslutningen misslyckades: " . $db->connect_error]);
    exit;
}

$db->set_charset("utf8mb4");

return $db;