<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/calendarrepository.php';
require_once __DIR__ . '/calendarservice.php';
require_once __DIR__ . '/calendarcontroller.php';

$repository = new CalendarRepository($mysqli);
$service = new CalendarService($repository);
$controller = new CalendarController($service);
$controller->handleRequest();