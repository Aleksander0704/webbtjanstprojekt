<?php

require_once __DIR__ . '/../core.php';

function adduser(string $username, string $password, string $role = 'user') {
    global $mysqli, $token;
    if (inloggadUser($token)['role'] !== 'admin') { sendJson(['error' => 'Bara admin'], 403); exit; }

    if ($username === '' || $password === '') {
        sendJson(['error' => 'username och password krävs'], 400);
        exit;
    }

    try {
        $mysqli->execute_query(
            "INSERT INTO USER (username, password_hash, role) VALUES (?, ?, ?)",
            [$username, password_hash($password, PASSWORD_DEFAULT), $role]
        );
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) { // 1062 = dublett, namnet finns redan
            sendJson(['error' => 'Användarnamnet är upptaget'], 409);
            exit;
        }
        throw $e;
    }

    sendJson(['success' => true, 'user_id' => $mysqli->insert_id], 201);
    exit;
}

function deluser(string $username) {
    global $mysqli, $token;
    if (inloggadUser($token)['role'] !== 'admin') { sendJson(['error' => 'Bara admin'], 403); exit; }

    if ($username === '') {
        sendJson(['error' => 'username krävs'], 400);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM USER WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        sendJson(['error' => 'Användaren hittades inte'], 404);
        exit;
    }

    sendJson(['success' => true, 'deleted_user' => $username]);
    exit;
}

function listusers() {
    global $mysqli;

    $result = $mysqli->query("SELECT username, role FROM USER");
    $users = $result->fetch_all(MYSQLI_ASSOC);

    sendJson(['success' => true, 'users' => $users]);
    exit;
}