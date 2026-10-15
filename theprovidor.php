<?php
require_once __DIR__ . '/core.php';

$LOGIN_URL = "https://theprovider.ntigskovde.se/login";
$VERIFY_URL = "https://theprovider.ntigskovde.se/verify";
$LOGOUT_URL = "https://theprovider.ntigskovde.se/logout";

$id = 1; // Variabeln för ID

$stmt = $mysqli->prepare("SELECT username, password FROM verysecret WHERE id = ?");
$stmt->bind_param("i", $id); // "i" står för integer (siffra)
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$username = $row['username'];
$password = $row['password'];


// 2. Skicka POST till API:et
$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode(['username' => $username, 'password' => $password])
    ]
];

$response = file_get_contents("https://theprovider.ntigskovde.se/login", false, stream_context_create($options));


echo $response;
?>