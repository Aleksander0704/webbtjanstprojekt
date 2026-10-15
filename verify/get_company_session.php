<?php
// verify/get_company_session.php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/providerclient.php';

function getCompanySessionKey(int $companyId = 1): ?string {
    global $mysqli; // Eller skicka med $db om du föredrar det
    $client = new ProviderClient($mysqli);
    return $client->getValidSessionKey($companyId);
}