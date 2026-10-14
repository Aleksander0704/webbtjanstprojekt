<?php
// login.php - Entry point för inloggning

require_once __DIR__ . '/db.php';              // Din databasanslutning ($mysqli)
require_once __DIR__ . '/LoginController.php';

// Starta kontrollern och hantera inloggningsförfrågan
$controller = new LoginController($mysqli);
$controller->handleRequest();