<?php
class CalendarController {
    private $service;

    public function __construct(CalendarService $service) {
        $this->service = $service;
    }

    /**
     * Hanterar inkommande förfrågningar till kalendern.
     */
    public function handleRequest(): void {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            $this->createEvent();
        } elseif ($method === 'GET') {
            $this->getEvents();
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Metoden stöds inte.'
            ]);
        }
    }

    private function getEvents(): void {
        // Hämta user_id från query-parametrar (t.ex. calendar.php?user_id=1)
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;

        $result = $this->service->getUserEvents($userId);

        http_response_code($result['status']);
        echo json_encode([
            'success' => $result['success'],
            'events' => $result['events'] ?? [],
            'error' => $result['error'] ?? null
        ]);
    }
    private function createEvent(): void {
        // 1. Använd $_POST istället för file_get_contents för form-data / multipart
        // Om data skickas som x-www-form-urlencoded eller multipart/form-data hamnar det här.
        $input = $_POST;

        // Om $_POST är tom kan det bero på att servern inte läst in multipart korrekt 
        // eller att klienten skickade rå data ändå, men för form-data är $_POST standard i PHP.
        if (empty($input)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ingen data skickades med.'
            ]);
            return;
        }

        // Hämta användar-ID (standard till 1 om det saknas)
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 1; 

        // Hantera participant_ids om det skickas som en array eller sträng från formulär
        if (isset($input['participant_ids']) && !is_array($input['participant_ids'])) {
            // Om det skickas som en kommaseparerad sträng (t.ex. "2,3"), gör om till array
            $input['participant_ids'] = array_map('trim', explode(',', $input['participant_ids']));
        }

        // 2. Anropa servicen
        $result = $this->service->createEvent($input, $userId);

        // 3. Svara klienten med rätt HTTP-statuskod och resultat
        http_response_code($result['status']);
        echo json_encode($result);
    }
}