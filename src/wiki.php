<?php

class WikiModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Skapar en artikel, dess första historikversion och kopplar taggar i en transaktion.
     */
    public function createArticle($ownerUserId, $title, $content, $status, array $tags) {
        $this->db->begin_transaction();

        try {
            // 1. Skapa WIKI_ARTICLE
            $queryArticle = "INSERT INTO WIKI_ARTICLE (owner_user_id, status, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->db->prepare($queryArticle);
            $stmt->bind_param("is", $ownerUserId, $status);
            $stmt->execute();
            $articleId = $this->db->insert_id;
            $stmt->close();

            // 2. Skapa WIKI_HISTORY (v1)
            $queryHistory = "INSERT INTO WIKI_HISTORY (article_id, version_number, title, content, edited_at, edited_by_user_id) 
                            VALUES (?, 1, ?, ?, NOW(), ?)";
            $stmt = $this->db->prepare($queryHistory);
            $stmt->bind_param("issi", $articleId, $title, $content, $ownerUserId);
            $stmt->execute();
            $historyId = $this->db->insert_id;
            $stmt->close();

            // 3. Uppdatera pekare för historik
            $publishedHistoryId = ($status === 'published') ? $historyId : null;
            $queryUpdate = "UPDATE WIKI_ARTICLE SET current_history_id = ?, published_history_id = ? WHERE article_id = ?";
            $stmt = $this->db->prepare($queryUpdate);
            $stmt->bind_param("iii", $historyId, $publishedHistoryId, $articleId);
            $stmt->execute();
            $stmt->close();

            // 4. Koppla taggar
            if (!empty($tags)) {
                $this->addTagsToArticle($articleId, $tags);
            }

            $this->db->commit();
            return $articleId;

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
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
}