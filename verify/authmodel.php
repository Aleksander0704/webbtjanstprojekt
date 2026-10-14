<?php
class AuthModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Hämtar session och tjänster. 
     * Eftersom det bara finns ett företag hämtar vi tjänsterna därifrån direkt.
     */
    public function getSessionWithServices(string $sessionKey) {
        $query = "
            SELECT 
                us.session_id,
                us.user_id,
                us.expires_at,
                (SELECT services FROM COMPANY LIMIT 1) AS services
            FROM USER_SESSION us
            WHERE us.session_key = ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $sessionData = $result->fetch_assoc();
        $stmt->close();

        return $sessionData;
    }

    public function extendSession(string $sessionKey): string {
        $newExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $query = "UPDATE USER_SESSION SET expires_at = ? WHERE session_key = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ss", $newExpiresAt, $sessionKey);
        $stmt->execute();
        $stmt->close();

        return $newExpiresAt;
    }
}