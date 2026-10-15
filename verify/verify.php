<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/verifyrepository.php';
require_once __DIR__ . '/verifyservice.php';
require_once __DIR__ . '/verifycontroller.php';

$repository = new VerifyRepository($mysqli);
$service = new VerifyService($repository);
$controller = new VerifyController($service);
$controller->handleRequest();