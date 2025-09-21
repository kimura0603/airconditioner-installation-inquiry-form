<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/Application.php';
require_once __DIR__ . '/../../src/models/ApplicationPreferredSlot.php';
require_once __DIR__ . '/../../src/models/ReservationSlot.php';
require_once __DIR__ . '/../../src/config/database.php';

class ReservationConfirmationSoftDeleteTest extends TestCase
{
    private $application;
    private $applicationPreferredSlot;
    private $reservationSlot;
    private $conn;
    private $testApplicationId;

    protected function setUp(): void
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->application = new Application();
        $this->applicationPreferredSlot = new ApplicationPreferredSlot();
        $this->reservationSlot = new ReservationSlot();

        // テスト用データベースをクリーンな状態にする
        $this->cleanupTestData();

        // テスト用のアプリケーションと希望日時を作成
        $this->createTestApplication();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
    }

    private function cleanupTestData()
    {
        $this->conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->conn->exec("DELETE FROM application_preferred_slots");
        $this->conn->exec("DELETE FROM applications");
        $this->conn->exec("DELETE FROM reservation_slots");
        $this->conn->exec("ALTER TABLE applications AUTO_INCREMENT = 1");
        $this->conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    private function createTestApplication()
    {
        $testData = [
            'customer_name' => 'テスト統合太郎',
            'customer_phone' => '090-1111-2222',
            'customer_email' => 'integration@test.com',
            'postal_code' => '123-4567',
            'address' => 'テスト統合住所',
            'building_type' => 'house',
            'floor_number' => null,
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'existing_ac_removal' => 'no',
            'electrical_work' => 'outlet_addition',
            'piping_work' => 'new',
            'wall_drilling' => 'yes',
            'special_requests' => ''
        ];

        $this->testApplicationId = $this->application->create($testData);

        // 3つの希望日時を作成
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-25', 'morning', 1);
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-26', 'afternoon', 2);
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-27', 'evening', 3);

        // 予約枠を初期化（各日時に容量2、現在予約数1で設定）
        $this->initializeReservationSlot('2025-09-25', 'morning', 2, 1);
        $this->initializeReservationSlot('2025-09-26', 'afternoon', 2, 1);
        $this->initializeReservationSlot('2025-09-27', 'evening', 2, 1);
    }

    private function initializeReservationSlot($date, $timeSlot, $maxCapacity, $currentBookings)
    {
        $query = "INSERT INTO reservation_slots (date, time_slot, max_capacity, current_bookings, is_available)
                  VALUES (:date, :time_slot, :max_capacity, :current_bookings, 1)
                  ON DUPLICATE KEY UPDATE
                  max_capacity = VALUES(max_capacity),
                  current_bookings = VALUES(current_bookings),
                  is_available = VALUES(is_available)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->bindParam(':max_capacity', $maxCapacity);
        $stmt->bindParam(':current_bookings', $currentBookings);
        $stmt->execute();
    }

    private function getSlotInfoDirect($date, $timeSlot)
    {
        $query = "SELECT * FROM reservation_slots WHERE date = :date AND time_slot = :time_slot";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function setSlotAvailabilityDirect($date, $timeSlot, $isAvailable)
    {
        $query = "UPDATE reservation_slots SET is_available = :is_available WHERE date = :date AND time_slot = :time_slot";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->bindParam(':is_available', $isAvailable, PDO::PARAM_BOOL);
        $stmt->execute();
    }

    private function decrementBookingDirect($date, $timeSlot)
    {
        $query = "UPDATE reservation_slots SET current_bookings = GREATEST(current_bookings - 1, 0) WHERE date = :date AND time_slot = :time_slot";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();
    }

    public function testReservationConfirmationWorkflow()
    {
        // 確定前の状態を確認
        $appBefore = $this->application->getById($this->testApplicationId);
        $this->assertEquals('pending', $appBefore['status']);
        $this->assertNull($appBefore['confirmed_date']);
        $this->assertNull($appBefore['confirmed_time_slot']);

        // 有効な希望日時が3つあることを確認
        $activeSlotsBefore = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $this->assertCount(3, $activeSlotsBefore);

        // 全ての希望日時（削除済み含む）も3つであることを確認
        $allSlotsBefore = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
        $this->assertCount(3, $allSlotsBefore);

        // 予約枠の初期状態を確認（SQLで直接確認）
        $slotBefore1 = $this->getSlotInfoDirect('2025-09-25', 'morning');
        $slotBefore2 = $this->getSlotInfoDirect('2025-09-26', 'afternoon');
        $slotBefore3 = $this->getSlotInfoDirect('2025-09-27', 'evening');

        $this->assertEquals(1, $slotBefore1['current_bookings']);
        $this->assertEquals(1, $slotBefore2['current_bookings']);
        $this->assertEquals(1, $slotBefore3['current_bookings']);
    }

    public function testConfirmReservationUpdatesStatusAndDate()
    {
        // 第2希望（2025-09-26 afternoon）で確定
        $result = $this->application->updateStatus($this->testApplicationId, 'confirmed', '2025-09-26', 'afternoon');
        $this->assertTrue($result);

        // アプリケーションの状態を確認
        $appAfter = $this->application->getById($this->testApplicationId);
        $this->assertEquals('confirmed', $appAfter['status']);
        $this->assertEquals('2025-09-26', $appAfter['confirmed_date']);
        $this->assertEquals('afternoon', $appAfter['confirmed_time_slot']);
    }

    public function testConfirmReservationSoftDeletesOtherPreferences()
    {
        // 第2希望で確定
        $this->application->updateStatus($this->testApplicationId, 'confirmed', '2025-09-26', 'afternoon');

        // 確定処理と同様の論理削除を実行
        $this->applicationPreferredSlot->softDeleteOtherPreferences(
            $this->testApplicationId,
            '2025-09-26',
            'afternoon'
        );

        // 有効な希望日時は確定したもののみになる
        $activeSlotsAfter = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $this->assertCount(1, $activeSlotsAfter);
        $this->assertEquals('2025-09-26', $activeSlotsAfter[0]['preferred_date']);
        $this->assertEquals('afternoon', $activeSlotsAfter[0]['time_slot']);

        // 全ての希望日時（削除済み含む）は3つのまま
        $allSlotsAfter = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
        $this->assertCount(3, $allSlotsAfter);

        // 削除されたスロットをチェック
        $deletedSlots = array_filter($allSlotsAfter, function($slot) {
            return !empty($slot['deleted_at']);
        });
        $this->assertCount(2, $deletedSlots);

        foreach ($deletedSlots as $deletedSlot) {
            $this->assertEquals('confirmed', $deletedSlot['deleted_reason']);
            $this->assertNotNull($deletedSlot['deleted_at']);
        }
    }

    public function testReservationSlotCountsAfterConfirmation()
    {
        // 確定前の予約枠状況を取得
        $slotsBefore = [
            'morning' => $this->getSlotInfoDirect('2025-09-25', 'morning'),
            'afternoon' => $this->getSlotInfoDirect('2025-09-26', 'afternoon'),
            'evening' => $this->getSlotInfoDirect('2025-09-27', 'evening')
        ];

        // 第2希望で確定し、他の希望日時を論理削除
        $this->application->updateStatus($this->testApplicationId, 'confirmed', '2025-09-26', 'afternoon');
        $this->applicationPreferredSlot->softDeleteOtherPreferences(
            $this->testApplicationId,
            '2025-09-26',
            'afternoon'
        );

        // 確定処理シミュレーション：全ての希望日時のカウントを減らす
        $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
        foreach ($allSlots as $slot) {
            $this->decrementBookingDirect($slot['preferred_date'], $slot['time_slot']);
        }

        // 確定した時間帯を無効化
        $this->setSlotAvailabilityDirect('2025-09-26', 'afternoon', false);

        // 確定後の予約枠状況を確認
        $slotsAfter = [
            'morning' => $this->getSlotInfoDirect('2025-09-25', 'morning'),
            'afternoon' => $this->getSlotInfoDirect('2025-09-26', 'afternoon'),
            'evening' => $this->getSlotInfoDirect('2025-09-27', 'evening')
        ];

        // 第1希望と第3希望は予約数が減少（1 → 0）
        $this->assertEquals(0, $slotsAfter['morning']['current_bookings']);
        $this->assertEquals(0, $slotsAfter['evening']['current_bookings']);

        // 第2希望（確定）も予約数は減少するが、利用不可になる
        $this->assertEquals(0, $slotsAfter['afternoon']['current_bookings']);
        $this->assertEquals(0, $slotsAfter['afternoon']['is_available']); // BooleanはDBで0/1として扱われる
    }

    public function testCompleteConfirmationWorkflow()
    {
        // confirm_reservation.phpと同じ処理を統合的にテスト
        $confirmedDate = '2025-09-26';
        $confirmedTimeSlot = 'afternoon';

        // 確定前の状態を記録
        $allSlotsBefore = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);

        try {
            $this->conn->beginTransaction();

            // 1. アプリケーションの状態を確定済みに更新
            $updateResult = $this->application->updateStatus($this->testApplicationId, 'confirmed', $confirmedDate, $confirmedTimeSlot);
            $this->assertTrue($updateResult);

            // 2. 全ての希望日時のカウントを減らす
            foreach ($allSlotsBefore as $slot) {
                $this->decrementBookingDirect($slot['preferred_date'], $slot['time_slot']);
            }

            // 3. 確定した日時以外の希望日時を論理削除
            $softDeleteResult = $this->applicationPreferredSlot->softDeleteOtherPreferences($this->testApplicationId, $confirmedDate, $confirmedTimeSlot);
            $this->assertTrue($softDeleteResult);

            // 4. 確定した日時を他の予約が入れないように無効化
            $this->setSlotAvailabilityDirect($confirmedDate, $confirmedTimeSlot, false);

            $this->conn->commit();

            // 最終状態の確認
            $finalApp = $this->application->getById($this->testApplicationId);
            $this->assertEquals('confirmed', $finalApp['status']);
            $this->assertEquals($confirmedDate, $finalApp['confirmed_date']);
            $this->assertEquals($confirmedTimeSlot, $finalApp['confirmed_time_slot']);

            $activeSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
            $this->assertCount(1, $activeSlots);
            $this->assertEquals($confirmedDate, $activeSlots[0]['preferred_date']);
            $this->assertEquals($confirmedTimeSlot, $activeSlots[0]['time_slot']);

            $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
            $this->assertCount(3, $allSlots);

            // 削除されたスロットは2つ
            $deletedCount = 0;
            foreach ($allSlots as $slot) {
                if (!empty($slot['deleted_at'])) {
                    $deletedCount++;
                    $this->assertEquals('confirmed', $slot['deleted_reason']);
                }
            }
            $this->assertEquals(2, $deletedCount);

            // 確定した時間帯は利用不可
            $confirmedSlot = $this->getSlotInfoDirect($confirmedDate, $confirmedTimeSlot);
            $this->assertEquals(0, $confirmedSlot['is_available']);

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollback();
            }
            $this->fail('Confirmation workflow failed: ' . $e->getMessage());
        }
    }

    public function testHistoryPreservation()
    {
        // 確定処理を実行
        $this->application->updateStatus($this->testApplicationId, 'confirmed', '2025-09-26', 'afternoon');
        $this->applicationPreferredSlot->softDeleteOtherPreferences($this->testApplicationId, '2025-09-26', 'afternoon');

        // 履歴が適切に保持されていることを確認
        $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);

        // 第1希望の履歴確認
        $firstPreference = array_filter($allSlots, function($slot) {
            return $slot['priority'] == 1;
        });
        $firstPreference = array_values($firstPreference)[0];

        $this->assertEquals('2025-09-25', $firstPreference['preferred_date']);
        $this->assertEquals('morning', $firstPreference['time_slot']);
        $this->assertEquals(1, $firstPreference['priority']);
        $this->assertNotNull($firstPreference['deleted_at']);
        $this->assertEquals('confirmed', $firstPreference['deleted_reason']);

        // 第2希望（確定）の確認
        $secondPreference = array_filter($allSlots, function($slot) {
            return $slot['priority'] == 2;
        });
        $secondPreference = array_values($secondPreference)[0];

        $this->assertEquals('2025-09-26', $secondPreference['preferred_date']);
        $this->assertEquals('afternoon', $secondPreference['time_slot']);
        $this->assertEquals(2, $secondPreference['priority']);
        $this->assertNull($secondPreference['deleted_at']); // 確定したので削除されていない

        // 第3希望の履歴確認
        $thirdPreference = array_filter($allSlots, function($slot) {
            return $slot['priority'] == 3;
        });
        $thirdPreference = array_values($thirdPreference)[0];

        $this->assertEquals('2025-09-27', $thirdPreference['preferred_date']);
        $this->assertEquals('evening', $thirdPreference['time_slot']);
        $this->assertEquals(3, $thirdPreference['priority']);
        $this->assertNotNull($thirdPreference['deleted_at']);
        $this->assertEquals('confirmed', $thirdPreference['deleted_reason']);
    }
}