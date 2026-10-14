<?php
// login.php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/loginmodel.php';
require_once __DIR__ . '/logincontroller.php';

$model = new LoginModel($mysqli);
$controller = new LoginController($model);
$controller->handleRequest();