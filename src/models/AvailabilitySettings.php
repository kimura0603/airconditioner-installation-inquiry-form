<?php
require_once __DIR__ . '/../config/database.php';

class AvailabilitySettings {
    private $conn;
    private $table_name = "availability_settings";
    private $override_table = "date_availability_overrides";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * 曜日・時間帯の基本設定を取得
     */
    public function getWeeklySettings() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY
                 CASE day_of_week
                     WHEN 'monday' THEN 1
                     WHEN 'tuesday' THEN 2
                     WHEN 'wednesday' THEN 3
                     WHEN 'thursday' THEN 4
                     WHEN 'friday' THEN 5
                     WHEN 'saturday' THEN 6
                     WHEN 'sunday' THEN 7
                 END,
                 CASE time_slot
                     WHEN 'morning' THEN 1
                     WHEN 'afternoon' THEN 2
                     WHEN 'evening' THEN 3
                 END";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 曜日・時間帯の設定を更新
     */
    public function updateWeeklySetting($dayOfWeek, $timeSlot, $isAvailable) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_available = :is_available, updated_at = CURRENT_TIMESTAMP
                  WHERE day_of_week = :day_of_week AND time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':day_of_week', $dayOfWeek);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->bindParam(':is_available', $isAvailable, PDO::PARAM_BOOL);

        return $stmt->execute();
    }

    /**
     * 特定の日付に対する可用性を確認
     * 1. 手動オーバーライドがあるかチェック
     * 2. なければ曜日の基本設定をチェック
     * 3. 確定済み予約があるかチェック
     */
    public function isDateTimeAvailable($date, $timeSlot) {
        // 1. 手動オーバーライドをチェック
        $override = $this->getDateOverride($date, $timeSlot);
        if ($override) {
            return $override['is_available'];
        }

        // 2. 曜日の基本設定をチェック
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        $weeklyAvailable = $this->isWeeklySlotAvailable($dayOfWeek, $timeSlot);
        if (!$weeklyAvailable) {
            return false;
        }

        // 3. 確定済み予約があるかチェック
        return !$this->hasConfirmedReservation($date, $timeSlot);
    }

    /**
     * 曜日の基本設定での可用性チェック
     */
    public function isWeeklySlotAvailable($dayOfWeek, $timeSlot) {
        $query = "SELECT is_available FROM " . $this->table_name . "
                  WHERE day_of_week = :day_of_week AND time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':day_of_week', $dayOfWeek);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (bool)$result['is_available'] : false;
    }

    /**
     * 特定日のオーバーライド設定を取得
     */
    public function getDateOverride($date, $timeSlot) {
        $query = "SELECT * FROM " . $this->override_table . "
                  WHERE date = :date AND time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 特定日の可用性をオーバーライド
     */
    public function setDateOverride($date, $timeSlot, $isAvailable, $reason = '', $createdBy = 'admin') {
        // 確定済み予約がある場合は変更不可
        if ($this->hasConfirmedReservation($date, $timeSlot)) {
            throw new Exception('確定済み予約があるため、この日時の設定は変更できません。');
        }

        $query = "INSERT INTO " . $this->override_table . "
                  (date, time_slot, is_available, reason, created_by)
                  VALUES (:date, :time_slot, :is_available, :reason, :created_by)
                  ON DUPLICATE KEY UPDATE
                  is_available = VALUES(is_available),
                  reason = VALUES(reason),
                  created_by = VALUES(created_by),
                  updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->bindParam(':is_available', $isAvailable, PDO::PARAM_BOOL);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':created_by', $createdBy);

        return $stmt->execute();
    }

    /**
     * 特定日のオーバーライドを削除（基本設定に戻す）
     */
    public function removeDateOverride($date, $timeSlot) {
        // 確定済み予約がある場合は変更不可
        if ($this->hasConfirmedReservation($date, $timeSlot)) {
            throw new Exception('確定済み予約があるため、この日時の設定は変更できません。');
        }

        $query = "DELETE FROM " . $this->override_table . "
                  WHERE date = :date AND time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);

        return $stmt->execute();
    }

    /**
     * 確定済み予約があるかチェック
     */
    private function hasConfirmedReservation($date, $timeSlot) {
        $query = "SELECT COUNT(*) as count FROM applications
                  WHERE status = 'confirmed'
                  AND confirmed_date = :date
                  AND confirmed_time_slot = :time_slot";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * 月間のオーバーライド設定を取得
     */
    public function getMonthlyOverrides($year, $month) {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = "SELECT * FROM " . $this->override_table . "
                  WHERE date BETWEEN :start_date AND :end_date
                  ORDER BY date ASC,
                  CASE time_slot
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

    /**
     * 日本語の曜日名を英語に変換
     */
    public function getDayOfWeekEn($date) {
        return strtolower(date('l', strtotime($date)));
    }

    /**
     * 英語の曜日名を日本語に変換
     */
    public function getDayOfWeekJp($dayOfWeek) {
        $map = [
            'monday' => '月',
            'tuesday' => '火',
            'wednesday' => '水',
            'thursday' => '木',
            'friday' => '金',
            'saturday' => '土',
            'sunday' => '日'
        ];
        return $map[$dayOfWeek] ?? $dayOfWeek;
    }
}