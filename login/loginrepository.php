<?php
class LoginRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getUserByUsername(string $username): ?array {
        $stmt = $this->db->prepare("SELECT user_id, password_hash FROM USER WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    public function deleteTokensForUser(int $userId): void {
        $stmt = $this->db->prepare("DELETE FROM USER_TOKEN WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }

    public function saveToken(int $userId, string $tokenHash, string $expiresAt): void {
        $stmt = $this->db->prepare("INSERT INTO USER_TOKEN (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $tokenHash, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }

    public function getCompanyServices(): int {
        $resCompany = $this->db->query("SELECT services FROM COMPANY WHERE company_id = 1 LIMIT 1");
        $company = $resCompany->fetch_assoc();
        return (int)($company['services'] ?? 7);
    }
}