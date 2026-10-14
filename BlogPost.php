<?php

class BlogPost {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($ownerUserId, $title, $content, $status = 'draft', array $tags = [], $blogId = null, $blogTitle = "Företagsblogg") {
        $this->conn->begin_transaction();

        try {
            // 1. Hämta eller skapa blogg för användaren
            if (!$blogId) {
                $blogId = $this->getOrCreateBlog($ownerUserId, $blogTitle);
            }

            $isPublished      = ($status === 'published');
            $publishedTitle   = $isPublished ? $title : null;
            $publishedContent = $isPublished ? $content : null;
            $publishedAt      = $isPublished ? date('Y-m-d H:i:s') : null;

            // 2. Skapa post i BLOG_POST
            $query = "INSERT INTO BLOG_POST 
                      (blog_id, status, published_title, published_content, draft_title, draft_content, published_at, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("issssss", $blogId, $status, $publishedTitle, $publishedContent, $title, $content, $publishedAt);

            if (!$stmt->execute()) {
                throw new Exception("Kunde inte skapa post i BLOG_POST: " . $stmt->error);
            }

            $postId = $this->conn->insert_id;
            $stmt->close();

            // 3. Tagg-koppling
            if (!empty($tags)) {
                $this->addTagsToPost($postId, $tags);
            }

            $this->conn->commit();
            return $postId;

        } catch (Throwable $e) {
            $this->conn->rollback();
            return ["error" => $e->getMessage()];
        }
    }

    private function getOrCreateBlog($ownerUserId, $blogTitle) {
        $query = "SELECT blog_id FROM BLOG WHERE owner_user_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $ownerUserId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['blog_id'];
        }
        $stmt->close();

        $insertQuery = "INSERT INTO BLOG (owner_user_id, title, created_at) VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($insertQuery);
        $stmt->bind_param("is", $ownerUserId, $blogTitle);
        $stmt->execute();
        $blogId = $this->conn->insert_id;
        $stmt->close();

        return $blogId;
    }

    private function addTagsToPost($postId, array $tags) {
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;

            $tagId = $this->getOrCreateTag($tagName);

            $query = "INSERT IGNORE INTO BLOG_POST_TAG (post_id, tag_id) VALUES (?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $postId, $tagId);
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