<?php

/*
 * Wikitjänsten (services & 4).
 * Skapa, uppdatera, ta bort och läsa sidor, sök, bilder och historik.
 * Varje ändring sparas som en ny version i WIKI_HISTORY.
 */

require_once __DIR__ . '/core.php';

class WikiService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Hanterar inkommande request för att skapa en wiki-artikel.
     */
    public function createWikiArticle() {
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

        // Hantera taggar
        $tags = [];
        if (!empty($tagsInput)) {
            $tags = is_array($tagsInput) 
                ? array_filter(array_map('trim', $tagsInput))
                : array_filter(array_map('trim', explode(',', $tagsInput)));
        }

        // Skapa artikeln i databasen
        $result = $this->createArticleInDb($ownerUserId, $title, $content, $status, $tags);

        if (is_array($result) && isset($result['error'])) {
            $this->sendJsonResponse(["message" => "Databasfel: " . $result['error']], 500);
        } elseif ($result) {
            $this->sendJsonResponse([
                "message" => "Wiki-artikeln skapades framgångsrikt!",
                "article_id" => $result
            ], 201);
        } else {
            $this->sendJsonResponse(["message" => "Kunde inte skapa wiki-artikeln."], 500);
        }
    }

    /**
     * Databasinteraktion med transaktioner för WIKI_ARTICLE, WIKI_HISTORY och TAG.
     */
    private function createArticleInDb($ownerUserId, $title, $content, $status, array $tags) {
        $this->db->begin_transaction();

        try {
            // 1. Skapa post i WIKI_ARTICLE (isolerad databas, ingen company_id)
            $queryArticle = "INSERT INTO WIKI_ARTICLE (owner_user_id, status, created_at) VALUES (?, ?, NOW())";
            $stmtArticle = $this->db->prepare($queryArticle);
            $stmtArticle->bind_param("is", $ownerUserId, $status);

            if (!$stmtArticle->execute()) {
                throw new Exception("Kunde inte skapa WIKI_ARTICLE: " . $stmtArticle->error);
            }

            $articleId = $this->db->insert_id;
            $stmtArticle->close();

            // 2. Skapa första versionen i WIKI_HISTORY (version_number = 1)
            $queryHistory = "INSERT INTO WIKI_HISTORY (article_id, version_number, title, content, edited_at, edited_by_user_id) VALUES (?, 1, ?, ?, NOW(), ?)";
            $stmtHistory = $this->db->prepare($queryHistory);
            $stmtHistory->bind_param("issi", $articleId, $title, $content, $ownerUserId);

            if (!$stmtHistory->execute()) {
                throw new Exception("Kunde inte skapa WIKI_HISTORY: " . $stmtHistory->error);
            }

            $historyId = $this->db->insert_id;
            $stmtHistory->close();

            // 3. Koppla nuvarande historik-ID till artikeln
            $publishedHistoryId = ($status === 'published') ? $historyId : null;
            $queryUpdate = "UPDATE WIKI_ARTICLE SET current_history_id = ?, published_history_id = ? WHERE article_id = ?";
            $stmtUpdate = $this->db->prepare($queryUpdate);
            $stmtUpdate->bind_param("iii", $historyId, $publishedHistoryId, $articleId);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            // 4. Lägg till taggar om de finns
            if (!empty($tags)) {
                $this->addTagsToArticle($articleId, $tags);
            }

            $this->db->commit();
            return $articleId;

        } catch (Throwable $e) {
            $this->db->rollback();
            return ["error" => $e->getMessage()];
        }
    }

    private function addTagsToArticle($articleId, array $tags) {
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;

            $tagId = $this->getOrCreateTag($tagName);

            $query = "INSERT IGNORE INTO WIKI_ARTICLE_TAG (article_id, tag_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $articleId, $tagId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getOrCreateTag($tagName) {
        $query = "SELECT tag_id FROM TAG WHERE name = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $tagName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['tag_id'];
        }
        $stmt->close();

        $insertQuery = "INSERT INTO TAG (name) VALUES (?)";
        $stmt = $this->db->prepare($insertQuery);
        $stmt->bind_param("s", $tagName);
        $stmt->execute();
        $tagId = $this->db->insert_id;
        $stmt->close();

        return $tagId;
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