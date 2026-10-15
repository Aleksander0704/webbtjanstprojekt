<?php

require_once __DIR__ . '/wiki.php';

class WikiController {
    private $wikiModel;

    public function __construct($db) {
        $this->wikiModel = new WikiModel($db);
    }

    public function create() {
        $input = $this->getRequestData();

        $ownerUserId = $input['owner_user_id'] ?? $input['user_id'] ?? null;
        $title       = $input['title'] ?? null;
        $content     = $input['content'] ?? null;
        $status      = $input['status'] ?? 'published';
        $tags        = $this->parseTags($input['tags'] ?? null);

        if (!$ownerUserId || !$title || !$content) {
            $this->sendJsonResponse(["message" => "Fälten 'owner_user_id', 'title' och 'content' krävs."], 400);
            return;
        }

        try {
            $articleId = $this->wikiModel->createArticle((int)$ownerUserId, $title, $content, $status, $tags);
            $this->sendJsonResponse([
                "message" => "Wiki-artikeln skapades framgångsrikt!",
                "article_id" => $articleId
            ], 201);
        } catch (Throwable $e) {
            $this->sendJsonResponse(["message" => "Databasfel: " . $e->getMessage()], 500);
        }
    }

    public function search() {
        $searchTerm = $_GET['query'] ?? $_GET['q'] ?? $_GET['search'] ?? '';

        if (empty(trim($searchTerm))) {
            $this->sendJsonResponse(["message" => "Ange en sökterm via parametern 'query' eller 'q'."], 400);
            return;
        }

        try {
            $results = $this->wikiModel->searchArticles($searchTerm);
            $this->sendJsonResponse([
                "count" => count($results),
                "data" => $results
            ], 200);
        } catch (Throwable $e) {
            $this->sendJsonResponse(["message" => "Databasfel: " . $e->getMessage()], 500);
        }
    }

public function update() {
        $input = $this->getRequestData();

        // Användaren kan skicka 'current_title' (om titeln ska ändras) eller bara 'title'
        $lookupTitle = $input['current_title'] ?? $input['title'] ?? null;
        $newTitle    = $input['title'] ?? $lookupTitle;
        $editorUserId = $input['user_id'] ?? $input['owner_user_id'] ?? null;
        $content      = $input['content'] ?? null;
        $status       = $input['status'] ?? 'published';
        $tags         = $this->parseTags($input['tags'] ?? null);

        if (!$lookupTitle || !$editorUserId || !$content) {
            $this->sendJsonResponse(["message" => "Fälten 'title' (eller 'current_title'), 'user_id' och 'content' krävs."], 400);
            return;
        }

        // Slå upp article_id baserat på titeln
        $articleId = $this->wikiModel->getArticleIdByTitle($lookupTitle);

        if (!$articleId) {
            $this->sendJsonResponse(["message" => "Hittade ingen wiki-sida med titeln: '{$lookupTitle}'."], 404);
            return;
        }

        try {
            $this->wikiModel->updateArticle($articleId, (int)$editorUserId, $newTitle, $content, $status, $tags);
            $this->sendJsonResponse(["message" => "Wiki-artikeln med titeln '{$lookupTitle}' har uppdaterats!"]);
        } catch (Throwable $e) {
            $this->sendJsonResponse(["message" => "Databasfel: " . $e->getMessage()], 500);
        }
    }

    public function history() {
        $articleId = $_GET['article_id'] ?? $_GET['id'] ?? null;

        if (!$articleId) {
            $this->sendJsonResponse(["message" => "Ange parametern 'article_id'."], 400);
            return;
        }

        try {
            $history = $this->wikiModel->getArticleHistory((int)$articleId);
            $this->sendJsonResponse([
                "article_id" => (int)$articleId,
                "total_versions" => count($history),
                "history" => $history
            ], 200);
        } catch (Throwable $e) {
            $this->sendJsonResponse(["message" => "Databasfel: " . $e->getMessage()], 500);
        }
    }

    public function delete() {
        $input = $this->getRequestData();
        $articleId = $input['article_id'] ?? $_GET['id'] ?? null;

        if (!$articleId) {
            $this->sendJsonResponse(["message" => "Ange 'article_id' för sidan som ska raderas."], 400);
            return;
        }

        try {
            $deleted = $this->wikiModel->deleteArticle((int)$articleId);
            if ($deleted) {
                $this->sendJsonResponse(["message" => "Wiki-artikeln raderades."]);
            } else {
                $this->sendJsonResponse(["message" => "Hittade ingen artikel med angivet ID."], 404);
            }
        } catch (Throwable $e) {
            $this->sendJsonResponse(["message" => "Databasfel: " . $e->getMessage()], 500);
        }
    }

    private function parseTags($tagsInput): array {
        if (empty($tagsInput)) return [];
        return is_array($tagsInput) 
            ? array_filter(array_map('trim', $tagsInput))
            : array_filter(array_map('trim', explode(',', $tagsInput)));
    }

    private function getRequestData(): array {
        $rawJson = file_get_contents('php://input');
        $decodedJson = json_decode($rawJson, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) ? $decodedJson : $_POST;
    }

    private function sendJsonResponse($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}