<?php
class CompanyRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getCompanyData(int $companyId): ?array {
        $stmt = $this->db->prepare("SELECT session_key, expires_at FROM COMPANY WHERE company_id = ?");
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
        $stmt->close();
        return $company ?: null;
    }

    public function saveOrUpdateCompany(int $companyId, string $sessionKey, string $expiresAt, int $services): void {
        $query = "INSERT INTO COMPANY (company_id, session_key, expires_at, services) 
                  VALUES (?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE 
                  session_key = VALUES(session_key), 
                  expires_at = VALUES(expires_at), 
                  services = VALUES(services)";
                  
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("issi", $companyId, $sessionKey, $expiresAt, $services);
        $stmt->execute();
        $stmt->close();
    }

    public function updateCompanySession(int $companyId, string $expiresAt, int $services): void {
        $stmt = $this->db->prepare("UPDATE COMPANY SET expires_at = ?, services = ? WHERE company_id = ?");
        $stmt->bind_param("sii", $expiresAt, $services, $companyId);
        $stmt->execute();
        $stmt->close();
    }
}