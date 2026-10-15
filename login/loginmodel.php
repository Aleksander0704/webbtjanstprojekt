<?php
// login/LoginModel.php

class LoginModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function authenticate(string $username, string $password): ?array {
        // 1. Hämta användaren
        $stmt = $this->db->prepare("SELECT user_id, password_hash FROM USER WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Kontrollera om användaren finns och lösenordet stämmer
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $userId = $user['user_id'];
        
        // 2. Rensa bort eventuella gamla tokens för denna användare (så det bara finns en aktiv)
        $stmtDelete = $this->db->prepare("DELETE FROM USER_TOKEN WHERE user_id = ?");
        $stmtDelete->bind_param("i", $userId);
        $stmtDelete->execute();
        $stmtDelete->close();

        // 3. Generera rå token och sha256-hash
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // 4. Spara den nya token-hashen i USER_TOKEN-tabellen
        $stmtInsert = $this->db->prepare("INSERT INTO USER_TOKEN (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmtInsert->bind_param("iss", $userId, $tokenHash, $expiresAt);
        $stmtInsert->execute();
        $stmtInsert->close();

        // 5. Hämta tjänster (bitmask) från COMPANY-tabellen
        $resCompany = $this->db->query("SELECT services FROM COMPANY WHERE company_id = 1 LIMIT 1");
        $company = $resCompany->fetch_assoc();
        $services = $company['services'] ?? 7;

        return [
            'user_id' => (int)$userId,
            'session_key' => $rawToken,
            'services' => (int)$services,
            'expires_at' => $expiresAt
        ];
    }
}