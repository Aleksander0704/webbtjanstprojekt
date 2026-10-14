<?php

class BlogController {
    private $blogModel;

    public function __construct($blogModel) {
        $this->blogModel = $blogModel;
    }

    /**
     * Hanterar skapandet av ett blogginlägg.
     */
    public function createBlogPost() {
        $input = $this->getRequestData();

        $companyId   = $input['company_id'] ?? null;
        $ownerUserId = $input['owner_user_id'] ?? $input['user_id'] ?? null;
        $title       = $input['title'] ?? null;
        $content     = $input['content'] ?? null;
        $status      = $input['status'] ?? 'draft';
        $blogId      = $input['blog_id'] ?? null;
        $blogTitle   = $input['blog_title'] ?? 'Företagsblogg';
        $tagsInput   = $input['tags'] ?? $input['tag'] ?? null;

        // Validering
        if (!$companyId || !$ownerUserId || !$title || !$content) {
            $this->sendJsonResponse([
                "message" => "Fälten 'company_id', 'owner_user_id', 'title' och 'content' krävs."
            ], 400);
            return;
        }

        // Hantera taggar
        $tags = [];
        if (!empty($tagsInput)) {
            $tags = is_array($tagsInput) 
                ? array_filter(array_map('trim', $tagsInput))
                : array_filter(array_map('trim', explode(',', $tagsInput)));
        }

        $result = $this->blogModel->create($companyId, $ownerUserId, $title, $content, $status, $tags, $blogId, $blogTitle);

        if (is_array($result) && isset($result['error'])) {
            $this->sendJsonResponse([
                "message" => "Databasfel: " . $result['error']
            ], 500);
        } elseif ($result) {
            $this->sendJsonResponse([
                "message" => "Blogginlägget skapades framgångsrikt!",
                "post_id" => $result
            ], 201);
        } else {
            $this->sendJsonResponse([
                "message" => "Kunde inte skapa blogginlägget."
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