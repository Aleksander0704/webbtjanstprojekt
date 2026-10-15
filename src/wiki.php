<?php

class WikiModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Skapar en ny wiki-artikel
     */
    public function createArticle(int $ownerUserId, string $title, string $content, string $status, array $tags): int {
        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("INSERT INTO WIKI_ARTICLE (owner_user_id, status, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $ownerUserId, $status);
            $stmt->execute();
            $articleId = $this->db->insert_id;
            $stmt->close();

            $stmt = $this->db->prepare("INSERT INTO WIKI_HISTORY (article_id, version_number, title, content, edited_at, edited_by_user_id) VALUES (?, 1, ?, ?, NOW(), ?)");
            $stmt->bind_param("issi", $articleId, $title, $content, $ownerUserId);
            $stmt->execute();
            $historyId = $this->db->insert_id;
            $stmt->close();

            $publishedHistoryId = ($status === 'published') ? $historyId : null;
            $stmt = $this->db->prepare("UPDATE WIKI_ARTICLE SET current_history_id = ?, published_history_id = ? WHERE article_id = ?");
            $stmt->bind_param("iii", $historyId, $publishedHistoryId, $articleId);
            $stmt->execute();
            $stmt->close();

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

    /**
     * Söker i titel, innehåll och taggar
     */
    public function searchArticles(string $searchTerm): array {
        $query = "SELECT DISTINCT 
                    a.article_id, 
                    a.status, 
                    a.owner_user_id, 
                    a.created_at,
                    h.title, 
                    h.content,
                    h.version_number,
                    GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS tags
                  FROM WIKI_ARTICLE a
                  JOIN WIKI_HISTORY h ON a.current_history_id = h.history_id
                  LEFT JOIN WIKI_ARTICLE_TAG vat ON a.article_id = vat.article_id
                  LEFT JOIN TAG t ON vat.tag_id = t.tag_id
                  WHERE h.title LIKE ? 
                     OR h.content LIKE ? 
                     OR t.name LIKE ?
                  GROUP BY a.article_id, h.history_id";

        $stmt = $this->db->prepare($query);
        $likeParam = "%" . $searchTerm . "%";
        $stmt->bind_param("sss", $likeParam, $likeParam, $likeParam);
        $stmt->execute();
        $result = $stmt->get_result();

        $articles = [];
        while ($row = $result->fetch_assoc()) {
            $articles[] = $row;
        }
        $stmt->close();

        return $articles;
    }

    /**
     * Hämtar artikel-ID baserat på den senaste titeln i historiken.
     */
    public function getArticleIdByTitle(string $title): ?int {
        $query = "SELECT a.article_id 
                  FROM WIKI_ARTICLE a
                  JOIN WIKI_HISTORY h ON a.current_history_id = h.history_id
                  WHERE h.title = ?
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $title);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return (int)$row['article_id'];
        }
        $stmt->close();

        return null;
    }

    /**
     * Uppdaterar en artikel genom att spara en NY version i WIKI_HISTORY
     */
    public function updateArticle(int $articleId, int $editorUserId, string $newTitle, string $content, string $status, array $tags): bool {
        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("SELECT IFNULL(MAX(version_number), 0) + 1 AS next_version FROM WIKI_HISTORY WHERE article_id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $nextVersion = $res['next_version'];
            $stmt->close();

            $stmt = $this->db->prepare("INSERT INTO WIKI_HISTORY (article_id, version_number, title, content, edited_at, edited_by_user_id) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->bind_param("iissi", $articleId, $nextVersion, $newTitle, $content, $editorUserId);
            $stmt->execute();
            $newHistoryId = $this->db->insert_id;
            $stmt->close();

            if ($status === 'published') {
                $stmt = $this->db->prepare("UPDATE WIKI_ARTICLE SET status = ?, current_history_id = ?, published_history_id = ? WHERE article_id = ?");
                $stmt->bind_param("siii", $status, $newHistoryId, $newHistoryId, $articleId);
            } else {
                $stmt = $this->db->prepare("UPDATE WIKI_ARTICLE SET status = ?, current_history_id = ? WHERE article_id = ?");
                $stmt->bind_param("sii", $status, $newHistoryId, $articleId);
            }
            $stmt->execute();
            $stmt->close();

            if (!empty($tags)) {
                $stmt = $this->db->prepare("DELETE FROM WIKI_ARTICLE_TAG WHERE article_id = ?");
                $stmt->bind_param("i", $articleId);
                $stmt->execute();
                $stmt->close();

                $this->addTagsToArticle($articleId, $tags);
            }

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Hämtar alla gamla versioner av en specifik wiki-sida
     */
    public function getArticleHistory(int $articleId): array {
        $query = "SELECT 
                    h.history_id, 
                    h.version_number, 
                    h.title, 
                    h.content, 
                    h.edited_at, 
                    h.edited_by_user_id,
                    u.username AS edited_by_username
                  FROM WIKI_HISTORY h
                  JOIN USER u ON h.edited_by_user_id = u.user_id
                  WHERE h.article_id = ?
                  ORDER BY h.version_number DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();

        return $history;
    }

    /**
     * Raderar en hel wiki-artikel och dess historik
     */
    public function deleteArticle(int $articleId): bool {
        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("UPDATE WIKI_ARTICLE SET current_history_id = NULL, published_history_id = NULL WHERE article_id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->db->prepare("DELETE FROM WIKI_ARTICLE_TAG WHERE article_id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->db->prepare("DELETE FROM WIKI_HISTORY WHERE article_id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->db->prepare("DELETE FROM WIKI_ARTICLE WHERE article_id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $deleted = $stmt->affected_rows > 0;
            $stmt->close();

            $this->db->commit();
            return $deleted;

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function addTagsToArticle(int $articleId, array $tags) {
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;

            $tagId = $this->getOrCreateTag($tagName);

            $stmt = $this->db->prepare("INSERT IGNORE INTO WIKI_ARTICLE_TAG (article_id, tag_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $articleId, $tagId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getOrCreateTag(string $tagName): int {
        $stmt = $this->db->prepare("SELECT tag_id FROM TAG WHERE name = ?");
        $stmt->bind_param("s", $tagName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return (int)$row['tag_id'];
        }
        $stmt->close();

        $stmt = $this->db->prepare("INSERT INTO TAG (name) VALUES (?)");
        $stmt->bind_param("s", $tagName);
        $stmt->execute();
        $tagId = $stmt->insert_id;
        $stmt->close();

        return $tagId;
    }
}