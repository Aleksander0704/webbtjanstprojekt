<?php
require_once __DIR__ . '/LoginModel.php';

class LoginController {
    private $loginModel;

    public function __construct($db) {
        $this->loginModel = new LoginModel($db);
    }

    public function handleRequest() {
        // Endast POST-förfrågan tillåts
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['error' => 'Metoden stöds ej'], 405);
        }

        // Hämta JSON-data från request body
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? null;
        $password = $input['password'] ?? null;

        if (!$username || !$password) {
            $this->sendJsonResponse(['error' => 'Användarnamn och lösenord krävs'], 400);
        }

        // Hämta användare från databasen
        $user = $this->loginModel->getUserByUsername($username);

        // Kontrollera att användaren finns och att lösenordet stämmer
        // (Obs: Om du lagrar lösenorden i klartext under testning kan du byta till: $password !== $user['password_hash'])
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->sendJsonResponse(['error' => 'Felaktigt användarnamn eller lösenord'], 401);
        }

        // Generera en unik, säker sessionsnyckel (64 tecken hex)
        $sessionKey = bin2hex(random_bytes(32));
        
        // Sätt utgångstid till 30 minuter framåt i tiden
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // Spara sessionen i databasen (inklusive company_id för att binda rätt tjänstepaket)
        $saved = $this->loginModel->createSession(
            intval($user['user_id']), 
            intval($user['company_id']), 
            $sessionKey, 
            $expiresAt
        );

        if (!$saved) {
            $this->sendJsonResponse(['error' => 'Kunde inte skapa session i databasen'], 500);
        }

        // Skicka svar exakt enligt API-dokumentationen
        $this->sendJsonResponse([
            'success' => true,
            'user_id' => intval($user['user_id']),
            'session_key' => $sessionKey,
            'services' => intval($user['services']),
            'expires_at' => $expiresAt
        ], 200);
    }

    private function sendJsonResponse($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}