<?php
// AuthModel.php

class AuthModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Hämtar session via token_hash och tillhörande tjänster från COMPANY.
     */
    public function getSessionWithServices(string $rawToken) {
        // Hasha den inkommande token med sha256 för att matcha lagringen i USER_TOKEN
        $tokenHash = hash('sha256', $rawToken);

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

        return $sessionData;
    }

    /**
     * Förlänger giltighetstiden för token i USER_TOKEN.
     */
    public function extendSession(string $rawToken): string {
        $tokenHash = hash('sha256', $rawToken);
        $newExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $query = "UPDATE USER_TOKEN SET expires_at = ?, last_used_at = NOW() WHERE token_hash = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ss", $newExpiresAt, $tokenHash);
        $stmt->execute();
        $stmt->close();

        return $newExpiresAt;
    }
}