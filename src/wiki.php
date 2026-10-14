<?php

/*
 * Wikitjänsten (services & 4).
 * Skapa, uppdatera, ta bort och läsa sidor, sök, bilder och historik.
 * Varje ändring sparas som en ny version i WIKI_HISTORY.
 */

require_once __DIR__ . '/core.php';

header('Content-Type: application/json; charset=utf-8');

$result = $db->query("SELECT article_id, status FROM WIKI_ARTICLE");
echo json_encode($result->fetch_all(MYSQLI_ASSOC));