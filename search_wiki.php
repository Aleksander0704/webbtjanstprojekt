<?php
require_once 'db.php';

$searchKeyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$currentUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($currentUserId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ogiltig eller saknad user_id']);
    exit;
}

// 1. Hämta company_id och services (bitmask)
$stmt = $mysqli->prepare("
    SELECT u.company_id, c.services 
    FROM USER u
    JOIN COMPANY c ON u.company_id = c.company_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Användaren hittades inte']);
    exit;
}

$companyId = $user['company_id'];
$servicesBitmask = intval($user['services']);

// 2. Kontrollera om företaget har tillgång till wiki (antag att wiki har bitvärde 2)
$hasWikiAccess = ($servicesBitmask & 2) === 2;

if (!$hasWikiAccess) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Företaget saknar behörighet till wikitjänsten']);
    exit;
}

$searchTerm = "%" . mb_strtolower($searchKeyword) . "%";

// 3. SQL endast för wiki
$query = "
    SELECT 
        wa.article_id AS id,
        wh.title AS title,
        wh.content AS content,
        wa.status,
        wa.company_id
    FROM WIKI_ARTICLE wa
    JOIN WIKI_HISTORY wh ON wa.current_history_id = wh.history_id
    LEFT JOIN WIKI_ARTICLE_TAG wat ON wa.article_id = wat.article_id
    LEFT JOIN TAG t ON wat.tag_id = t.tag_id
    WHERE (LOWER(wh.title) LIKE ? OR LOWER(wh.content) LIKE ? OR LOWER(t.name) LIKE ?)
      AND (wa.status = 'published' OR wa.company_id = ?)
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $companyId);
$stmt->execute();
$result = $stmt->get_result();

$articles = [];
while ($row = $result->fetch_assoc()) {
    $articles[] = $row;
}

$stmt->close();
$mysqli->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'count' => count($articles),
    'results' => $articles
], JSON_PRETTY_PRINT);