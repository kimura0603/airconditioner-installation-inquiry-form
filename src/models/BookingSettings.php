<?php
require_once __DIR__ . '/../config/database.php';

class BookingSettings {
    private $conn;
    private $table_name = "booking_settings";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * 設定値を取得
     */
    public function getSetting($key, $defaultValue = null) {
        $query = "SELECT setting_value FROM " . $this->table_name . " WHERE setting_key = :key";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $defaultValue;
    }

    /**
     * 設定値を更新
     */
    public function updateSetting($key, $value, $description = null) {
        $query = "INSERT INTO " . $this->table_name . "
                  (setting_key, setting_value, setting_description)
                  VALUES (:key, :value, :description)
                  ON DUPLICATE KEY UPDATE
                  setting_value = VALUES(setting_value),
                  setting_description = COALESCE(VALUES(setting_description), setting_description),
                  updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->bindParam(':description', $description);

        return $stmt->execute();
    }

    /**
     * 全設定を取得
     */
    public function getAllSettings() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY setting_key";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row;
        }

        return $settings;
    }

    /**
     * 予約受付可能日数を取得
     */
    public function getBookingAdvanceDays() {
        return (int)$this->getSetting('booking_advance_days', 30);
    }

    /**
     * 予約受付可能日数を設定
     */
    public function setBookingAdvanceDays($days) {
        $days = (int)$days;
        if ($days < 1 || $days > 365) {
            throw new Exception('予約受付期間は1日から365日の間で設定してください。');
        }

        return $this->updateSetting('booking_advance_days', $days, '何日先まで予約受付可能か（日数）');
    }

    /**
     * 最低予約受付時間を取得（時間単位）
     */
    public function getMinimumAdvanceHours() {
        return (int)$this->getSetting('booking_minimum_advance_hours', 24);
    }

    /**
     * 最低予約受付時間を設定
     */
    public function setMinimumAdvanceHours($hours) {
        $hours = (int)$hours;
        if ($hours < 1 || $hours > 168) { // 1週間まで
            throw new Exception('最低予約受付時間は1時間から168時間（1週間）の間で設定してください。');
        }

        return $this->updateSetting('booking_minimum_advance_hours', $hours, '予約受付の最低何時間前までか');
    }

    /**
     * 予約受付が有効かチェック
     */
    public function isBookingEnabled() {
        return (bool)$this->getSetting('booking_enabled', 1);
    }

    /**
     * 予約受付の有効/無効を設定
     */
    public function setBookingEnabled($enabled) {
        $value = $enabled ? '1' : '0';
        return $this->updateSetting('booking_enabled', $value, '予約受付が有効かどうか（1=有効、0=無効）');
    }

    /**
     * 指定日が予約受付可能期間内かチェック
     */
    public function isDateWithinBookingPeriod($date) {
        if (!$this->isBookingEnabled()) {
            return false;
        }

        $targetTimestamp = strtotime($date);
        $todayTimestamp = strtotime('today');
        $maxTimestamp = strtotime('+' . $this->getBookingAdvanceDays() . ' days', $todayTimestamp);
        $minTimestamp = strtotime('+' . $this->getMinimumAdvanceHours() . ' hours');

        return $targetTimestamp >= $minTimestamp && $targetTimestamp <= $maxTimestamp;
    }

    /**
     * 予約受付可能な日付範囲を取得
     */
    public function getBookingDateRange() {
        if (!$this->isBookingEnabled()) {
            return [
                'start_date' => null,
                'end_date' => null,
                'enabled' => false
            ];
        }

        $minDate = date('Y-m-d', strtotime('+' . $this->getMinimumAdvanceHours() . ' hours'));
        $maxDate = date('Y-m-d', strtotime('+' . $this->getBookingAdvanceDays() . ' days'));

        return [
            'start_date' => $minDate,
            'end_date' => $maxDate,
            'enabled' => true
        ];
    }

    /**
     * 複数設定を一括更新
     */
    public function updateMultipleSettings($settings) {
        $this->conn->beginTransaction();

        try {
            foreach ($settings as $key => $value) {
                switch ($key) {
                    case 'booking_advance_days':
                        $this->setBookingAdvanceDays($value);
                        break;
                    case 'booking_minimum_advance_hours':
                        $this->setMinimumAdvanceHours($value);
                        break;
                    case 'booking_enabled':
                        $this->setBookingEnabled($value);
                        break;
                    default:
                        // その他の設定は直接更新
                        $this->updateSetting($key, $value);
                        break;
                }
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}