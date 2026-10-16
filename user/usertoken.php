<?php

require_once __DIR__ . '/../core.php';

function createToken($username, $password) {
    global $mysqli;

    // Hämta user_id och lösenordshashen
    $stmt = $mysqli->prepare("SELECT user_id, password_hash FROM USER WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    // Samma fel oavsett om användaren saknas eller lösenordet är fel
    if (!$row || !password_verify($password, $row['password_hash'])) {
        sendJson(['error' => 'Fel användarnamn eller lösenord'], 401);
        exit;
    }
    $userId = $row['user_id'];

    // Skapa token: råvärdet skickas till användaren, hashen sparas
    $token      = bin2hex(random_bytes(32));          // råa token (64 tecken)
    $tokenHash  = hash('sha256', $token);             // hash att spara (CHAR(64))
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // Rensa gamla tokens som har gått ut
    $mysqli->execute_query(
        "DELETE FROM USER_TOKEN WHERE expires_at < ?",
        [date('Y-m-d H:i:s')]
    );

    $mysqli->execute_query(
        "INSERT INTO USER_TOKEN (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
        [$userId, $tokenHash, $expires_at]
    );

    // Skicka tillbaka råa token till användaren (den finns bara här, aldrig i DB)
    sendJson(['success' => true, 'token' => $token, 'expires_at' => $expires_at]);
    exit;
}

function listtoken() {
    global $mysqli;

    $result = $mysqli->query("SELECT token_hash, expires_at FROM USER_TOKEN");
    $tokens = $result->fetch_all(MYSQLI_ASSOC);

    sendJson(['success' => true, 'tokens' => $tokens]);
    exit;
}

function verifyToken($token) {
    global $mysqli;

    $tokenHash = hash('sha256', $token);

    $stmt = $mysqli->prepare("SELECT user_id, expires_at FROM USER_TOKEN WHERE token_hash = ?");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        sendJson(['error' => 'Ogiltig token'], 401);
        exit;
    }

    if (new DateTime() > new DateTime($row['expires_at'])) {
        sendJson(['error' => 'Token har gått ut'], 401);
        exit;
    }

    // Förläng 30 min och spara när token användes senast
    $nu         = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    $mysqli->execute_query(
        "UPDATE USER_TOKEN SET expires_at = ?, last_used_at = ? WHERE token_hash = ?",
        [$expires_at, $nu, $tokenHash]
    );

    sendJson(['success' => true, 'user_id' => $row['user_id'], 'expires_at' => $expires_at]);
    exit;
}

function userlogout($token) {
    global $mysqli;

    $tokenHash = hash('sha256', $token);

    $stmt = $mysqli->prepare("DELETE FROM USER_TOKEN WHERE token_hash = ?");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        sendJson(['error' => 'Ogiltig token'], 401);
        exit;
    }

    sendJson(['success' => true, 'message' => 'Token har tagits bort']);
    exit;
}

function inloggadUser($token) {
    global $mysqli;

    $row = $mysqli->execute_query(
        "SELECT u.user_id, u.role FROM USER_TOKEN t JOIN USER u ON u.user_id = t.user_id
        WHERE t.token_hash = ? AND t.expires_at > ?",
        [hash('sha256', $token), date('Y-m-d H:i:s')]
    )->fetch_assoc();

    if (!$row) {
        sendJson(['error' => 'Inte inloggad'], 401);
        exit;
    }
    return $row;
}

