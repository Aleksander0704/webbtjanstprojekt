<?php
// config.php - Läser in .env och skapar databasanslutning

// 1. Läs in .env-filen om den finns
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Hoppa över kommentarsrader
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        // Dela upp raden på ett likhetstecken (=)
        if (str_contains($line, '=')) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            // Ta bort eventuella citattecken runt värdet
            $value = trim($value, '"\'');
            
            // Spara i $_ENV och $_SERVER för säkerhets skull
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// 2. Hämta databas-uppgifterna från $_ENV
$dbHost = $_ENV['DB_HOST'];
$dbUser = $_ENV['DB_USER'];
$dbPass = $_ENV['DB_PASS'];
$dbName = $_ENV['DB_NAME'];
$dbPort = $_ENV['DB_PORT'] ?? 3306;

// 3. Skapa MySQLi-anslutningen
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, intval($dbPort));

if ($mysqli->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Databasanslutning misslyckades: " . $mysqli->connect_error]);
    exit;
}

$mysqli->set_charset("utf8mb4");
