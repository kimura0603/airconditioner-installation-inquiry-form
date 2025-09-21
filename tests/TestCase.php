<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/models/Application.php';
require_once __DIR__ . '/../src/models/ApplicationPreferredSlot.php';
require_once __DIR__ . '/../src/models/ReservationSlot.php';

abstract class TestCase extends BaseTestCase
{
    protected $testDatabase;
    protected $testConnection;
    protected $conn; // 新しいテストで使用するエイリアス

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestDatabase();
        parent::tearDown();
    }

    protected function setupTestDatabase(): void
    {
        // テスト専用データベース接続
        $this->testDatabase = new \Database();
        $this->testConnection = $this->testDatabase->getConnection();
        $this->conn = $this->testConnection; // エイリアスを設定

        // テスト用テーブルの初期化
        $this->initializeTestTables();
    }

    protected function cleanupTestDatabase(): void
    {
        if ($this->testConnection) {
            // テストデータのクリーンアップ
            $this->testConnection->exec("DELETE FROM application_preferred_slots WHERE 1=1");
            $this->testConnection->exec("DELETE FROM applications WHERE 1=1");
            $this->testConnection->exec("DELETE FROM reservation_slots WHERE 1=1");
            $this->testConnection->exec("DELETE FROM date_availability_overrides WHERE 1=1");
            $this->testConnection->exec("ALTER TABLE applications AUTO_INCREMENT = 1");
            $this->testConnection->exec("ALTER TABLE application_preferred_slots AUTO_INCREMENT = 1");
        }
    }

    protected function initializeTestTables(): void
    {
        // 既存のテストデータをクリーンアップ
        $this->cleanupTestDatabase();

        // 基本的な時間枠データの挿入
        $timeSlots = [
            ['morning', '午前（9:00-12:00）', '09:00:00', '12:00:00'],
            ['afternoon', '午後（12:00-15:00）', '12:00:00', '15:00:00'],
            ['evening', '夕方（15:00-18:00）', '15:00:00', '18:00:00']
        ];

        foreach ($timeSlots as $slot) {
            $stmt = $this->testConnection->prepare("
                INSERT IGNORE INTO time_slots (slot_name, display_name, start_time, end_time, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute($slot);
        }
    }

    /**
     * テスト用のアプリケーションを作成
     */
    protected function createTestApplication($data = []): int
    {
        $defaultData = [
            'customer_name' => 'テスト太郎',
            'customer_phone' => '090-1234-5678',
            'customer_email' => 'test@example.com',
            'postal_code' => '123-4567',
            'address' => 'テスト住所',
            'building_type' => 'house',
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'existing_ac_removal' => 'no',
            'electrical_work' => 'none',
            'piping_work' => 'new',
            'wall_drilling' => 'no',
            'special_requests' => ''
        ];

        $testData = array_merge($defaultData, $data);
        $application = new \Application();
        return $application->create($testData);
    }

    /**
     * テスト用の希望日時を作成
     */
    protected function createTestPreferredSlots($applicationId, $slots): void
    {
        $preferredSlot = new \ApplicationPreferredSlot();
        foreach ($slots as $priority => $slot) {
            $preferredSlot->create($applicationId, $slot['date'], $slot['time_slot'], $priority + 1);
        }
    }

    /**
     * 予約枠の状況を検証するヘルパー
     */
    protected function assertReservationSlotState($date, $timeSlot, $expectedBookings, $expectedAvailable = true): void
    {
        $reservationSlot = new \ReservationSlot();
        $slotInfo = $reservationSlot->getSlotInfo($date, $timeSlot);

        $this->assertNotNull($slotInfo, "Slot info should exist for {$date} {$timeSlot}");
        $this->assertEquals($expectedBookings, $slotInfo['current_bookings'],
            "Current bookings mismatch for {$date} {$timeSlot}");
        $this->assertEquals($expectedAvailable ? 1 : 0, $slotInfo['is_available'],
            "Availability mismatch for {$date} {$timeSlot}");
    }

    /**
     * アプリケーションの状態を検証するヘルパー
     */
    protected function assertApplicationState($applicationId, $expectedStatus, $expectedConfirmedDate = null, $expectedTimeSlot = null): void
    {
        $application = new \Application();
        $appData = $application->getById($applicationId);

        $this->assertNotNull($appData, "Application {$applicationId} should exist");
        $this->assertEquals($expectedStatus, $appData['status'],
            "Application status mismatch for {$applicationId}");

        if ($expectedConfirmedDate) {
            $this->assertEquals($expectedConfirmedDate, $appData['confirmed_date'],
                "Confirmed date mismatch for {$applicationId}");
        }

        if ($expectedTimeSlot) {
            $this->assertEquals($expectedTimeSlot, $appData['confirmed_time_slot'],
                "Confirmed time slot mismatch for {$applicationId}");
        }
    }

    /**
     * 希望日時レコードの存在を検証するヘルパー
     */
    protected function assertPreferredSlotsDeleted($applicationId): void
    {
        $preferredSlot = new \ApplicationPreferredSlot();
        $slots = $preferredSlot->getByApplicationId($applicationId);

        $this->assertEmpty($slots, "Preferred slots should be deleted for application {$applicationId}");
    }
}