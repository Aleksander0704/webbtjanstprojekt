<?php
require_once __DIR__ . '/AuthModel.php';

class VerifyController {
    private $authModel;

    public function __construct($db) {
        $this->authModel = new AuthModel($db);
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['valid' => false], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $sessionKey = $input['session_key'] ?? null;

        if (!$sessionKey) {
            $this->sendJsonResponse(['valid' => false], 400);
        }

        $session = $this->authModel->getSessionWithServices($sessionKey);

        if (!$session || strtotime($session['expires_at']) < time()) {
            $this->sendJsonResponse(['valid' => false], 200);
        }

        // Förläng sessionen med 30 minuter (Auto-renew enligt spec)
        $newExpiresAt = $this->authModel->extendSession($sessionKey);

        $this->sendJsonResponse([
            'valid' => true,
            'user_id' => intval($session['user_id']),
            'services' => intval($session['services']),
            'new_expires_at' => $newExpiresAt
        ], 200);
    }

    private function sendJsonResponse($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}