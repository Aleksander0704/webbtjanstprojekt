<?php
class VerifyController {
    private $verifyService;

    public function __construct(VerifyService $verifyService) {
        $this->verifyService = $verifyService;
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['valid' => false], 405);
        }

        $sessionKey = $_POST['session_key'] ?? null;
        if (!$sessionKey) {
            $input = json_decode(file_get_contents('php://input'), true);
            $sessionKey = $input['session_key'] ?? null;
        }

        if (!$sessionKey) {
            $this->sendJsonResponse(['valid' => false], 400);
        }

        $result = $this->verifyService->verifyAndExtend($sessionKey);

        if (!$result) {
            $this->sendJsonResponse(['valid' => false], 200);
        }

        $this->sendJsonResponse([
            'valid' => true,
            'user_id' => $result['user_id'],
            'services' => $result['services'],
            'new_expires_at' => $result['new_expires_at']
        ], 200);
    }

    private function sendJsonResponse($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}