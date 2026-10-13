<?php
require_once 'db.php';

// Hämta alla globala taggar sorterade i bokstavsordning
$query = "SELECT tag_id, name FROM TAG ORDER BY name ASC";
$result = $mysqli->query($query);

$tags = [];
while ($row = $result->fetch_assoc()) {
    $tags[] = $row;
}

$mysqli->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'count' => count($tags),
    'tags' => $tags
], JSON_PRETTY_PRINT);