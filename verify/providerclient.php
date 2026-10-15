<?php
// verify/providerclient.php

class ProviderClient {
    private $db;
    private $providerLoginUrl = "https://theprovider.ntigskovde.se/login"; 
    private $providerVerifyUrl = "https://theprovider.ntigskovde.se/verify"; 

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Huvudfunktion: Säkerställer att företaget har en giltig session,
     * förnyar/loggar in vid behov, och returnerar den giltiga session_key.
     */
    public function getValidSessionKey(int $companyId): ?string {
        // 1. Hämta nuvarande nyckel och utgångstid från databasen
        $stmt = $this->db->prepare("SELECT session_key, expires_at FROM COMPANY WHERE company_id = ?");
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
        $stmt->close();

        // 2. Om ingen nyckel finns eller om den har gått ut lokalt -> logga in på nytt
        if (!$company || empty($company['session_key']) || strtotime($company['expires_at']) <= time()) {
            $loginResult = $this->loginCompany($companyId);
            return $loginResult['session_key'] ?? null;
        }

        // 3. Kolla med leverantörens API om nyckeln fortfarande är giltig
        $response = $this->sendCurl($this->providerVerifyUrl, [
            'session_key' => $company['session_key']
        ]);

        // Om leverantören svarar att nyckeln är giltig
        if ($response && isset($response['valid']) && $response['valid'] === true) {
            // Uppdatera lokal expires_at och services med det nya datumet från leverantören
            $newExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $services = $response['services'] ?? 1;

            $updateStmt = $this->db->prepare("UPDATE COMPANY SET expires_at = ?, services = ? WHERE company_id = ?");
            $updateStmt->bind_param("sii", $newExpiresAt, $services, $companyId);
            $updateStmt->execute();
            $updateStmt->close();

            // Returnera den befintliga, giltiga nyckeln (exakt samma som innan!)
            return $company['session_key'];
        }

        // 4. Om leverantören underkände nyckeln -> gör en ny inloggning
        $newLogin = $this->loginCompany($companyId);
        return $newLogin['session_key'] ?? null;
    }

    public function loginCompany(int $companyId): ?array {
        $username = $_ENV["COMPANY_{$companyId}_USERNAME"] ?? null;
        $password = $_ENV["COMPANY_{$companyId}_PASSWORD"] ?? null;

        if (!$username || !$password) {
            error_log("ProviderClient: Saknar inloggningsuppgifter i .env för företag {$companyId}");
            return null;
        }

        $response = $this->sendCurl($this->providerLoginUrl, [
            'username' => $username,
            'password' => $password
        ]);

        if (!$response || !isset($response['session_key'])) {
            error_log("ProviderClient: Kunde inte logga in hos leverantören för företag {$companyId}");
            return null;
        }

        $sessionKey = $response['session_key'];
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $services = $response['services'] ?? 0;

        // Använd INSERT ... ON DUPLICATE KEY UPDATE så den skapar raden om den saknas
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

        return [
            'session_key' => $sessionKey,
            'expires_at' => $expiresAt,
            'services' => $services
        ];
    }

    private function sendCurl(string $url, array $data): ?array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }
}