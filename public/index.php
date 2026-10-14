<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Läs in databasanslutningen
$db = require_once __DIR__ . '/../src/core.php';

// Parsa URL
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$delar = explode('/', trim($path, '/'));

$tjanst = $_GET['resource'] ?? $delar[1] ?? $delar[0] ?? '';
$action = $_GET['action']   ?? $delar[2] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($tjanst) {
    case 'wiki':
        require_once __DIR__ . '/../src/wikiController.php';
        $controller = new WikiController($db);

        if ($method === 'POST' && $action === 'create') {
            $controller->create();
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Ogiltig action eller HTTP-metod för wiki']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Tjänsten hittades inte']);
        break;
}