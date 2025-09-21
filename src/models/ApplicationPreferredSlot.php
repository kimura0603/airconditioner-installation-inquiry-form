<?php
require_once __DIR__ . '/../config/database.php';

class ApplicationPreferredSlot {
    private $conn;
    private $table_name = "application_preferred_slots";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create($applicationId, $preferredDate, $timeSlot, $priority) {
        $query = "INSERT INTO " . $this->table_name . "
                  (application_id, preferred_date, time_slot, priority)
                  VALUES (:application_id, :preferred_date, :time_slot, :priority)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':application_id', $applicationId);
        $stmt->bindParam(':preferred_date', $preferredDate);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->bindParam(':priority', $priority);

        return $stmt->execute();
    }

    public function getByApplicationId($applicationId) {
        $query = "SELECT aps.*, ts.display_name
                  FROM " . $this->table_name . " aps
                  JOIN time_slots ts ON aps.time_slot = ts.slot_name
                  WHERE aps.application_id = :application_id
                  ORDER BY aps.priority ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':application_id', $applicationId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByApplicationId($applicationId) {
        $query = "DELETE FROM " . $this->table_name . " WHERE application_id = :application_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':application_id', $applicationId);

        return $stmt->execute();
    }

    public function getByDateAndSlot($date, $timeSlot) {
        $query = "SELECT aps.*, a.customer_name, a.customer_phone, a.status
                  FROM " . $this->table_name . " aps
                  JOIN applications a ON aps.application_id = a.id
                  WHERE aps.preferred_date = :date AND aps.time_slot = :time_slot
                  AND a.status IN ('pending', 'confirmed')
                  ORDER BY aps.priority ASC, aps.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>