<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/Application.php';
require_once __DIR__ . '/../../src/config/database.php';

class ApplicationStatusFilterTest extends TestCase
{
    private $application;
    private $conn;
    private $testApplicationIds = [];

    protected function setUp(): void
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->application = new Application();

        // テスト用データベースなので、全てのデータを削除してクリーンな状態にする
        $this->conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->conn->exec("DELETE FROM application_preferred_slots");
        $this->conn->exec("DELETE FROM applications");
        $this->conn->exec("ALTER TABLE applications AUTO_INCREMENT = 1");
        $this->conn->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 異なるステータスのテストアプリケーションを作成
        $this->createTestApplications();
    }

    protected function tearDown(): void
    {
        // テスト後のクリーンアップ
        $this->conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->conn->exec("DELETE FROM application_preferred_slots");
        $this->conn->exec("DELETE FROM applications");
        $this->conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    private function createTestApplications()
    {
        $baseTestData = [
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

        // pending状態のアプリケーション（2件）
        $pendingData1 = array_merge($baseTestData, ['customer_name' => 'テスト太郎（受付中1）']);
        $pendingData2 = array_merge($baseTestData, ['customer_name' => 'テスト花子（受付中2）']);

        $this->testApplicationIds['pending1'] = $this->application->create($pendingData1);
        $this->testApplicationIds['pending2'] = $this->application->create($pendingData2);

        // confirmed状態のアプリケーション（1件）
        $confirmedData = array_merge($baseTestData, ['customer_name' => 'テスト次郎（確定済み）']);
        $confirmedId = $this->application->create($confirmedData);
        $this->testApplicationIds['confirmed'] = $confirmedId;

        // ステータスを確定済みに更新
        $this->application->updateStatus($confirmedId, 'confirmed', '2025-09-25', 'morning');

        // cancelled状態のアプリケーション（1件）
        $cancelledData = array_merge($baseTestData, ['customer_name' => 'テスト三郎（キャンセル）']);
        $cancelledId = $this->application->create($cancelledData);
        $this->testApplicationIds['cancelled'] = $cancelledId;

        // ステータスをキャンセルに更新
        $query = "UPDATE applications SET status = 'cancelled' WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $cancelledId);
        $stmt->execute();
    }

    public function testGetAllReturnsAllApplications()
    {
        $applications = $this->application->getAll();

        // 作成した4件のアプリケーションの存在を確認
        $this->assertEquals(4, count($applications));

        // IDが連番（1,2,3,4）であることを確認
        $foundIds = array_column($applications, 'id');
        sort($foundIds);
        $this->assertEquals([1, 2, 3, 4], $foundIds);

        // 各ステータスのアプリケーションが適切に作成されていることを確認
        $statusCounts = array_count_values(array_column($applications, 'status'));
        $this->assertEquals(2, $statusCounts['pending']);
        $this->assertEquals(1, $statusCounts['confirmed']);
        $this->assertEquals(1, $statusCounts['cancelled']);
    }

    public function testGetByStatusPending()
    {
        $pendingApplications = $this->application->getByStatus('pending');

        // pending状態の件数をチェック
        $testPendingCount = 0;
        foreach ($pendingApplications as $app) {
            $this->assertEquals('pending', $app['status']);
            if (in_array($app['id'], [$this->testApplicationIds['pending1'], $this->testApplicationIds['pending2']])) {
                $testPendingCount++;
            }
        }

        // 作成した2件のpendingアプリケーションが含まれていることを確認
        $this->assertEquals(2, $testPendingCount);
    }

    public function testGetByStatusConfirmed()
    {
        $confirmedApplications = $this->application->getByStatus('confirmed');

        // 作成したconfirmedアプリケーションが含まれていることを確認
        $this->assertEquals(1, count($confirmedApplications));

        $confirmedApp = $confirmedApplications[0];
        $this->assertEquals('confirmed', $confirmedApp['status']);
        $this->assertEquals($this->testApplicationIds['confirmed'], $confirmedApp['id']);

        // 確定済みの場合、confirmed_dateとconfirmed_time_slotが設定されている
        $this->assertNotNull($confirmedApp['confirmed_date']);
        $this->assertNotNull($confirmedApp['confirmed_time_slot']);
        $this->assertEquals('2025-09-25', $confirmedApp['confirmed_date']);
        $this->assertEquals('morning', $confirmedApp['confirmed_time_slot']);
    }

    public function testGetByStatusCancelled()
    {
        $cancelledApplications = $this->application->getByStatus('cancelled');

        // 作成したcancelledアプリケーションが含まれていることを確認
        $this->assertEquals(1, count($cancelledApplications));

        $cancelledApp = $cancelledApplications[0];
        $this->assertEquals('cancelled', $cancelledApp['status']);
        $this->assertEquals($this->testApplicationIds['cancelled'], $cancelledApp['id']);
    }

    public function testGetByStatusWithNullReturnsAll()
    {
        // nullを渡すと全件取得される
        $allApplications = $this->application->getByStatus(null);
        $allApplications2 = $this->application->getAll();

        // 同じ結果になるはず
        $this->assertEquals(count($allApplications2), count($allApplications));
    }

    public function testGetByStatusWithInvalidStatusReturnsEmpty()
    {
        // 存在しないステータスで検索
        $invalidApplications = $this->application->getByStatus('invalid_status');

        // 空の配列が返される
        $this->assertIsArray($invalidApplications);
        $this->assertEmpty($invalidApplications);
    }

    public function testApplicationsOrderedByCreatedAtDesc()
    {
        // pending状態のアプリケーションを取得
        $pendingApplications = $this->application->getByStatus('pending');

        // 2件以上あることを確認
        $this->assertGreaterThanOrEqual(2, count($pendingApplications));

        // created_atで降順になっていることを確認
        for ($i = 1; $i < count($pendingApplications); $i++) {
            $prevCreatedAt = strtotime($pendingApplications[$i-1]['created_at']);
            $currentCreatedAt = strtotime($pendingApplications[$i]['created_at']);
            $this->assertGreaterThanOrEqual($currentCreatedAt, $prevCreatedAt);
        }
    }

    public function testGetByStatusPreservesAllApplicationFields()
    {
        $pendingApplications = $this->application->getByStatus('pending');

        // pendingアプリケーションが2件あることを確認
        $this->assertEquals(2, count($pendingApplications));

        // 作成したpendingアプリケーションの1つを取得
        $testApp = $pendingApplications[0]; // 最初のpendingアプリケーション

        // 主要フィールドが全て含まれていることを確認
        $this->assertEquals('090-1234-5678', $testApp['customer_phone']);
        $this->assertEquals('test@example.com', $testApp['customer_email']);
        $this->assertEquals('house', $testApp['building_type']);
        $this->assertEquals('living', $testApp['room_type']);
        $this->assertEquals('pending', $testApp['status']);
        $this->assertNotNull($testApp['created_at']);
        $this->assertNotNull($testApp['updated_at']);
        $this->assertStringContainsString('テスト', $testApp['customer_name']);
        $this->assertStringContainsString('受付中', $testApp['customer_name']);
    }

    public function testStatusFilterCombinationsTotal()
    {
        // 各ステータスの件数を確認
        $pendingCount = count($this->application->getByStatus('pending'));
        $confirmedCount = count($this->application->getByStatus('confirmed'));
        $cancelledCount = count($this->application->getByStatus('cancelled'));
        $totalCount = count($this->application->getAll());

        // 作成したテストデータの件数が正確であることを確認
        $this->assertEquals(2, $pendingCount); // pending2件
        $this->assertEquals(1, $confirmedCount); // confirmed1件
        $this->assertEquals(1, $cancelledCount); // cancelled1件
        $this->assertEquals(4, $totalCount); // 全体4件

        // 各ステータスの合計が全体の件数と一致することを確認
        $this->assertEquals($totalCount, $pendingCount + $confirmedCount + $cancelledCount);
    }
}