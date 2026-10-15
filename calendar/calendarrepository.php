<?php
class CalendarRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Skapar ett event och lägger till skaparen + eventuella deltagare i en transaktion.
     */
    public function createEventWithParticipants(array $data, array $participantIds, int $userId): ?int {
        $this->db->begin_transaction();

        try {
            // 1. Skapa eventet
            $queryEvent = "
                INSERT INTO CALENDAR_EVENT 
                (title, description, start_time, end_time, location, is_company_wide, created_by_user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmtEvent = $this->db->prepare($queryEvent);
            $isCompanyWide = $data['is_company_wide'] ? 1 : 0;
            
            $stmtEvent->bind_param(
                "sssssii", 
                $data['title'], 
                $data['description'], 
                $data['start_time'], 
                $data['end_time'], 
                $data['location'], 
                $isCompanyWide, 
                $userId
            );

            if (!$stmtEvent->execute()) {
                throw new Exception("Kunde inte spara kalendereventet.");
            }

            $eventId = $stmtEvent->insert_id;
            $stmtEvent->close();

            // 2. Lägg ALLTID till skaparen själv som 'organizer' (och 'accepted')
            // Så att eventet syns i skaparens egen kalender
            $queryPart = "INSERT INTO EVENT_PARTICIPANT (event_id, user_id, status, role) VALUES (?, ?, ?, ?)";
            $stmtPart = $this->db->prepare($queryPart);

            $creatorStatus = 'accepted';
            $creatorRole = 'organizer';
            $stmtPart->bind_param("iiss", $eventId, $userId, $creatorStatus, $creatorRole);
            
            if (!$stmtPart->execute()) {
                throw new Exception("Kunde inte lägga till skaparen som deltagare.");
            }

            // 3. Lägg till övriga inbjudna deltagare (om det finns några)
            if (!empty($participantIds)) {
                $attendeeStatus = 'pending';
                $attendeeRole = 'attendee';

                foreach ($participantIds as $participantId) {
                    $partIdInt = (int)$participantId;
                    
                    // Undvik att lägga till skaparen två gånger om skaparen råkar finnas med i listan
                    if ($partIdInt === $userId) {
                        continue; 
                    }

                    $stmtPart->bind_param("iiss", $eventId, $partIdInt, $attendeeStatus, $attendeeRole);
                    
                    if (!$stmtPart->execute()) {
                        throw new Exception("Kunde inte lägga till deltagare med ID: {$partIdInt}");
                    }
                }
            }
            
            $stmtPart->close();

            // 4. Allt gick bra -> Commit
            $this->db->commit();
            return (int)$eventId;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Transaktionsfel vid skapande av event: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Hämtar alla kalenderevent som en viss användare är kopplad till.
     */
    public function getEventsForUser(int $userId): array {
        $query = "SELECT e.event_id, e.title, e.description, e.start_time, e.end_time, e.location, e.is_company_wide, 
                         p.status AS participant_status, p.role AS participant_role
                  FROM CALENDAR_EVENT e
                  JOIN EVENT_PARTICIPANT p ON e.event_id = p.event_id
                  WHERE p.user_id = ?
                  ORDER BY e.start_time ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $events = [];

        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }

        $stmt->close();
        return $events;
    }
}