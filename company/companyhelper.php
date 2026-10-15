<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/companyrepository.php';
require_once __DIR__ . '/providerservice.php';

function getCompanySessionKey(int $companyId = 1): ?string {
    global $mysqli;
    $repository = new CompanyRepository($mysqli);
    $service = new ProviderService($repository);
    return $service->getValidSessionKey($companyId);
}

function companyHasService(int $companyServices, int $serviceFlag): bool {
    return ($companyServices & $serviceFlag) === $serviceFlag;
}