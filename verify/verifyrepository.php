<?php
class VerifyRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getSessionWithServices(string $tokenHash): ?array {
        $query = "
            SELECT 
                ut.token_id AS session_id,
                ut.user_id,
                ut.expires_at,
                (SELECT services FROM COMPANY WHERE company_id = 1 LIMIT 1) AS services
            FROM USER_TOKEN ut
            WHERE ut.token_hash = ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $sessionData = $result->fetch_assoc();
        $stmt->close();

        return $sessionData ?: null;
    }

    public function extendSession(string $tokenHash, string $newExpiresAt): void {
        $query = "UPDATE USER_TOKEN SET expires_at = ?, last_used_at = NOW() WHERE token_hash = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ss", $newExpiresAt, $tokenHash);
        $stmt->execute();
        $stmt->close();
    }
}