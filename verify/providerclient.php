<?php
// ProviderClient.php

class ProviderClient {
    private $db;
    // Byt ut dessa mot leverantörens faktiska URL:er
    private $providerLoginUrl = "http://theprovider.ntigskovde.se/login"; 
    private $providerVerifyUrl = "http://theprovider.ntigskovde.se/verify"; 

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * 1. Loggar in hos leverantören med uppgifter från .env för ett visst företag
     * och sparar ner den nya session-key, expires_at och services i COMPANY-tabellen.
     */
    public function loginCompany(int $companyId): ?array {
        $username = $_ENV["COMPANY_{$companyId}_USERNAME"] ?? null;
        $password = $_ENV["COMPANY_{$companyId}_PASSWORD"] ?? null;

        if (!$username || !$password) {
            error_log("ProviderClient: Saknar inloggningsuppgifter i .env för företag {$companyId}");
            return null;
        }

        // Skicka cURL-anrop till leverantörens inloggnings-API
        $response = $this->sendCurl($this->providerLoginUrl, [
            'username' => $username,
            'password' => $password
        ]);

        if (!$response || !isset($response['session_key'])) {
            error_log("ProviderClient: Kunde inte logga in hos leverantören för företag {$companyId}");
            return null;
        }

        $sessionKey = $response['session_key'];
        $expiresAt = $response['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $services = $response['services'] ?? 0;

        // Uppdatera COMPANY-tabellen med leverantörens data
        $query = "UPDATE COMPANY SET provider_session_key = ?, expires_at = ?, services = ? WHERE company_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ssii", $sessionKey, $expiresAt, $services, $companyId);
        $stmt->execute();
        $stmt->close();

        return [
            'session_key' => $sessionKey,
            'expires_at' => $expiresAt,
            'services' => $services
        ];
    }

    /**
     * 2. Verifierar om företagets nuvarande session-key fortfarande är giltig hos leverantören.
     * Om den har gått ut eller inte finns kan den automatiskt försöka logga in på nytt.
     */
    public function verifyCompanySession(int $companyId): bool {
        // Hämta företagets nuvarande nyckel från databasen
        $stmt = $this->db->prepare("SELECT provider_session_key, expires_at FROM COMPANY WHERE company_id = ?");
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
        $stmt->close();

        if (!$company || empty($company['provider_session_key'])) {
            // Ingen nyckel finns, gör en inloggning direkt
            return $this->loginCompany($companyId) !== null;
        }

        // Kolla om tiden har gått ut lokalt först
        if (strtotime($company['expires_at']) <= time()) {
            // Gått ut, logga in på nytt
            return $this->loginCompany($companyId) !== null;
        }

        // Skicka verifieringsförfrågan till leverantörens API
        $response = $this->sendCurl($this->providerVerifyUrl, [
            'session_key' => $company['provider_session_key']
        ]);

        // Om leverantören svarar att nyckeln är giltig (ex. 'valid' => true)
        if ($response && isset($response['valid']) && $response['valid'] === true) {
            return true;
        }

        // Om nyckeln inte längre godkänns av leverantören, logga in på nytt
        $newLogin = $this->loginCompany($companyId);
        return $newLogin !== null;
    }

    /**
     * Hjälpfunktion för att köra cURL-anrop mot leverantören
     */
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