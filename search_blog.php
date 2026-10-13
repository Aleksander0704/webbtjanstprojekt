<?php
require_once 'db.php';

$searchKeyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$currentUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($currentUserId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ogiltig eller saknad user_id']);
    exit;
}

// 1. Hämta företagets ID
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

// 2. Ren och enkel SQL endast för bloggar
$query = "
    SELECT 
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
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("sssssi", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $companyId);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}

$stmt->close();
$mysqli->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'count' => count($posts),
    'results' => $posts
], JSON_PRETTY_PRINT);