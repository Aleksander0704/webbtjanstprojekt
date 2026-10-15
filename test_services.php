<?php
// test_services.php
require_once __DIR__ . '/verify/company_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Tjänsternas konstanter
define('SERVICE_CALENDAR', 1);
define('SERVICE_BLOG', 2);
define('SERVICE_WIKI', 4);

$companyId = 4;

// Hämta session (och se till att den är giltig/uppdaterad)
$sessionKey = getCompanySessionKey($companyId);

// Hämta företagets aktuella services-värde från databasen
$stmt = $mysqli->prepare("SELECT services FROM COMPANY WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$dbCompany = $stmt->get_result()->fetch_assoc();
$stmt->close();

$actualServices = $dbCompany['services'] ?? 0;

echo json_encode([
    "status" => "success",
    "company_id" => $companyId,
    "services_value" => $actualServices,
    "permissions" => [
        "har_tillgang_till_kalender" => companyHasService($actualServices, SERVICE_CALENDAR),
        "har_tillgang_till_blogg"    => companyHasService($actualServices, SERVICE_BLOG),
        "har_tillgang_till_wiki"     => companyHasService($actualServices, SERVICE_WIKI)
    ]
], JSON_PRETTY_PRINT);