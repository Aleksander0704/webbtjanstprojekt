<?php
class LoginService {
    private $repository;

    public function __construct(LoginRepository $repository) {
        $this->repository = $repository;
    }

    public function authenticate(string $username, string $password): ?array {
        // 1. Hämta användaren via repository
        $user = $this->repository->getUserByUsername($username);

        // 2. Kontrollera lösenord
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $userId = $user['user_id'];

        // 3. Rensa gamla tokens
        $this->repository->deleteTokensForUser($userId);

        // 4. Generera ny token och 30-minutersgräns
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // 5. Spara nya token i databasen
        $this->repository->saveToken($userId, $tokenHash, $expiresAt);

        // 6. Hämta tjänster
        $services = $this->repository->getCompanyServices();

        return [
            'user_id' => (int)$userId,
            'session_key' => $rawToken,
            'services' => $services,
            'expires_at' => $expiresAt
        ];
    }
}