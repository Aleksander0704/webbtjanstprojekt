<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$db = require_once __DIR__ . '/../db.php';

$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$delar = array_values(array_filter(explode('/', trim($path, '/'))));

$tjanst = $_GET['resource'] ?? $delar[0] ?? '';
$action = $_GET['action']   ?? $delar[1] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($tjanst) {
    case 'wiki':
        require_once __DIR__ . '/../src/wikiController.php';
        $controller = new WikiController($db);

        if ($method === 'POST' && $action === 'create') {
            $controller->create();
        } elseif ($method === 'GET' && ($action === 'search' || isset($_GET['query']) || isset($_GET['q']))) {
            $controller->search();
        } elseif ($method === 'GET' && $action === 'history') {
            $controller->history();
        } elseif (($method === 'PUT' || $method === 'POST') && $action === 'update') {
            $controller->update();
        } elseif (($method === 'DELETE' || $method === 'POST') && $action === 'delete') {
            $controller->delete();
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