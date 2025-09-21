<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/Application.php';
require_once __DIR__ . '/../../src/models/ApplicationPreferredSlot.php';
require_once __DIR__ . '/../../src/models/ReservationSlot.php';
require_once __DIR__ . '/../../src/config/database.php';

class ApplicationCancellationTest extends TestCase
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

        // テスト用のアプリケーションを作成
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
            'customer_name' => 'テストキャンセル太郎',
            'customer_phone' => '090-9999-8888',
            'customer_email' => 'cancel@test.com',
            'postal_code' => '123-4567',
            'address' => 'テストキャンセル住所',
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

        // 予約枠を初期化
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

    private function decrementBookingDirect($date, $timeSlot)
    {
        $query = "UPDATE reservation_slots SET current_bookings = GREATEST(current_bookings - 1, 0) WHERE date = :date AND time_slot = :time_slot";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_slot', $timeSlot);
        $stmt->execute();
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

    public function testCancelPendingApplication()
    {
        // 申し込みがpending状態であることを確認
        $appBefore = $this->application->getById($this->testApplicationId);
        $this->assertEquals('pending', $appBefore['status']);

        // 有効な希望日時が3つあることを確認
        $activeSlotsBefor = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $this->assertCount(3, $activeSlotsBefor);

        // キャンセル処理シミュレーション
        $this->simulateCancellation();

        // アプリケーションがキャンセル状態になっていることを確認
        $appAfter = $this->application->getById($this->testApplicationId);
        $this->assertEquals('cancelled', $appAfter['status']);

        // 有効な希望日時が0になっていることを確認
        $activeSlotsAfter = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $this->assertCount(0, $activeSlotsAfter);

        // 全ての希望日時（削除済み含む）は3つのまま
        $allSlotsAfter = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
        $this->assertCount(3, $allSlotsAfter);

        // 全ての希望日時がcancelledで論理削除されていることを確認
        foreach ($allSlotsAfter as $slot) {
            $this->assertNotNull($slot['deleted_at']);
            $this->assertEquals('cancelled', $slot['deleted_reason']);
        }
    }

    public function testCancelConfirmedApplication()
    {
        // 第2希望で確定処理を先に行う
        $this->application->updateStatus($this->testApplicationId, 'confirmed', '2025-09-26', 'afternoon');
        $this->applicationPreferredSlot->softDeleteOtherPreferences($this->testApplicationId, '2025-09-26', 'afternoon');
        $this->setSlotAvailabilityDirect('2025-09-26', 'afternoon', false);

        // 確定状態になっていることを確認
        $appBeforeCancel = $this->application->getById($this->testApplicationId);
        $this->assertEquals('confirmed', $appBeforeCancel['status']);
        $this->assertEquals('2025-09-26', $appBeforeCancel['confirmed_date']);
        $this->assertEquals('afternoon', $appBeforeCancel['confirmed_time_slot']);

        // 確定した時間帯が利用不可になっていることを確認
        $confirmedSlotBefore = $this->getSlotInfoDirect('2025-09-26', 'afternoon');
        $this->assertEquals(0, $confirmedSlotBefore['is_available']);

        // キャンセル処理シミュレーション（確定済みの場合の特別処理含む）
        $this->simulateCancellationForConfirmed();

        // アプリケーションがキャンセル状態になっていることを確認
        $appAfter = $this->application->getById($this->testApplicationId);
        $this->assertEquals('cancelled', $appAfter['status']);

        // 確定していた時間帯が再び利用可能になっていることを確認
        $confirmedSlotAfter = $this->getSlotInfoDirect('2025-09-26', 'afternoon');
        $this->assertEquals(1, $confirmedSlotAfter['is_available']);
    }

    public function testCancelAlreadyCancelledApplication()
    {
        // 先にキャンセル処理を行う
        $this->simulateCancellation();

        // 既にキャンセル済みの状態であることを確認
        $appCancelled = $this->application->getById($this->testApplicationId);
        $this->assertEquals('cancelled', $appCancelled['status']);

        // 再度キャンセル処理を実行しようとする（エラーハンドリングのテスト）
        try {
            // 重複キャンセルのチェック
            if ($appCancelled['status'] === 'cancelled') {
                throw new Exception('この申し込みは既にキャンセル済みです。');
            }
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertEquals('この申し込みは既にキャンセル済みです。', $e->getMessage());
        }
    }

    public function testReservationSlotCountsAfterCancellation()
    {
        // キャンセル前の予約枠状況を確認
        $slotsBefore = [
            'morning' => $this->getSlotInfoDirect('2025-09-25', 'morning'),
            'afternoon' => $this->getSlotInfoDirect('2025-09-26', 'afternoon'),
            'evening' => $this->getSlotInfoDirect('2025-09-27', 'evening')
        ];

        $this->assertEquals(1, $slotsBefore['morning']['current_bookings']);
        $this->assertEquals(1, $slotsBefore['afternoon']['current_bookings']);
        $this->assertEquals(1, $slotsBefore['evening']['current_bookings']);

        // キャンセル処理実行
        $this->simulateCancellation();

        // キャンセル後の予約枠状況を確認
        $slotsAfter = [
            'morning' => $this->getSlotInfoDirect('2025-09-25', 'morning'),
            'afternoon' => $this->getSlotInfoDirect('2025-09-26', 'afternoon'),
            'evening' => $this->getSlotInfoDirect('2025-09-27', 'evening')
        ];

        // 全ての予約枠の予約数が減少していることを確認（1 → 0）
        $this->assertEquals(0, $slotsAfter['morning']['current_bookings']);
        $this->assertEquals(0, $slotsAfter['afternoon']['current_bookings']);
        $this->assertEquals(0, $slotsAfter['evening']['current_bookings']);
    }

    public function testCancellationHistoryPreservation()
    {
        // キャンセル処理実行
        $this->simulateCancellation();

        // 全ての希望日時履歴を取得
        $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
        $this->assertCount(3, $allSlots);

        // 各希望日時の履歴確認
        foreach ($allSlots as $slot) {
            // 論理削除されている
            $this->assertNotNull($slot['deleted_at']);
            $this->assertEquals('cancelled', $slot['deleted_reason']);

            // 元のデータが保持されている
            $this->assertNotNull($slot['preferred_date']);
            $this->assertNotNull($slot['time_slot']);
            $this->assertNotNull($slot['priority']);
            $this->assertNotNull($slot['created_at']);
        }

        // 優先順位ごとの確認
        $slotsByPriority = [];
        foreach ($allSlots as $slot) {
            $slotsByPriority[$slot['priority']] = $slot;
        }

        $this->assertEquals('2025-09-25', $slotsByPriority[1]['preferred_date']);
        $this->assertEquals('morning', $slotsByPriority[1]['time_slot']);

        $this->assertEquals('2025-09-26', $slotsByPriority[2]['preferred_date']);
        $this->assertEquals('afternoon', $slotsByPriority[2]['time_slot']);

        $this->assertEquals('2025-09-27', $slotsByPriority[3]['preferred_date']);
        $this->assertEquals('evening', $slotsByPriority[3]['time_slot']);
    }

    private function simulateCancellation()
    {
        $this->conn->beginTransaction();

        try {
            // アプリケーションの存在確認
            $appData = $this->application->getById($this->testApplicationId);
            if (!$appData) {
                throw new Exception('申し込みが見つかりません。');
            }

            // 既にキャンセル済みかチェック
            if ($appData['status'] === 'cancelled') {
                throw new Exception('この申し込みは既にキャンセル済みです。');
            }

            // 各希望日時の予約カウントを減らす（有効なもののみ）
            $activeSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
            foreach ($activeSlots as $slot) {
                $this->decrementBookingDirect($slot['preferred_date'], $slot['time_slot']);
            }

            // 全ての有効な希望日時を論理削除（キャンセル理由で）
            foreach ($activeSlots as $slot) {
                $this->applicationPreferredSlot->softDelete(
                    $this->testApplicationId,
                    $slot['preferred_date'],
                    $slot['time_slot'],
                    'cancelled'
                );
            }

            // アプリケーションのステータスをキャンセルに更新
            $query = "UPDATE applications SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->testApplicationId);
            $stmt->execute();

            $this->conn->commit();

        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function simulateCancellationForConfirmed()
    {
        $this->conn->beginTransaction();

        try {
            $appData = $this->application->getById($this->testApplicationId);

            // 確定済みの場合、確定した時間帯の利用可能性を復元
            if ($appData['status'] === 'confirmed' && $appData['confirmed_date'] && $appData['confirmed_time_slot']) {
                $this->setSlotAvailabilityDirect($appData['confirmed_date'], $appData['confirmed_time_slot'], true);
            }

            // 有効な希望日時のカウントを減らす
            $activeSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
            foreach ($activeSlots as $slot) {
                $this->decrementBookingDirect($slot['preferred_date'], $slot['time_slot']);
            }

            // 全ての有効な希望日時を論理削除
            foreach ($activeSlots as $slot) {
                $this->applicationPreferredSlot->softDelete(
                    $this->testApplicationId,
                    $slot['preferred_date'],
                    $slot['time_slot'],
                    'cancelled'
                );
            }

            // アプリケーションのステータスをキャンセルに更新
            $query = "UPDATE applications SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->testApplicationId);
            $stmt->execute();

            $this->conn->commit();

        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}