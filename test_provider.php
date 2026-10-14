<?php
// test_provider.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/verify/providerclient.php';

// Sätt header så det blir ren JSON
header('Content-Type: application/json; charset=utf-8');

$client = new ProviderClient($mysqli);
$companyId = 1;

// 1. Testa att logga in och hämta ny nyckel
$loginResult = $client->loginCompany($companyId);

if (!$loginResult) {
    echo json_encode([
        "status" => "error",
        "message" => "Inloggning misslyckades för företag {$companyId}. Kolla .env och leverantörens URL."
    ], JSON_PRETTY_PRINT);
    exit;
}

// 2. Testa att verifiera nyckeln
$isValid = $client->verifyCompanySession($companyId);

// Skriv ut ett snyggt resultat
echo json_encode([
    "status" => "success",
    "message" => "Testet genomfört!",
    "login_data" => $loginResult,
    "verification_passed" => $isValid
], JSON_PRETTY_PRINT);