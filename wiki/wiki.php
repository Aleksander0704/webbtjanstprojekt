<?php

function wiki() {
    global $mysqli; // Hämtar din MySQLi-anslutning från core.php

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? null;

    // Om det är POST utan specifik action -> Skapa ny artikel (din befintliga logik)
    if ($method === 'POST' && !$action) {
        wiki_create();
    } 
    // Om det är GET -> Läs / Sök eller hämta historik
    else if ($method === 'GET') {
        if ($action === 'history') {
            wiki_history();
        } else {
            wiki_search();
        }
    } 
    // Om vi styr via action-parameter eller andra metoder
    else {
        switch ($action) {
            case 'create':
                wiki_create();
                break;
            case 'update':
                wiki_update();
                break;
            case 'delete':
                wiki_delete();
                break;
            default:
                sendJson(["error" => "Ogiltig action eller metod för wikin."], 400);
                break;
        }
    }
}


// 1. CREATE (Din befintliga kod för att skapa)

function wiki_create() {
    global $mysqli;

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    $owner_user_id = $data['owner_user_id'] ?? 1;
    $tags = $data['tags'] ?? [];
    $image = $data['image'] ?? null;

    if (empty($title) || empty($content)) {
        sendJson(["error" => "Titel och innehåll krävs!"], 400);
        return;
    }

    $mysqli->begin_transaction();

    try {
        // 1. Skapa posten i WIKI_ARTICLE med prepared statements
        $stmt = $mysqli->prepare("INSERT INTO WIKI_ARTICLE (owner_user_id, status, created_at) VALUES (?, 'published', NOW())");
        $stmt->bind_param("i", $owner_user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Kunde inte skapa artikel: " . $stmt->error);
        }

        $article_id = $mysqli->insert_id;
        $stmt->close();

        // 2. Spara version 1 i WIKI_HISTORY[cite: 1]
        data_history:
        $version_number = 1;
        $stmtHistory = $mysqli->prepare("INSERT INTO WIKI_HISTORY (article_id, version_number, title, content, edited_by_user_id, edited_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmtHistory->bind_param("iissi", $article_id, $version_number, $title, $content, $owner_user_id);
        
        if (!$stmtHistory->execute()) {
            throw new Exception("Kunde inte spara historik: " . $stmtHistory->error);
        }
        
        $history_id = $mysqli->insert_id;
        $stmtHistory->close();

        // 3. Uppdatera WIKI_ARTICLE med pekare till nuvarande/publicerad historik
        $stmtUpdate = $mysqli->prepare("UPDATE WIKI_ARTICLE SET published_history_id = ?, current_history_id = ? WHERE article_id = ?");
        $stmtUpdate->bind_param("iii", $history_id, $history_id, $article_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // 4. Hantera taggar säkert
        if (!empty($tags) && is_array($tags)) {
            foreach ($tags as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) continue;

                $stmtTagCheck = $mysqli->prepare("SELECT tag_id FROM TAG WHERE name = ?");
                $stmtTagCheck->bind_param("s", $tagName);
                $stmtTagCheck->execute();
                $result = $stmtTagCheck->get_result();

                if ($row = $result->fetch_assoc()) {
                    $tag_id = $row['tag_id'];
                } else {
                    $stmtTagInsert = $mysqli->prepare("INSERT INTO TAG (name) VALUES (?)");
                    $stmtTagInsert->bind_param("s", $tagName);
                    $stmtTagInsert->execute();
                    $tag_id = $mysqli->insert_id;
                    $stmtTagInsert->close();
                }
                $stmtTagCheck->close();

                $stmtLink = $mysqli->prepare("INSERT IGNORE INTO WIKI_ARTICLE_TAG (article_id, tag_id) VALUES (?, ?)");
                $stmtLink->bind_param("ii", $article_id, $tag_id);
                $stmtLink->execute();
                $stmtLink->close();
            }
        }

        // 5. Hantera bild (om det skickats med)
        if (!empty($image) && is_array($image)) {
            $filePath = $image['file_path'] ?? '';
            $mimeType = $image['mime_type'] ?? 'image/jpeg';
            $caption = $image['caption'] ?? null;

            if (!empty($filePath)) {
                $stmtImg = $mysqli->prepare("INSERT INTO IMAGE (file_path, mime_type, uploaded_by_user_id) VALUES (?, ?, ?)");
                $stmtImg->bind_param("ssi", $filePath, $mimeType, $owner_user_id);
                $stmtImg->execute();
                $image_id = $mysqli->insert_id;
                $stmtImg->close();

                $stmtHistImg = $mysqli->prepare("INSERT INTO WIKI_HISTORY_IMAGE (history_id, image_id, caption) VALUES (?, ?, ?)");
                $stmtHistImg->bind_param("iis", $history_id, $image_id, $caption);
                $stmtHistImg->execute();
                $stmtHistImg->close();
            }
        }

        $mysqli->commit();
        sendJson([
            "success" => true,
            "message" => "Wikisida skapad framgångsrikt!",
            "article_id" => $article_id
        ], 201);

    } catch (Exception $e) {
        $mysqli->rollback();
        sendJson(["error" => $e->getMessage()], 500);
    }
}

// 2. READ / SEARCH (Läs och sök artiklar)

function wiki_search() {
    global $mysqli;
    $queryTerm = $_GET['query'] ?? $_GET['q'] ?? $_GET['search'] ?? '';

    $sql = "SELECT DISTINCT 
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

    try {
        $stmt = $mysqli->prepare($sql);
        $like = "%" . $queryTerm . "%";
        $stmt->bind_param("sss", $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();

        $articles = [];
        while ($row = $result->fetch_assoc()) {
            $articles[] = $row;
        }
        $stmt->close();

        sendJson([
            "success" => true,
            "count" => count($articles),
            "data" => $articles
        ], 200);

    } catch (Throwable $e) {
        sendJson(["error" => "Databasfel: " . $e->getMessage()], 500);
    }
}

// 3. UPDATE (Redigera och skapa ny version)

function wiki_update() {
    global $mysqli;
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $article_id = $data['article_id'] ?? null;
    $title = $data['title'] ?? null;
    $content = $data['content'] ?? null;
    $editor_user_id = $data['user_id'] ?? $data['owner_user_id'] ?? 1;
    $status = $data['status'] ?? 'published';
    $tags = $data['tags'] ?? [];

    if (!$article_id || !$title || !$content) {
        sendJson(["error" => "Fälten 'article_id', 'title' och 'content' krävs för uppdatering."], 400);
        return;
    }

    $mysqli->begin_transaction();

    try {
        // Hämta nästa versionsnummer
        $stmt = $mysqli->prepare("SELECT IFNULL(MAX(version_number), 0) + 1 AS next_version FROM WIKI_HISTORY WHERE article_id = ?");
        $stmt->bind_param("i", $article_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $nextVersion = $res['next_version'];
        $stmt->close();

        // Spara ny version i WIKI_HISTORY
        $stmt = $mysqli->prepare("INSERT INTO WIKI_HISTORY (article_id, version_number, title, content, edited_at, edited_by_user_id) VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("iissi", $article_id, $nextVersion, $title, $content, $editor_user_id);
        $stmt->execute();
        $newHistoryId = $mysqli->insert_id;
        $stmt->close();

        // Uppdatera pekare i WIKI_ARTICLE
        $stmt = $mysqli->prepare("UPDATE WIKI_ARTICLE SET status = ?, current_history_id = ?, published_history_id = ? WHERE article_id = ?");
        $stmt->bind_param("siii", $status, $newHistoryId, $newHistoryId, $article_id);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
        sendJson([
            "success" => true,
            "message" => "Wikisidan uppdaterad till version {$nextVersion}!",
            "article_id" => $article_id
        ], 200);

    } catch (Exception $e) {
        $mysqli->rollback();
        sendJson(["error" => "Databasfel: " . $e->getMessage()], 500);
    }
}

// 4. HISTORY (Se tidigare versioner)

function wiki_history() {
    global $mysqli;
    $articleId = $_GET['article_id'] ?? $_GET['id'] ?? null;

    if (!$articleId) {
        sendJson(["error" => "Ange parametern 'article_id'."], 400);
        return;
    }

    $sql = "SELECT history_id, version_number, title, content, edited_at, edited_by_user_id 
            FROM WIKI_HISTORY 
            WHERE article_id = ? 
            ORDER BY version_number DESC";

    try {
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();

        sendJson([
            "success" => true,
            "article_id" => (int)$articleId,
            "versions" => $history
        ], 200);

    } catch (Throwable $e) {
        sendJson(["error" => "Databasfel: " . $e->getMessage()], 500);
    }
}


// 5. DELETE (Ta bort artikel)

function wiki_delete() {
    global $mysqli;
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $articleId = $data['article_id'] ?? $_GET['id'] ?? null;

    if (!$articleId) {
        sendJson(["error" => "Ange 'article_id' för att radera."], 400);
        return;
    }

    $mysqli->begin_transaction();

    try {
        // Nollställ pekare först
        $stmt = $mysqli->prepare("UPDATE WIKI_ARTICLE SET current_history_id = NULL, published_history_id = NULL WHERE article_id = ?");
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $stmt->close();

        // Ta bort tagg-kopplingar
        $stmt = $mysqli->prepare("DELETE FROM WIKI_ARTICLE_TAG WHERE article_id = ?");
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $stmt->close();

        // Ta bort historik
        $stmt = $mysqli->prepare("DELETE FROM WIKI_HISTORY WHERE article_id = ?");
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $stmt->close();

        // Ta bort själva artikeln
        $stmt = $mysqli->prepare("DELETE FROM WIKI_ARTICLE WHERE article_id = ?");
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        $mysqli->commit();

        if ($deleted) {
            sendJson(["success" => true, "message" => "Wikisidan raderades."]);
        } else {
            sendJson(["error" => "Hittade ingen artikel med det ID:t."], 404);
        }

    } catch (Exception $e) {
        $mysqli->rollback();
        sendJson(["error" => "Databasfel: " . $e->getMessage()], 500);
    }
}