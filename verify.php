<?php

require_once 'db.php';

// 1. Indata från klienten (t.ex. från request body eller session)
$userId = 1; // Det user_id som ska verifieras

// 2. Hämta session_key och company_id via Prepared Statement
$query = "
    SELECT c.company_id, c.session_key, c.expires_at, c.services 
    FROM USER u
    JOIN COMPANY c ON u.company_id = c.company_id
    WHERE u.user_id = ?
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$companyData = $result->fetch_assoc();
$stmt->close();

if (!$companyData) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['valid' => false, 'message' => 'Användare eller företag hittades inte.']);
    $mysqli->close();
    exit;
}

$sessionKey = $companyData['session_key'];

// 3. Skicka session_key till leverantörens API (/verify)
$ch = curl_init('http://host/verify');

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode(['session_key' => $sessionKey])
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo '❌ cURL-fel: ' . curl_error($ch);
    curl_close($ch);
    $mysqli->close();
    exit;
}

curl_close($ch);

$responseData = json_decode($response, true);

// 4. Hantera svaret från leverantören
if (isset($responseData['valid']) && $responseData['valid'] === true) {
    
    // Valfritt: Uppdatera nytt expires_at i COMPANY-tabellen om API:et returnerade nytt datum
    if (isset($responseData['new_expires_at'])) {
        $updateQuery = "UPDATE COMPANY SET expires_at = ? WHERE company_id = ?";
        $updateStmt = $mysqli->prepare($updateQuery);
        $updateStmt->bind_param("si", $responseData['new_expires_at'], $companyData['company_id']);
        $updateStmt->execute();
        $updateStmt->close();
    }

    echo "✅ Nyckeln för företag {$companyData['company_id']} är giltig! Gäller till: " . $responseData['new_expires_at'] . "\n";
} else {
    echo "❌ Företagets nyckel är ogiltig. Logga in igen.\n";
}

$mysqli->close();