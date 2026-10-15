<?php
class LoginController {
    private $loginService;

    public function __construct(LoginService $loginService) {
        $this->loginService = $loginService;
    }

    public function handleRequest() {
        header('Content-Type: application/json; charset=utf-8');

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "Användarnamn och lösenord krävs"]);
            return;
        }

        $sessionData = $this->loginService->authenticate($username, $password);

        if (!$sessionData) {
            http_response_code(401);
            echo json_encode(["error" => "Felaktigt användarnamn eller lösenord"]);
            return;
        }

        http_response_code(200);
        echo json_encode(array_merge(["success" => true], $sessionData));
    }
}