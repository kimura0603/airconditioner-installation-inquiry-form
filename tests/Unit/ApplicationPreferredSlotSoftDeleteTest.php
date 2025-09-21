<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/ApplicationPreferredSlot.php';
require_once __DIR__ . '/../../src/models/Application.php';
require_once __DIR__ . '/../../src/config/database.php';

class ApplicationPreferredSlotSoftDeleteTest extends TestCase
{
    private $applicationPreferredSlot;
    private $application;
    private $conn;
    private $testApplicationId;

    protected function setUp(): void
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->applicationPreferredSlot = new ApplicationPreferredSlot();
        $this->application = new Application();

        // テスト用のアプリケーションを作成
        $testData = [
            'customer_name' => 'テスト太郎',
            'customer_phone' => '090-1234-5678',
            'customer_email' => 'test@example.com',
            'postal_code' => '123-4567',
            'address' => 'テスト住所',
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

        // テスト用の希望日時を3つ作成
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-25', 'morning', 1);
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-26', 'afternoon', 2);
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-27', 'evening', 3);
    }

    protected function tearDown(): void
    {
        // テストデータをクリーンアップ
        if ($this->testApplicationId) {
            $this->conn->exec("DELETE FROM application_preferred_slots WHERE application_id = " . $this->testApplicationId);
            $this->conn->exec("DELETE FROM applications WHERE id = " . $this->testApplicationId);
        }
    }

    public function testGetByApplicationIdExcludesDeletedByDefault()
    {
        // 削除前は3つ取得できる
        $slots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $this->assertCount(3, $slots);

        // 1つを論理削除
        $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-25', 'morning');

        // 削除後は2つになる
        $slots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $this->assertCount(2, $slots);

        // 削除されたもの以外が残っていることを確認
        $dates = array_column($slots, 'preferred_date');
        $this->assertContains('2025-09-26', $dates);
        $this->assertContains('2025-09-27', $dates);
        $this->assertNotContains('2025-09-25', $dates);
    }

    public function testGetByApplicationIdIncludesDeletedWhenRequested()
    {
        // 1つを論理削除
        $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-25', 'morning', 'confirmed');

        // includeDeleted=trueで削除済みも含めて取得
        $allSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId, true);
        $this->assertCount(3, $allSlots);

        // 削除されたスロットを確認
        $deletedSlot = null;
        foreach ($allSlots as $slot) {
            if ($slot['preferred_date'] === '2025-09-25') {
                $deletedSlot = $slot;
                break;
            }
        }

