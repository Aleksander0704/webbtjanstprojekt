<?php
class LoginModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Hämtar användare och dess företagets tjänster baserat på användarnamn
     */
    public function getUserByUsername(string $username) {
        // Om användaren har en company_id, eller om tjänsterna hämtas via företaget
        // Anpassa JOIN beroende på hur din USER-tabell relaterar till COMPANY (t.ex. via company_id eller om det är via sessionen)
        $query = "
            SELECT u.user_id, u.username, u.password_hash, c.company_id, c.services 
            FROM USER u
            JOIN COMPANY c ON u.company_id = c.company_id
            WHERE u.username = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        return $user;
    }

    /**
     * Skapar en ny session i USER_SESSION-tabellen
     */
    public function createSession(int $userId, int $companyId, string $sessionKey, string $expiresAt): bool {
        $query = "INSERT INTO USER_SESSION (user_id, company_id, session_key, expires_at) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iiss", $userId, $companyId, $sessionKey, $expiresAt);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}