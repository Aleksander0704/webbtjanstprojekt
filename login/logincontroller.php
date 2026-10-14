<?php
// LoginController.php

class LoginController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    public function handleRequest() {
        header('Content-Type: application/json; charset=utf-8');

        // Hämta data från form-data ($_POST)
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "Användarnamn och lösenord krävs"]);
            return;
        }

        // Försök logga in via modellen
        $sessionData = $this->model->authenticate($username, $password);

        if (!$sessionData) {
            http_response_code(401);
            echo json_encode(["error" => "Felaktigt användarnamn eller lösenord"]);
            return;
        }

        // Skicka tillbaka lyckat svar med session_key
        http_response_code(200);
        echo json_encode(array_merge(["success" => true], $sessionData));
    }
}