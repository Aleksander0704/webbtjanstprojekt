<?php
require_once 'db.php';

$searchKeyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$currentUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($currentUserId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ogiltig eller saknad user_id']);
    exit;
}

// 1. Hämta användarens company_id för behörighetskontroll av utkast
$stmt = $mysqli->prepare("SELECT company_id FROM USER WHERE user_id = ?");
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
$searchTerm = "%" . mb_strtolower($searchKeyword) . "%";

// 2. Sök-query med UNION ALL
$query = "
    -- BLOGGPOSTER
    SELECT 
        'blog' AS type,
        bp.post_id AS id,
        COALESCE(bp.published_title, bp.draft_title) AS title,
        COALESCE(bp.published_content, bp.draft_content) AS content,
        bp.status,
        b.company_id
    FROM BLOG_POST bp
    JOIN BLOG b ON bp.blog_id = b.blog_id
    LEFT JOIN BLOG_POST_TAG bpt ON bp.post_id = bpt.post_id
    LEFT JOIN TAG t ON bpt.tag_id = t.tag_id
    WHERE (LOWER(bp.published_title) LIKE ? OR LOWER(bp.published_content) LIKE ? OR LOWER(bp.draft_title) LIKE ? OR LOWER(bp.draft_content) LIKE ? OR LOWER(t.name) LIKE ?)
      AND (bp.status = 'published' OR b.company_id = ?)

    UNION

    -- WIKI-ARTIKLAR
    SELECT 
        'wiki' AS type,
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

// Bind parametrar (5 för blogg + 1 för company_id, 3 för wiki + 1 för company_id)
$stmt->bind_param(
    "sssssisiis", 
    $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $companyId,
    $searchTerm, $searchTerm, $searchTerm, $companyId
);

$stmt->execute();
$result = $stmt->get_result();
$searchResults = [];

while ($row = $result->fetch_assoc()) {
    $searchResults[] = $row;
}

$stmt->close();
$mysqli->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'query' => $searchKeyword,
    'count' => count($searchResults),
    'results' => $searchResults
], JSON_PRETTY_PRINT);