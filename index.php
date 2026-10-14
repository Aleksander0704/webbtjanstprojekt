<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Hämta vilket företags databas som ska användas
$dbName = $_GET['db'] ?? $_POST['db_name'] ?? 'db_company_1'; 

// Anslut till det specifika företagets databas
$db = new mysqli("db", "db", "db", $dbName);

if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Kunde inte ansluta till företagets databas: " . $db->connect_error]);
    exit;
}

$db->set_charset("utf8mb4");

// Importera modeller och controllers
require_once 'WikiArticle.php';
require_once 'WikiController.php';
require_once 'BlogPost.php';
require_once 'BlogController.php';

// Initiera modeller och controllers
$wikiModel = new WikiArticle($db);
$wikiController = new WikiController($wikiModel);

$blogModel = new BlogPost($db);
$blogController = new BlogController($blogModel);

// Hämta URL-parametrar
$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'wiki';
$action   = $_GET['action'] ?? null;

switch ($method) {
    case 'POST':
        if ($resource === 'wiki') {
            if ($action === 'create') {
                $wikiController->createWikiArticle();
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Ange en giltig action (action=create)."]);
            }
        } elseif ($resource === 'blog') {
            if ($action === 'create') {
                $blogController->createBlogPost();
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Ange en giltig action (action=create)."]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Resursen hittades inte."]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "HTTP-metoden stöds inte."]);
        break;
}