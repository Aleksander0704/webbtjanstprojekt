<?php

class WikiArticle {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($ownerUserId, $title, $content, $status = 'draft', array $tags = []) {
        $this->conn->begin_transaction();

        try {
            // 1. Skapa post i WIKI_ARTICLE (utan company_id)
            $queryArticle = "INSERT INTO WIKI_ARTICLE (owner_user_id, status, created_at) VALUES (?, ?, NOW())";
            $stmtArticle = $this->conn->prepare($queryArticle);
            $stmtArticle->bind_param("is", $ownerUserId, $status);

            if (!$stmtArticle->execute()) {
                throw new Exception("Kunde inte skapa post i WIKI_ARTICLE: " . $stmtArticle->error);
            }

            $articleId = $this->conn->insert_id;
            $stmtArticle->close();

            // 2. Skapa v1 i WIKI_HISTORY
            $queryHistory = "INSERT INTO WIKI_HISTORY (article_id, version_number, title, content, edited_at, edited_by_user_id) VALUES (?, 1, ?, ?, NOW(), ?)";
            $stmtHistory = $this->conn->prepare($queryHistory);
            $stmtHistory->bind_param("issi", $articleId, $title, $content, $ownerUserId);

            if (!$stmtHistory->execute()) {
                throw new Exception("Kunde inte skapa post i WIKI_HISTORY: " . $stmtHistory->error);
            }

            $historyId = $this->conn->insert_id;
            $stmtHistory->close();

            // 3. Koppla nuvarande historik-ID
            $publishedHistoryId = ($status === 'published') ? $historyId : null;
            $queryUpdate = "UPDATE WIKI_ARTICLE SET current_history_id = ?, published_history_id = ? WHERE article_id = ?";
            $stmtUpdate = $this->conn->prepare($queryUpdate);
            $stmtUpdate->bind_param("iii", $historyId, $publishedHistoryId, $articleId);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            // 4. Koppla taggar
            if (!empty($tags)) {
                $this->addTagsToArticle($articleId, $tags);
            }

            $this->conn->commit();
            return $articleId;

        } catch (Throwable $e) {
            $this->conn->rollback();
            return ["error" => $e->getMessage()];
        }
    }

    private function addTagsToArticle($articleId, array $tags) {
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;

            $tagId = $this->getOrCreateTag($tagName);

            $query = "INSERT IGNORE INTO WIKI_ARTICLE_TAG (article_id, tag_id) VALUES (?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $articleId, $tagId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getOrCreateTag($tagName) {
        $query = "SELECT tag_id FROM TAG WHERE name = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $tagName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['tag_id'];
        }
        $stmt->close();

        $insertQuery = "INSERT INTO TAG (name) VALUES (?)";
        $stmt = $this->conn->prepare($insertQuery);
        $stmt->bind_param("s", $tagName);
        $stmt->execute();
        $tagId = $this->conn->insert_id;
        $stmt->close();

        return $tagId;
    }
}