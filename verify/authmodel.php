<?php
class AuthModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Hämtar session och tjänster baserat på användarens session_key
     */
    public function getSessionWithServices(string $sessionKey) {
        // Om company_id t.ex. ligger direkt på sessionen eller om vi hämtar tjänsterna via en koppling
        $query = "
            SELECT 
                us.session_id,
                us.user_id,
                us.expires_at,
                c.services 
            FROM USER_SESSION us
            JOIN COMPANY c ON us.company_id = c.company_id
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