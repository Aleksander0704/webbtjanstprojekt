<?php
// login/LoginModel.php

class LoginModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function authenticate(string $username, string $password): ?array {
        // 1. Hämta användaren från USER (med rätt kolumnnamn password_hash)
        $stmt = $this->db->prepare("SELECT user_id, password_hash, is_active FROM USER WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Kontrollera om användaren finns, är aktiv och om lösenordet stämmer
        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $userId = $user['user_id'];
        
        // 2. Generera rå token (som skickas till klienten) och en sha256-hash (som sparas i databasen)
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // 3. Spara token-hashen i USER_TOKEN-tabellen
        $stmt = $this->db->prepare("INSERT INTO USER_TOKEN (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $tokenHash, $expiresAt);
        $stmt->execute();
        $stmt->close();

        // 4. Hämta tjänster (bitmask) från COMPANY-tabellen
        $resCompany = $this->db->query("SELECT services FROM COMPANY WHERE company_id = 1 LIMIT 1");
        $company = $resCompany->fetch_assoc();
        $services = $company['services'] ?? 7;

        return [
            'user_id' => (int)$userId,
            'session_key' => $rawToken, // Vi skickar råtoken till klienten (dina gruppmedlemmar kallar det token/session_key)
            'services' => (int)$services,
            'expires_at' => $expiresAt
        ];
    }
}
