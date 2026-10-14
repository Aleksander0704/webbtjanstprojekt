<?php

require_once __DIR__ . '/wiki.php';

class WikiController {
    private $wikiModel;

    public function __construct($db) {
        $this->wikiModel = new WikiModel($db);
    }

    /**
     * Skapar en ny artikel baserat på inkommande request.
     */
    public function create() {
        $input = $this->getRequestData();

        $ownerUserId = $input['owner_user_id'] ?? $input['user_id'] ?? null;
        $title       = $input['title'] ?? null;
        $content     = $input['content'] ?? null;
        $status      = $input['status'] ?? 'draft';
        $tagsInput   = $input['tags'] ?? $input['tag'] ?? null;

        // Validering
        if (!$ownerUserId || !$title || !$content) {
            $this->sendJsonResponse([
                "message" => "Fälten 'owner_user_id', 'title' och 'content' krävs."
            ], 400);
            return;
        }

        // Hantera taggar (om skickat som array eller kommaseparerad sträng)
        $tags = [];
        if (!empty($tagsInput)) {
            $tags = is_array($tagsInput) 
                ? array_filter(array_map('trim', $tagsInput))
                : array_filter(array_map('trim', explode(',', $tagsInput)));
        }

        try {
            $articleId = $this->wikiModel->createArticle($ownerUserId, $title, $content, $status, $tags);
            $this->sendJsonResponse([
                "message" => "Wiki-artikeln skapades framgångsrikt!",
                "article_id" => $articleId
            ], 201);
        } catch (Throwable $e) {
            $this->sendJsonResponse([
                "message" => "Databasfel: " . $e->getMessage()
            ], 500);
        }
    }

    private function getRequestData(): array {
        $rawJson = file_get_contents('php://input');
        $decodedJson = json_decode($rawJson, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
            return $decodedJson;
        }

        return $_POST;
    }

    private function sendJsonResponse($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}