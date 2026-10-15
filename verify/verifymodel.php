<?php
// verify/VerifyModel.php

class VerifyModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function verifyToken(string $rawToken): ?array {
        if (empty($rawToken)) {
            return null;
        }

        // 1. Hasha råtoken med sha256 för att matcha lagringen i databasen
        $tokenHash = hash('sha256', $rawToken);

        // 2. Slå upp token i USER_TOKEN och kontrollera att den inte har gått ut
        $stmt = $this->db->prepare("
            SELECT t.user_id, t.expires_at, u.username, u.role 
            FROM USER_TOKEN t
            JOIN USER u ON t.user_id = u.user_id
            WHERE t.token_hash = ? AND t.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $tokenData = $result->fetch_assoc();
        $stmt->close();

        if (!$tokenData) {
            return null;
        }

        // 3. Uppdatera last_used_at för token
        $updateStmt = $this->db->prepare("UPDATE USER_TOKEN SET last_used_at = NOW() WHERE token_hash = ?");
        $updateStmt->bind_param("s", $tokenHash);
        $updateStmt->execute();
        $updateStmt->close();

        // 4. Hämta tjänster (bitmask) från COMPANY-tabellen
        $resCompany = $this->db->query("SELECT services FROM COMPANY WHERE company_id = 1 LIMIT 1");
        $company = $resCompany->fetch_assoc();
        $services = $company['services'] ?? 7;

        return [
            'user_id' => (int)$tokenData['user_id'],
            'username' => $tokenData['username'],
            'role' => $tokenData['role'],
            'services' => (int)$services,
            'expires_at' => $tokenData['expires_at']
        ];
    }
}