<?php
require_once __DIR__ . '/core.php';

$LOGIN_URL = "https://theprovider.ntigskovde.se/login";
$VERIFY_URL = "https://theprovider.ntigskovde.se/verify";
$LOGOUT_URL = "https://theprovider.ntigskovde.se/logout";
// företags id
$id = 4;

function login() {
    global $mysqli, $LOGIN_URL, $id;
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

    $response = json_decode(file_get_contents($LOGIN_URL, false, stream_context_create($options)), true);


    if (empty($response['session_key'])) {
        sendJson(['error' => 'Kunde inte logga in hos The Provider', 'svar' => $response], 502);
        exit;
    }
    
    // 3. Spara i databasen (skapar raden första gången, skriver över den sen)
    $mysqli->execute_query(
        "REPLACE INTO COMPANY (company_id, session_key, services, expires_at) VALUES (?, ?, ?, ?)",
        [$id, $response['session_key'], $response['services'] ?? 0, $response['expires_at'] ?? null]
    );

    sendJson(['success' => true, 'services' => $response['services'] ?? 0]);
    exit;
}

function verify() {
    global $mysqli, $VERIFY_URL, $id;

    // Hämta session_key från databasen

    $stmt = $mysqli->prepare("SELECT session_key FROM COMPANY WHERE company_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        sendJson(['error' => 'Ingen session_key hittades i databasen'], 404);
        exit;
    }

    $sessionKey = $row['session_key'];

    // Skicka POST till API:et
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode(['session_key' => $sessionKey])
        ]
    ];

    $response = json_decode(file_get_contents($VERIFY_URL, false, stream_context_create($options)), true);

    if (empty($response['valid'])) {
        sendJson(['error' => 'Sessionen är inte giltig', 'svar' => $response], 401);
        exit;
    }
    $mysqli->execute_query(
        "UPDATE COMPANY SET expires_at = ? WHERE company_id = ?",
        [$response['new_expires_at'] ?? null, $id]
    );
    sendJson(['success' => true, 'valid' => true, 'new_expires_at' => $response['new_expires_at']]);
    exit;
}

function logout() {
    global $mysqli, $LOGOUT_URL, $id;

    // Hämta session_key från databasen
    $stmt = $mysqli->prepare("SELECT session_key FROM COMPANY WHERE company_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        sendJson(['error' => 'Ingen session_key hittades i databasen'], 404);
        exit;
    }

    $sessionKey = $row['session_key'];

    // Skicka POST till API:et
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode(['session_key' => $sessionKey])
        ]
    ];

    $response = json_decode(file_get_contents($LOGOUT_URL, false, stream_context_create($options)), true);

    if (empty($response['success'])) {
        sendJson(['error' => 'Kunde inte logga ut hos The Provider', 'svar' => $response], 502);
        exit;
    }

    // Ta bort session_key från databasen
    $mysqli->execute_query(
        "DELETE FROM COMPANY WHERE company_id = ?",
        [$id]
    );

    sendJson(['success' => true]);
    exit;
}
?>