        $this->assertNotNull($deletedSlot);
        $this->assertNotNull($deletedSlot['deleted_at']);
        $this->assertEquals('confirmed', $deletedSlot['deleted_reason']);
    }

    public function testGetAllByApplicationIdReturnsIncludingDeleted()
    {
        // 1つを論理削除
        $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-25', 'morning');

        // getAllByApplicationIdは削除済みも含む
        $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
        $this->assertCount(3, $allSlots);
    }

    public function testSoftDeleteMarksRecordAsDeleted()
    {
        // 論理削除実行
        $result = $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-25', 'morning', 'manual');
        $this->assertTrue($result);

        // 削除されたレコードを直接確認
        $query = "SELECT * FROM application_preferred_slots
                  WHERE application_id = :app_id
                  AND preferred_date = :date
                  AND time_slot = :slot";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':app_id', $this->testApplicationId);
        $stmt->bindValue(':date', '2025-09-25');
        $stmt->bindValue(':slot', 'morning');
        $stmt->execute();

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($record);
        $this->assertNotNull($record['deleted_at']);
        $this->assertEquals('manual', $record['deleted_reason']);
    }

    public function testSoftDeleteDoesNotAffectAlreadyDeletedRecords()
    {
        // 最初の削除
        $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-25', 'morning', 'confirmed');

        // 同じレコードを再度削除しようとする
        $result = $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-25', 'morning', 'manual');

        // 更新対象がないので0行更新（falseではない）
        $this->assertTrue($result);

        // 削除理由は最初のままであることを確認
        $query = "SELECT deleted_reason FROM application_preferred_slots
                  WHERE application_id = :app_id
                  AND preferred_date = :date
                  AND time_slot = :slot";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':app_id', $this->testApplicationId);
        $stmt->bindValue(':date', '2025-09-25');
        $stmt->bindValue(':slot', 'morning');
        $stmt->execute();

        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('confirmed', $record['deleted_reason']);
    }

    public function testSoftDeleteOtherPreferences()
    {
        // 第2希望を確定として、他を論理削除
        $result = $this->applicationPreferredSlot->softDeleteOtherPreferences(
            $this->testApplicationId,
            '2025-09-26',
            'afternoon'
        );
        $this->assertTrue($result);

        // 確定したもの以外が削除されていることを確認
        $activeSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $this->assertCount(1, $activeSlots);
        $this->assertEquals('2025-09-26', $activeSlots[0]['preferred_date']);
        $this->assertEquals('afternoon', $activeSlots[0]['time_slot']);

        // 削除されたスロットを確認
        $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
        $deletedCount = 0;
        foreach ($allSlots as $slot) {
            if (!empty($slot['deleted_at'])) {
                $deletedCount++;
                $this->assertEquals('confirmed', $slot['deleted_reason']);
            }
        }
        $this->assertEquals(2, $deletedCount);
    }

    public function testGetByDateAndSlotExcludesDeletedSlots()
    {
        // 別のアプリケーションで同じ日時に希望日時を作成
        $testData2 = [
            'customer_name' => 'テスト花子',
            'customer_phone' => '090-5678-1234',
            'customer_email' => 'test2@example.com',
            'postal_code' => '123-4567',
            'address' => 'テスト住所2',
            'building_type' => 'apartment',
            'floor_number' => 3,
            'room_type' => 'bedroom',
            'room_size' => '6jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.2kw',
            'existing_ac' => 'no',
            'existing_ac_removal' => 'no',
            'electrical_work' => 'none',
            'piping_work' => 'new',
            'wall_drilling' => 'no',
            'special_requests' => ''
        ];

        $testApplicationId2 = $this->application->create($testData2);
        $this->applicationPreferredSlot->create($testApplicationId2, '2025-09-25', 'morning', 1);

        try {
            // 削除前は2つの申し込みが該当
            $results = $this->applicationPreferredSlot->getByDateAndSlot('2025-09-25', 'morning');
            $this->assertCount(2, $results);

            // 1つを論理削除
            $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-25', 'morning');

            // 削除後は1つになる
            $results = $this->applicationPreferredSlot->getByDateAndSlot('2025-09-25', 'morning');
            $this->assertCount(1, $results);
            $this->assertEquals($testApplicationId2, $results[0]['application_id']);

        } finally {
            // クリーンアップ
            $this->conn->exec("DELETE FROM application_preferred_slots WHERE application_id = " . $testApplicationId2);
            $this->conn->exec("DELETE FROM applications WHERE id = " . $testApplicationId2);
        }
    }

    public function testSoftDeleteWithDifferentReasons()
    {
        // 異なる理由で削除
        $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-25', 'morning', 'confirmed');
        $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-26', 'afternoon', 'cancelled');
        $this->applicationPreferredSlot->softDelete($this->testApplicationId, '2025-09-27', 'evening', 'manual');

        // 全スロットを取得して理由を確認
        $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);

        $reasonMap = [];
        foreach ($allSlots as $slot) {
            $key = $slot['preferred_date'] . '_' . $slot['time_slot'];
            $reasonMap[$key] = $slot['deleted_reason'];
        }

        $this->assertEquals('confirmed', $reasonMap['2025-09-25_morning']);
        $this->assertEquals('cancelled', $reasonMap['2025-09-26_afternoon']);
        $this->assertEquals('manual', $reasonMap['2025-09-27_evening']);
    }
}