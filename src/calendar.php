<?php

/*
 * Kalendertjänsten (services & 1).
 * Skapa, redigera och se event, bjuda in anställda och svara på inbjudningar.
 * Globala event (is_company_wide) syns för alla anställda.
 *
 * Databasen ($mysqli) läses in av public/index.php.
 */

// Svara med JSON och avsluta
function svara(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Läser både JSON-body och vanlig form-data
function hamta_indata(): array {
    return json_decode(file_get_contents('php://input'), true) ?? $_POST;
}

// Vem är inloggad? Ger user_id, annars stoppas anropet med 401
function inloggad_user(mysqli $db, array $in): int {
    $nyckel = $in['session_key'] ?? $_GET['session_key'] ?? '';
    $rad = $db->execute_query(
        "SELECT t.user_id FROM USER_TOKEN t JOIN USER u ON u.user_id = t.user_id
         WHERE t.token_hash = ? AND t.expires_at > ? AND u.is_active = 1",
        [hash('sha256', $nyckel), date('Y-m-d H:i:s')]
    )->fetch_assoc();

    if (!$rad) svara(['error' => 'Inte inloggad'], 401);
    return (int)$rad['user_id'];
}

// Har företaget betalat för kalendern? (1 = kalender, 2 = blogg, 4 = wiki)
function kraver_kalender(mysqli $db): void {
    $services = (int)($db->execute_query("SELECT services FROM COMPANY LIMIT 1")->fetch_assoc()['services'] ?? 0);
    if (!($services & 1)) svara(['error' => 'Företaget har inte kalendertjänsten'], 403);
}

// GET /calendar/list
function kalender_lista(mysqli $db, int $userId): void {
    $events = $db->execute_query(
        "SELECT e.*, p.status AS mitt_svar
         FROM CALENDAR_EVENT e
         LEFT JOIN EVENT_PARTICIPANT p ON p.event_id = e.event_id AND p.user_id = ?
         WHERE e.is_company_wide = 1 OR e.created_by_user_id = ? OR p.user_id IS NOT NULL
         ORDER BY e.start_time",
        [$userId, $userId]
    )->fetch_all(MYSQLI_ASSOC);

    svara(['data' => $events]);
}

// POST /calendar/create  { title, start_time, end_time, description?, location?, is_company_wide?, invite: [user_id, ...] }
function kalender_skapa(mysqli $db, int $userId, array $in): void {
    if (empty($in['title']) || empty($in['start_time']) || empty($in['end_time'])) {
        svara(['error' => 'title, start_time och end_time krävs'], 400);
    }
    if ($in['end_time'] < $in['start_time']) {
        svara(['error' => 'Eventet slutar innan det börjar'], 400);
    }

    $db->execute_query(
        "INSERT INTO CALENDAR_EVENT (title, description, start_time, end_time, location, is_company_wide, created_by_user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$in['title'], $in['description'] ?? null, $in['start_time'], $in['end_time'],
         $in['location'] ?? null, empty($in['is_company_wide']) ? 0 : 1, $userId]
    );
    $eventId = $db->insert_id;

    // Den som skapar blir organizer, de inbjudna får status 'pending'
    $db->execute_query(
        "INSERT INTO EVENT_PARTICIPANT (event_id, user_id, role, status) VALUES (?, ?, 'organizer', 'accepted')",
        [$eventId, $userId]
    );
    foreach ($in['invite'] ?? [] as $gast) {
        $db->execute_query("INSERT IGNORE INTO EVENT_PARTICIPANT (event_id, user_id) VALUES (?, ?)", [$eventId, (int)$gast]);
    }

    svara(['event_id' => $eventId], 201);
}

// POST /calendar/update  { event_id, title, start_time, end_time, description?, location?, is_company_wide? }
function kalender_andra(mysqli $db, int $userId, array $in): void {
    if (empty($in['event_id']) || empty($in['title']) || empty($in['start_time']) || empty($in['end_time'])) {
        svara(['error' => 'event_id, title, start_time och end_time krävs'], 400);
    }
    if ($in['end_time'] < $in['start_time']) {
        svara(['error' => 'Eventet slutar innan det börjar'], 400);
    }

    // Bara den som skapade eventet får ändra det
    $finns = $db->execute_query(
        "SELECT 1 FROM CALENDAR_EVENT WHERE event_id = ? AND created_by_user_id = ?",
        [(int)$in['event_id'], $userId]
    )->fetch_assoc();
    if (!$finns) svara(['error' => 'Eventet finns inte eller är inte ditt'], 404);

    $db->execute_query(
        "UPDATE CALENDAR_EVENT
         SET title = ?, description = ?, start_time = ?, end_time = ?, location = ?, is_company_wide = ?
         WHERE event_id = ?",
        [$in['title'], $in['description'] ?? null, $in['start_time'], $in['end_time'],
         $in['location'] ?? null, empty($in['is_company_wide']) ? 0 : 1, (int)$in['event_id']]
    );

    svara(['success' => true]);
}

// POST /calendar/respond  { event_id, status: "accepted" | "declined" }
function kalender_svara(mysqli $db, int $userId, array $in): void {
    $status = $in['status'] ?? '';
    if (!in_array($status, ['accepted', 'declined'])) {
        svara(['error' => "status måste vara 'accepted' eller 'declined'"], 400);
    }

    $inbjuden = $db->execute_query(
        "SELECT 1 FROM EVENT_PARTICIPANT WHERE event_id = ? AND user_id = ?",
        [(int)($in['event_id'] ?? 0), $userId]
    )->fetch_assoc();
    if (!$inbjuden) svara(['error' => 'Du är inte inbjuden till det eventet'], 404);

    $db->execute_query(
        "UPDATE EVENT_PARTICIPANT SET status = ?, responded_at = NOW() WHERE event_id = ? AND user_id = ?",
        [$status, (int)$in['event_id'], $userId]
    );

    svara(['success' => true]);
}
