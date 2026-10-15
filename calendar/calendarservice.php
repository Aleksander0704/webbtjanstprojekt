<?php
require_once __DIR__ . '/../company/companyhelper.php'; // För att kolla företagets tjänster/session om det behövs

class CalendarService {
    private $repository;

    public function __construct(CalendarRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Validerar och skapar ett nytt kalenderevent via repositoryt.
     */
    public function createEvent(array $requestData, int $userId, int $companyId = 1): array {
        // 1. Affärsregel: Kontrollera att företaget har tillgång till kalendertjänsten (exempel på bitmask-koll)
        // (Antag att t.ex. service-flaggan för kalender är 2, eller anpassa efter ert system)
        $sessionKey = getCompanySessionKey($companyId);
        if (!$sessionKey) {
            return [
                'success' => false,
                'error' => 'Kunde inte verifiera företagets session mot leverantören.',
                'status' => 401
            ];
        }

        // 2. Validera obligatoriska fält
        if (empty($requestData['title']) || empty($requestData['start_time']) || empty($requestData['end_time'])) {
            return [
                'success' => false,
                'error' => 'Titel, starttid och sluttid är obligatoriska.',
                'status' => 400
            ];
        }

        // 3. Affärsregel: Kontrollera att start_time är före end_time
        $startTime = strtotime($requestData['start_time']);
        $endTime = strtotime($requestData['end_time']);

        if ($startTime === false || $endTime === false || $startTime >= $endTime) {
            return [
                'success' => false,
                'error' => 'Sluttiden måste vara efter starttiden.',
                'status' => 422
            ];
        }

        // 4. Förbered data för repositoryt
        $eventData = [
            'title' => trim($requestData['title']),
            'description' => $requestData['description'] ?? null,
            'start_time' => $requestData['start_time'],
            'end_time' => $requestData['end_time'],
            'location' => $requestData['location'] ?? null,
            'is_company_wide' => filter_var($requestData['is_company_wide'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ];

        // Hämta eventuella inbjudna deltagare (array med user_id)
        $participantIds = $requestData['participant_ids'] ?? [];

        // 5. Anropa repositoryt för att köra transaktionen
        $eventId = $this->repository->createEventWithParticipants($eventData, $participantIds, $userId);

        if (!$eventId) {
            return [
                'success' => false,
                'error' => 'Ett databasfel uppstod när eventet skulle sparas.',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'event_id' => $eventId,
            'status' => 201
        ];
    }
}