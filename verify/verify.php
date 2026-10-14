<?php
// verify.php - Entry point för verifiering av sessionsnyckel

require_once __DIR__ . '/db.php';             // Din MySQLi-anslutning ($mysqli)
require_once __DIR__ . '/VerifyController.php';

// Skapa instans av kontrollern och kör anropet
$controller = new VerifyController($mysqli);
$controller->handleRequest();