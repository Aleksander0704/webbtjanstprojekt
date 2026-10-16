<?php
$envFile = __DIR__ . '/.env';


function sendJson($data, int $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// 3. Skapa MySQLi-anslutningen
$mysqli = new mysqli("db","db","db","db");

if ($mysqli->connect_error) {
    sendJson([
        "error" => "Databasanslutning misslyckades: " . $mysqli->connect_error
    ], 500);
    exit;
}

$mysqli->set_charset("utf8mb4");
