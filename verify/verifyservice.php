<?php
class VerifyService {
    private $repository;

    public function __construct(VerifyRepository $repository) {
        $this->repository = $repository;
    }

    public function verifyAndExtend(string $rawToken): ?array {
        if (empty($rawToken)) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        $session = $this->repository->getSessionWithServices($tokenHash);

        // Kontrollera om sessionen existerar och har gått ut
        if (!$session || strtotime($session['expires_at']) < time()) {
            return null;
        }

        // Tvinga 30 minuters förlängning
        $newExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $this->repository->extendSession($tokenHash, $newExpiresAt);

        return [
            'user_id' => (int)$session['user_id'],
            'services' => (int)$session['services'],
            'new_expires_at' => $newExpiresAt
        ];
    }
}