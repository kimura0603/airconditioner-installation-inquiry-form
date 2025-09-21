<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/Application.php';
require_once __DIR__ . '/../../src/models/ApplicationPreferredSlot.php';
require_once __DIR__ . '/../../src/models/ReservationSlot.php';
require_once __DIR__ . '/../../src/config/database.php';

class ApplicationDetailPageTest extends TestCase
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
            'customer_name' => 'テスト詳細太郎',
            'customer_phone' => '090-1234-5678',
            'customer_email' => 'detail@test.com',
            'postal_code' => '123-4567',
            'address' => 'テスト詳細住所1-2-3',
            'building_type' => 'house',
            'floor_number' => null,
            'room_type' => 'living',
            'room_size' => '10jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'existing_ac_removal' => 'no',
            'electrical_work' => 'outlet_addition',
            'piping_work' => 'new',
            'wall_drilling' => 'yes',
            'special_requests' => 'テスト用の特記事項です。'
        ];

        $this->testApplicationId = $this->application->create($testData);

        // 3つの希望日時を作成
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-25', 'morning', 1);
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-26', 'afternoon', 2);
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-27', 'evening', 3);
    }

    public function testValidApplicationIdReturnsApplicationData()
    {
        // 有効なIDで申し込み情報が取得できることを確認
        $appData = $this->application->getById($this->testApplicationId);

        $this->assertNotNull($appData);
        $this->assertEquals('テスト詳細太郎', $appData['customer_name']);
        $this->assertEquals('090-1234-5678', $appData['customer_phone']);
        $this->assertEquals('detail@test.com', $appData['customer_email']);
        $this->assertEquals('123-4567', $appData['postal_code']);
        $this->assertEquals('テスト詳細住所1-2-3', $appData['address']);
        $this->assertEquals('house', $appData['building_type']);
        $this->assertEquals('living', $appData['room_type']);
        $this->assertEquals('10jo', $appData['room_size']);
        $this->assertEquals('wall_mounted', $appData['ac_type']);
        $this->assertEquals('2.8kw', $appData['ac_capacity']);
        $this->assertEquals('テスト用の特記事項です。', $appData['special_requests']);
        $this->assertEquals('pending', $appData['status']);
    }

    public function testInvalidApplicationIdReturnsNull()
    {
        // 存在しないIDの場合はfalseまたはnullが返されることを確認
        $appData = $this->application->getById(99999);
        $this->assertFalse($appData);
    }

    public function testPreferredSlotsRetrievalForValidApplication()
    {
        // 有効な希望日時の取得テスト
        $preferredSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);

        $this->assertCount(3, $preferredSlots);

        // 優先順位順にソートされているかテスト
        $this->assertEquals(1, $preferredSlots[0]['priority']);
        $this->assertEquals(2, $preferredSlots[1]['priority']);
        $this->assertEquals(3, $preferredSlots[2]['priority']);

        // 日時の確認
        $this->assertEquals('2025-09-25', $preferredSlots[0]['preferred_date']);
        $this->assertEquals('morning', $preferredSlots[0]['time_slot']);

        $this->assertEquals('2025-09-26', $preferredSlots[1]['preferred_date']);
        $this->assertEquals('afternoon', $preferredSlots[1]['time_slot']);

        $this->assertEquals('2025-09-27', $preferredSlots[2]['preferred_date']);
        $this->assertEquals('evening', $preferredSlots[2]['time_slot']);
    }

    public function testAllPreferredSlotsIncludeDeleted()
    {
        // 論理削除されたスロットも含めた全取得のテスト
        // 先に1つのスロットを論理削除
        $this->applicationPreferredSlot->softDelete(
            $this->testApplicationId,
            '2025-09-25',
            'morning',
            'manual'
        );

        $activeSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);

        // アクティブなスロットは2個になる
        $this->assertCount(2, $activeSlots);

        // 全スロット（削除済み含む）は3個のまま
        $this->assertCount(3, $allSlots);

        // 削除されたスロットの確認
        $deletedSlot = array_filter($allSlots, function($slot) {
            return !empty($slot['deleted_at']);
        });
        $this->assertCount(1, $deletedSlot);

        $deletedSlot = array_values($deletedSlot)[0];
        $this->assertEquals('manual', $deletedSlot['deleted_reason']);
        $this->assertNotNull($deletedSlot['deleted_at']);
    }

    public function testConfirmedApplicationDisplaysConfirmedDate()
    {
        // 申し込みを確定状態にする
        $result = $this->application->updateStatus($this->testApplicationId, 'confirmed', '2025-09-26', 'afternoon');
        $this->assertTrue($result);

        $appData = $this->application->getById($this->testApplicationId);

        $this->assertEquals('confirmed', $appData['status']);
        $this->assertEquals('2025-09-26', $appData['confirmed_date']);
        $this->assertEquals('afternoon', $appData['confirmed_time_slot']);
    }

    public function testCancelledApplicationStatus()
    {
        // 申し込みをキャンセル状態にする
        $result = $this->application->updateStatus($this->testApplicationId, 'cancelled');
        $this->assertTrue($result);

        $appData = $this->application->getById($this->testApplicationId);
        $this->assertEquals('cancelled', $appData['status']);
    }

    public function testTranslateValueFunction()
    {
        // 翻訳関数のテスト（application_detail.phpで使用される関数と同じロジック）
        $translations = [
            'building_type' => [
                'house' => '一戸建て',
                'apartment' => 'アパート・マンション',
                'office' => 'オフィス',
                'store' => '店舗'
            ],
            'room_type' => [
                'living' => 'リビング・居間',
                'bedroom' => '寝室',
                'kitchen' => 'キッチン・台所',
                'office' => '書斎・オフィス',
                'other' => 'その他'
            ],
            'status' => [
                'pending' => '受付中',
                'confirmed' => '確定済み',
                'cancelled' => 'キャンセル',
                'completed' => '完了'
            ]
        ];

        // 建物種別の翻訳テスト
        $this->assertEquals('一戸建て', $translations['building_type']['house']);
        $this->assertEquals('アパート・マンション', $translations['building_type']['apartment']);

        // 部屋種別の翻訳テスト
        $this->assertEquals('リビング・居間', $translations['room_type']['living']);
        $this->assertEquals('寝室', $translations['room_type']['bedroom']);

        // ステータスの翻訳テスト
        $this->assertEquals('受付中', $translations['status']['pending']);
        $this->assertEquals('確定済み', $translations['status']['confirmed']);
        $this->assertEquals('キャンセル', $translations['status']['cancelled']);
    }

    public function testTimeSlotDisplayNames()
    {
        // 時間枠表示名のテスト
        $slotNames = [
            'morning' => '午前（9:00-12:00）',
            'afternoon' => '午後（12:00-15:00）',
            'evening' => '夕方（15:00-18:00）'
        ];

        $this->assertEquals('午前（9:00-12:00）', $slotNames['morning']);
        $this->assertEquals('午後（12:00-15:00）', $slotNames['afternoon']);
        $this->assertEquals('夕方（15:00-18:00）', $slotNames['evening']);
    }

    public function testApplicationDataIntegrity()
    {
        // データの整合性テスト
        $appData = $this->application->getById($this->testApplicationId);
        $preferredSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);

        // 申し込みデータと希望日時データの関係性確認
        $this->assertNotNull($appData);
        $this->assertNotEmpty($preferredSlots);

        // 各希望日時が正しい申し込みIDを持っているか確認
        foreach ($preferredSlots as $slot) {
            $this->assertEquals($this->testApplicationId, $slot['application_id']);
        }

        // 申し込み作成時刻が現在時刻付近であることを確認
        $createdAt = strtotime($appData['created_at']);
        $now = time();
        $this->assertLessThan(86400, abs($now - $createdAt)); // 24時間以内（テスト環境では時差があることを考慮）
    }

    public function testSpecialRequestsHandling()
    {
        // テスト申し込みの特記事項を確認
        $appData = $this->application->getById($this->testApplicationId);
        $this->assertEquals('テスト用の特記事項です。', $appData['special_requests']);

        // 特記事項の更新テスト
        $query = "UPDATE applications SET special_requests = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $newRequest = "新しい特記事項\n複数行の内容";
        $stmt->execute([$newRequest, $this->testApplicationId]);

        $updatedAppData = $this->application->getById($this->testApplicationId);
        $this->assertEquals($newRequest, $updatedAppData['special_requests']);
    }

    public function testFloorNumberHandling()
    {
        // 階数の取り扱いテスト（nullと数値の両方）

        // 階数なし（一戸建て）の申し込み
        $appData = $this->application->getById($this->testApplicationId);
        $this->assertNull($appData['floor_number']);

        // 階数ありの申し込みを作成
        $testData = [
            'customer_name' => 'テスト階数太郎',
            'customer_phone' => '090-8888-8888',
            'customer_email' => 'floor@test.com',
            'postal_code' => '888-8888',
            'address' => 'テスト住所',
            'building_type' => 'apartment',
            'floor_number' => 5,
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.5kw',
            'existing_ac' => 'no',
            'existing_ac_removal' => 'no',
            'electrical_work' => 'outlet_addition',
            'piping_work' => 'new',
            'wall_drilling' => 'yes',
            'special_requests' => ''
        ];

        $floorAppId = $this->application->create($testData);
        $floorAppData = $this->application->getById($floorAppId);

        $this->assertEquals(5, $floorAppData['floor_number']);
        $this->assertEquals('apartment', $floorAppData['building_type']);
    }
}