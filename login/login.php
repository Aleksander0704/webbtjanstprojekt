<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/loginrepository.php';
require_once __DIR__ . '/loginservice.php';
require_once __DIR__ . '/logincontroller.php';

$repository = new LoginRepository($mysqli);
$service = new LoginService($repository);
$controller = new LoginController($service);
$controller->handleRequest();