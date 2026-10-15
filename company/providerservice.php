<?php
class ProviderService {
    private $repository;
    private $providerLoginUrl = "https://theprovider.ntigskovde.se/login"; 
    private $providerVerifyUrl = "https://theprovider.ntigskovde.se/verify"; 

    public function __construct(CompanyRepository $repository) {
        $this->repository = $repository;
    }

    public function getValidSessionKey(int $companyId): ?string {
        $company = $this->repository->getCompanyData($companyId);

        // Om ingen nyckel finns eller om den har gått ut lokalt -> logga in på nytt
        if (!$company || empty($company['session_key']) || strtotime($company['expires_at']) <= time()) {
            $loginResult = $this->loginCompany($companyId);
            return $loginResult['session_key'] ?? null;
        }

        // Kolla med leverantörens API
        $response = $this->sendCurl($this->providerVerifyUrl, [
            'session_key' => $company['session_key']
        ]);

        if ($response && isset($response['valid']) && $response['valid'] === true) {
            $newExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $services = $response['services'] ?? 1;

            $this->repository->updateCompanySession($companyId, $newExpiresAt, $services);
            return $company['session_key'];
        }

        // Om leverantören underkände -> logga in på nytt
        $newLogin = $this->loginCompany($companyId);
        return $newLogin['session_key'] ?? null;
    }

    private function loginCompany(int $companyId): ?array {
        $username = $_ENV["COMPANY_{$companyId}_USERNAME"] ?? null;
        $password = $_ENV["COMPANY_{$companyId}_PASSWORD"] ?? null;

        if (!$username || !$password) {
            return null;
        }

        $response = $this->sendCurl($this->providerLoginUrl, [
            'username' => $username,
            'password' => $password
        ]);

        if (!$response || !isset($response['session_key'])) {
            return null;
        }

        $sessionKey = $response['session_key'];
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes')); // Tvinga 30 min
        $services = $response['services'] ?? 0;

        $this->repository->saveOrUpdateCompany($companyId, $sessionKey, $expiresAt, $services);

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
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }
}