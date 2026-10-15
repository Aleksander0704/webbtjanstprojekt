<?php
// verify/company_helper.php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/providerclient.php';

// 1. Hämta giltig session-nyckel
function getCompanySessionKey(int $companyId = 1): ?string {
    global $mysqli;
    $client = new ProviderClient($mysqli);
    return $client->getValidSessionKey($companyId);
}

// 2. Kontrollera om företaget har tillgång till en specifik tjänst (bitmask)
function companyHasService(int $companyServices, int $serviceFlag): bool {
    return ($companyServices & $serviceFlag) === $serviceFlag;
}