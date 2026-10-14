<?php

class WikiController {
    private $wikiModel;

    public function __construct($wikiModel) {
        $this->wikiModel = $wikiModel;
    }

    /**
     * Handles the creation of a new Wiki article.
     */
    public function createWikiArticle() {
        $input = $this->getRequestData();

        $companyId   = $input['company_id'] ?? null;
        $ownerUserId = $input['owner_user_id'] ?? $input['user_id'] ?? null;
        $title       = $input['title'] ?? null;
        $content     = $input['content'] ?? null;
        $status      = $input['status'] ?? 'draft';
        $tagsInput   = $input['tags'] ?? $input['tag'] ?? null;

        // 1. Validate required fields
        if (!$companyId || !$ownerUserId || !$title || !$content) {
            $this->sendJsonResponse([
                "message" => "Fälten 'company_id', 'owner_user_id', 'title' och 'content' krävs."
            ], 400);
            return;
        }

        // 2. Parse tags (supports array or comma-separated string)
        $tags = [];
        if (!empty($tagsInput)) {
            $tags = is_array($tagsInput) 
                ? array_filter(array_map('trim', $tagsInput))
                : array_filter(array_map('trim', explode(',', $tagsInput)));
        }

        // 3. Create the article via model
        $result = $this->wikiModel->create($companyId, $ownerUserId, $title, $content, $status, $tags);

        // 4. Handle response
        if (is_array($result) && isset($result['error'])) {
            $this->sendJsonResponse([
                "message" => "Databasfel: " . $result['error']
            ], 500);
        } elseif ($result) {
            $this->sendJsonResponse([
                "message" => "Wiki-artikeln skapades framgångsrikt!",
                "article_id" => $result
            ], 201);
        } else {
            $this->sendJsonResponse([
                "message" => "Kunde inte skapa wiki-artikeln."
            ], 500);
        }
    }

    /**
     * Reads request payload from raw JSON body or form POST data.
     */
    private function getRequestData(): array {
        $rawJson = file_get_contents('php://input');
        $decodedJson = json_decode($rawJson, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
            return $decodedJson;
        }

        return $_POST;
    }

    /**
     * Helper to output JSON response with specified HTTP status code.
     */
    private function sendJsonResponse($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}