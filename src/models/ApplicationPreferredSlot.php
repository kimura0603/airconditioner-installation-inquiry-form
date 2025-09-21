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

    public function getByApplicationId($applicationId, $includeDeleted = false) {
        $whereClause = "aps.application_id = :application_id";
        if (!$includeDeleted) {
            $whereClause .= " AND aps.deleted_at IS NULL";
        }

        $query = "SELECT aps.*, ts.display_name
                  FROM " . $this->table_name . " aps
                  JOIN time_slots ts ON aps.time_slot = ts.slot_name
                  WHERE " . $whereClause . "
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
                  AND aps.deleted_at IS NULL
                  AND a.status IN ('pending', 'confirmed')
                  ORDER BY aps.priority ASC, aps.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 希望日時を論理削除する
     */
    public function softDelete($applicationId, $date, $timeSlot, $reason = 'confirmed') {
        $query = "UPDATE " . $this->table_name . "
                  SET deleted_at = CURRENT_TIMESTAMP,
                      deleted_reason = :reason
                  WHERE application_id = :application_id
                  AND preferred_date = :date
                  AND time_slot = :time_slot
                  AND deleted_at IS NULL";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':application_id', $applicationId);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->bindParam(':reason', $reason);

        return $stmt->execute();
    }

    /**
     * 指定アプリケーションの他の希望日時を論理削除する（確定時に使用）
     */
    public function softDeleteOtherPreferences($applicationId, $confirmedDate, $confirmedTimeSlot) {
        $query = "UPDATE " . $this->table_name . "
                  SET deleted_at = CURRENT_TIMESTAMP,
                      deleted_reason = 'confirmed'
                  WHERE application_id = :application_id
                  AND NOT (preferred_date = :confirmed_date AND time_slot = :confirmed_time_slot)
                  AND deleted_at IS NULL";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':application_id', $applicationId);
        $stmt->bindParam(':confirmed_date', $confirmedDate);
        $stmt->bindParam(':confirmed_time_slot', $confirmedTimeSlot);

        return $stmt->execute();
    }

    /**
     * 論理削除された希望日時も含めて取得（履歴確認用）
     */
    public function getAllByApplicationId($applicationId) {
        return $this->getByApplicationId($applicationId, true);
    }
}
?>