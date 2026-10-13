<?php
// DDEV Standard Database Credentials
$host = 'db';
$username = 'db';
$password = 'db';
$database = 'db';
$port = 3306;

// Skapa MySQLi-anslutning
$mysqli = new mysqli($host, $username, $password, $database, $port);

// Kontrollera anslutningen
if ($mysqli->connect_error) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '❌ DDEV Anslutningsfel: ' . $mysqli->connect_error
    ]);
    exit;
}

$mysqli->set_charset("utf8mb4");

