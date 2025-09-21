<?php
require_once __DIR__ . '/../config/database.php';

class Application {
    private $conn;
    private $table_name = "applications";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . "
                  (customer_name, customer_phone, customer_email, postal_code, address,
                   building_type, floor_number, room_type, room_size, ac_type, ac_capacity,
                   existing_ac, existing_ac_removal, electrical_work, piping_work, wall_drilling,
                   special_requests, status)
                  VALUES
                  (:customer_name, :customer_phone, :customer_email, :postal_code, :address,
                   :building_type, :floor_number, :room_type, :room_size, :ac_type, :ac_capacity,
                   :existing_ac, :existing_ac_removal, :electrical_work, :piping_work, :wall_drilling,
                   :special_requests, 'pending')";

        $stmt = $this->conn->prepare($query);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status, $confirmedDate = null, $confirmedTimeSlot = null) {
        $query = "UPDATE " . $this->table_name . "
                  SET status = :status, confirmed_date = :confirmed_date, confirmed_time_slot = :confirmed_time_slot
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':confirmed_date', $confirmedDate);
        $stmt->bindParam(':confirmed_time_slot', $confirmedTimeSlot);

        return $stmt->execute();
    }

    public function getByStatus($status = null) {
        if ($status) {
            $query = "SELECT * FROM " . $this->table_name . " WHERE status = :status ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
        } else {
            $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>