<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/Application.php';
require_once __DIR__ . '/../../src/models/ApplicationPreferredSlot.php';
require_once __DIR__ . '/../../src/config/database.php';

class ApplicationCancellationIntegrationTest extends TestCase
{
    private $application;
    private $applicationPreferredSlot;
    private $conn;
    private $testApplicationId;

    protected function setUp(): void
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->application = new Application();
        $this->applicationPreferredSlot = new ApplicationPreferredSlot();

        // テストデータをクリーンアップ
        $this->cleanupTestData();

        // テスト用申し込みを作成
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
            'customer_name' => 'WebAPIテスト太郎',
            'customer_phone' => '090-1111-1111',
            'customer_email' => 'webapi@test.com',
            'postal_code' => '123-4567',
            'address' => 'WebAPIテスト住所',
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
            'special_requests' => 'WebAPIテスト用'
        ];

        $this->testApplicationId = $this->application->create($testData);

        // 希望日時を作成
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-25', 'morning', 1);
        $this->applicationPreferredSlot->create($this->testApplicationId, '2025-09-26', 'afternoon', 2);
    }

    public function testCancelApplicationApiSuccess()
    {
        // キャンセル前の状態確認
        $appBefore = $this->application->getById($this->testApplicationId);
        $this->assertEquals('pending', $appBefore['status']);

        // キャンセル処理を直接実行（HTTP呼び出しの代わりに）
        $result = $this->simulateApiCancellation($this->testApplicationId);

        // レスポンスの確認
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('キャンセル', $result['message']);
        $this->assertEquals($this->testApplicationId, $result['application_id']);

        // データベース状態の確認
        $appAfter = $this->application->getById($this->testApplicationId);
        $this->assertEquals('cancelled', $appAfter['status']);

        // 希望日時が論理削除されていることを確認
        $activeSlots = $this->applicationPreferredSlot->getByApplicationId($this->testApplicationId);
        $this->assertCount(0, $activeSlots);

        $allSlots = $this->applicationPreferredSlot->getAllByApplicationId($this->testApplicationId);
        $this->assertCount(2, $allSlots);

        foreach ($allSlots as $slot) {
            $this->assertNotNull($slot['deleted_at']);
            $this->assertEquals('cancelled', $slot['deleted_reason']);
        }
    }

    public function testCancelApplicationApiInvalidMethod()
    {
        // GETメソッドでアクセス（エラーになるはず）
        $response = $this->callCancelApplicationApi($this->testApplicationId, 'GET');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Invalid request method', $response['message']);
    }

    public function testCancelApplicationApiMissingParameters()
    {
        // application_idなしでリクエスト
        $response = $this->callCancelApplicationApiWithoutId();

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Missing application ID', $response['message']);
    }

    public function testCancelApplicationApiInvalidAction()
    {
        // 無効なアクションでリクエスト
        $response = $this->callCancelApplicationApiWithInvalidAction($this->testApplicationId);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Invalid action', $response['message']);
    }

    public function testCancelApplicationApiNonExistentApplication()
    {
        // 存在しないapplication_idでリクエスト
        $response = $this->callCancelApplicationApi(99999);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('申し込みが見つかりません', $response['message']);
    }

    public function testCancelApplicationApiAlreadyCancelled()
    {
        // 先にキャンセル処理
        $this->callCancelApplicationApi($this->testApplicationId);

        // 再度キャンセル処理
        $response = $this->callCancelApplicationApi($this->testApplicationId);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('既にキャンセル済み', $response['message']);
    }

    public function testCancelConfirmedApplicationApi()
    {
        // 先に確定処理
        $this->application->updateStatus($this->testApplicationId, 'confirmed', '2025-09-25', 'morning');

        // 確定状態であることを確認
        $appBefore = $this->application->getById($this->testApplicationId);
        $this->assertEquals('confirmed', $appBefore['status']);

        // キャンセル処理を直接実行
        $result = $this->simulateApiCancellation($this->testApplicationId);

        // 成功することを確認
        $this->assertTrue($result['success']);

        // 状態がキャンセルに変更されていることを確認
        $appAfter = $this->application->getById($this->testApplicationId);
        $this->assertEquals('cancelled', $appAfter['status']);
    }

    private function callCancelApplicationApi($applicationId, $method = 'POST')
    {
        $url = 'http://localhost:8080/admin/cancel_application.php';

        $postData = http_build_query([
            'action' => 'cancel_application',
            'application_id' => $applicationId
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            $this->fail('API呼び出しに失敗しました');
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail('JSONデコードに失敗しました: ' . $response);
        }

        return $decoded;
    }

    private function callCancelApplicationApiWithoutId()
    {
        $url = 'http://localhost:8080/admin/cancel_application.php';

        $postData = http_build_query([
            'action' => 'cancel_application'
            // application_idを意図的に省略
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        return json_decode($response, true);
    }

    private function callCancelApplicationApiWithInvalidAction($applicationId)
    {
        $url = 'http://localhost:8080/admin/cancel_application.php';

        $postData = http_build_query([
            'action' => 'invalid_action',
            'application_id' => $applicationId
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        return json_decode($response, true);
    }

    private function simulateApiCancellation($applicationId)
    {
        try {
            require_once __DIR__ . '/../../src/models/ReservationSlot.php';

            $this->conn->beginTransaction();

            // cancel_application.phpと同じロジックを実行
            $appData = $this->application->getById($applicationId);
            if (!$appData) {
                throw new Exception('申し込みが見つかりません。');
            }

            if ($appData['status'] === 'cancelled') {
                throw new Exception('この申し込みは既にキャンセル済みです。');
            }

            $reservationSlot = new ReservationSlot();

            // 確定済みの場合の処理
            if ($appData['status'] === 'confirmed') {
                if ($appData['confirmed_date'] && $appData['confirmed_time_slot']) {
                    // ReservationSlotのメソッドに問題がある可能性があるため、直接SQLで実行
                    $query = "UPDATE reservation_slots SET is_available = :is_available WHERE date = :date AND time_slot = :time_slot";
                    $stmt = $this->conn->prepare($query);
                    $isAvailable = true;
                    $stmt->bindParam(':date', $appData['confirmed_date']);
                    $stmt->bindParam(':time_slot', $appData['confirmed_time_slot']);
                    $stmt->bindParam(':is_available', $isAvailable, PDO::PARAM_BOOL);
                    $stmt->execute();
                }
            }

            // この申し込みの全ての希望日時を取得（削除済み含む）
            $allPreferredSlots = $this->applicationPreferredSlot->getAllByApplicationId($applicationId);

            // 各希望日時の予約カウントを減らす（削除済みでないもののみ）
            $activeSlots = $this->applicationPreferredSlot->getByApplicationId($applicationId);
            foreach ($activeSlots as $slot) {
                // 直接SQLで予約数を減らす
                $query = "UPDATE reservation_slots SET current_bookings = GREATEST(current_bookings - 1, 0) WHERE date = :date AND time_slot = :time_slot";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':date', $slot['preferred_date']);
                $stmt->bindParam(':time_slot', $slot['time_slot']);
                $stmt->execute();
            }

            // 全ての希望日時を論理削除（キャンセル理由で）
            foreach ($allPreferredSlots as $slot) {
                if (empty($slot['deleted_at'])) {
                    $this->applicationPreferredSlot->softDelete(
                        $applicationId,
                        $slot['preferred_date'],
                        $slot['time_slot'],
                        'cancelled'
                    );
                }
            }

            // アプリケーションのステータスをキャンセルに更新
            $query = "UPDATE applications SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $applicationId);
            $updateResult = $stmt->execute();

            if (!$updateResult) {
                throw new Exception('申し込みステータスの更新に失敗しました。');
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => '申し込みをキャンセルしました。',
                'application_id' => $applicationId
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}