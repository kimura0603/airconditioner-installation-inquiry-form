<?php
require_once __DIR__ . '/../config/database.php';

class ReservationSlot {
    private $conn;
    private $table_name = "reservation_slots";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAvailableSlots($startDate, $endDate = null) {
        if ($endDate === null) {
            $endDate = date('Y-m-d', strtotime($startDate . ' +30 days'));
        }

        $query = "SELECT rs.*, ts.display_name, ts.start_time, ts.end_time
                  FROM " . $this->table_name . " rs
                  JOIN time_slots ts ON rs.time_slot = ts.slot_name
                  WHERE rs.reservation_date BETWEEN :start_date AND :end_date
                  AND rs.is_available = TRUE
                  AND rs.current_bookings < rs.max_capacity
                  AND ts.is_active = TRUE
                  ORDER BY rs.reservation_date ASC,
                          CASE rs.time_slot
                              WHEN 'morning' THEN 1
                              WHEN 'afternoon' THEN 2
                              WHEN 'evening' THEN 3
                          END";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSlotIfNotExists($date, $timeSlot) {
        $query = "INSERT IGNORE INTO " . $this->table_name . "
                  (reservation_date, time_slot, max_capacity, current_bookings)
                  VALUES (:date, :time_slot, 2, 0)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);

        return $stmt->execute();
    }

    public function isSlotAvailable($date, $timeSlot) {
        $this->createSlotIfNotExists($date, $timeSlot);

        $query = "SELECT rs.*, ts.is_active
                  FROM " . $this->table_name . " rs
                  JOIN time_slots ts ON rs.time_slot = ts.slot_name
                  WHERE rs.reservation_date = :date
                  AND rs.time_slot = :time_slot
                  AND rs.is_available = TRUE
                  AND rs.current_bookings < rs.max_capacity
                  AND ts.is_active = TRUE";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function incrementBooking($date, $timeSlot) {
        $this->createSlotIfNotExists($date, $timeSlot);

        $query = "UPDATE " . $this->table_name . "
                  SET current_bookings = current_bookings + 1
                  WHERE reservation_date = :date AND time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);

        return $stmt->execute();
    }

    public function decrementBooking($date, $timeSlot) {
        $query = "UPDATE " . $this->table_name . "
                  SET current_bookings = GREATEST(current_bookings - 1, 0)
                  WHERE reservation_date = :date AND time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);

        return $stmt->execute();
    }

    public function setSlotAvailability($date, $timeSlot, $isAvailable) {
        $this->createSlotIfNotExists($date, $timeSlot);

        $query = "UPDATE " . $this->table_name . "
                  SET is_available = :is_available
                  WHERE reservation_date = :date AND time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->bindParam(':is_available', $isAvailable, PDO::PARAM_BOOL);

        return $stmt->execute();
    }

    public function getSlotInfo($date, $timeSlot) {
        $this->createSlotIfNotExists($date, $timeSlot);

        $query = "SELECT rs.*, ts.display_name, ts.start_time, ts.end_time, ts.is_active
                  FROM " . $this->table_name . " rs
                  JOIN time_slots ts ON rs.time_slot = ts.slot_name
                  WHERE rs.reservation_date = :date AND rs.time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMonthlyCalendar($year, $month) {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = "SELECT rs.*, ts.display_name
                  FROM " . $this->table_name . " rs
                  JOIN time_slots ts ON rs.time_slot = ts.slot_name
                  WHERE rs.reservation_date BETWEEN :start_date AND :end_date
                  AND ts.is_active = TRUE
                  ORDER BY rs.reservation_date ASC,
                          CASE rs.time_slot
                              WHEN 'morning' THEN 1
                              WHEN 'afternoon' THEN 2
                              WHEN 'evening' THEN 3
                          END";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>