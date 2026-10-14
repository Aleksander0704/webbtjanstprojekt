<?php
// LoginModel.php

class LoginModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function authenticate(string $username, string $password): ?array {
        // 1. Hämta användaren från databasen
        $stmt = $this->db->prepare("SELECT user_id, password FROM USER WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Om användaren inte finns eller lösenordet är fel
        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        $userId = $user['user_id'];
        
        // 2. Generera unik session_key och sätt 30 minuters giltighetstid
        $sessionKey = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // 3. Spara sessionen i USER_SESSION-tabellen
        $stmt = $this->db->prepare("INSERT INTO USER_SESSION (user_id, session_key, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $sessionKey, $expiresAt);
        $stmt->execute();
        $stmt->close();

        // 4. Hämta tjänster (bitmask) från COMPANY-tabellen
        $resCompany = $this->db->query("SELECT services FROM COMPANY LIMIT 1");
        $company = $resCompany->fetch_assoc();
        $services = $company['services'] ?? 7; // Standard 7 om inget annat anges

        return [
            'user_id' => (int)$userId,
            'session_key' => $sessionKey,
            'services' => (int)$services,
            'expires_at' => $expiresAt
        ];
    }
}