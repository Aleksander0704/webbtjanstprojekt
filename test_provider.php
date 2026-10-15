<?php
// test_provider.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/company/providerservice.php';

header('Content-Type: application/json; charset=utf-8');

$client = new ProviderClient($mysqli);
$companyId = 1;

// Hämta en garanterat giltig session (förnyar eller verifierar automatiskt)
$sessionKey = $client->getValidSessionKey($companyId);

// Hämta aktuell status direkt från databasen för att verifiera
$stmt = $mysqli->prepare("SELECT company_id, session_key, expires_at, services FROM COMPANY WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$companyData = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    "success" => $sessionKey !== null,
    "company_id" => $companyData['company_id'] ?? $companyId,
    "session_key" => $companyData['session_key'] ?? null,
    "services" => $companyData['services'] ?? 0,
    "expires_at" => $companyData['expires_at'] ?? null
], JSON_PRETTY_PRINT);