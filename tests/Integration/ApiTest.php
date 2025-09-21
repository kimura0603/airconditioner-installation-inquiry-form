<?php

namespace Tests\Integration;

use Tests\TestCase;

class ApiTest extends TestCase
{
    /**
     * 空き状況取得APIのテスト
     */
    public function testGetAvailableSlotsApi(): void
    {
        // テストデータの準備
        $app1Id = $this->createTestApplication(['customer_name' => '田中太郎']);
        $this->createTestPreferredSlots($app1Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning'],
            ['date' => '2025-10-01', 'time_slot' => 'afternoon']
        ]);

        // APIの実行
        $response = $this->callGetAvailableSlotsApi('2025-10-01');

        // レスポンスの検証
        $this->assertTrue($response['success']);
        $this->assertEquals('2025-10-01', $response['date']);
        $this->assertCount(3, $response['slots']); // morning, afternoon, evening

        // 各時間帯の検証
        $morningSlot = $this->findSlotByTimeSlot($response['slots'], 'morning');
        $this->assertNotNull($morningSlot);
        $this->assertEquals(1, $morningSlot['current_bookings']);
        $this->assertEquals(1, $morningSlot['available_count']);
        $this->assertTrue($morningSlot['available']);

        $afternoonSlot = $this->findSlotByTimeSlot($response['slots'], 'afternoon');
        $this->assertNotNull($afternoonSlot);
        $this->assertEquals(1, $afternoonSlot['current_bookings']);
        $this->assertEquals(1, $afternoonSlot['available_count']);
        $this->assertTrue($afternoonSlot['available']);

        $eveningSlot = $this->findSlotByTimeSlot($response['slots'], 'evening');
        $this->assertNotNull($eveningSlot);
        $this->assertEquals(0, $eveningSlot['current_bookings']);
        $this->assertEquals(2, $eveningSlot['available_count']);
        $this->assertTrue($eveningSlot['available']);
    }

    /**
     * 確定済み時間帯がAPIで正しく表示されるかのテスト
     */
    public function testConfirmedSlotInApi(): void
    {
        // テストデータの準備と確定
        $applicationId = $this->createTestApplication(['customer_name' => '田中太郎']);
        $this->createTestPreferredSlots($applicationId, [
            ['date' => '2025-10-01', 'time_slot' => 'morning']
        ]);

        // 確定処理
        $this->confirmReservationViaApi($applicationId, '2025-10-01', 'morning');

        // APIでの確認
        $response = $this->callGetAvailableSlotsApi('2025-10-01');

        $morningSlot = $this->findSlotByTimeSlot($response['slots'], 'morning');
        $this->assertNotNull($morningSlot);
        $this->assertEquals(0, $morningSlot['current_bookings']); // リセットされている
        $this->assertEquals(2, $morningSlot['available_count']); // 見た目は2だが
        $this->assertFalse($morningSlot['available']); // 実際は予約不可
    }

    /**
     * 満席時間帯のAPIレスポンステスト
     */
    public function testFullSlotInApi(): void
    {
        // 2件の申し込みで満席にする
        $app1Id = $this->createTestApplication(['customer_name' => '田中太郎']);
        $app2Id = $this->createTestApplication(['customer_name' => '佐藤次郎']);

        $this->createTestPreferredSlots($app1Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning']
        ]);
        $this->createTestPreferredSlots($app2Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning']
        ]);

        // APIでの確認（満席状態）
        $response = $this->callGetAvailableSlotsApi('2025-10-01');

        $morningSlot = $this->findSlotByTimeSlot($response['slots'], 'morning');
        $this->assertNotNull($morningSlot);
        $this->assertEquals(2, $morningSlot['current_bookings']);
        $this->assertEquals(0, $morningSlot['available_count']);
        $this->assertFalse($morningSlot['available']); // 満席で予約不可
    }

    /**
     * 予約確定APIのテスト
     */
    public function testConfirmReservationApi(): void
    {
        // テストデータの準備
        $applicationId = $this->createTestApplication(['customer_name' => '田中太郎']);
        $this->createTestPreferredSlots($applicationId, [
            ['date' => '2025-10-01', 'time_slot' => 'morning'],
            ['date' => '2025-10-02', 'time_slot' => 'afternoon']
        ]);

        // 確定API呼び出し
        $response = $this->callConfirmReservationApi($applicationId, '2025-10-01', 'morning');

        // レスポンスの検証
        $this->assertTrue($response['success']);
        $this->assertEquals('予約が確定されました。', $response['message']);
        $this->assertEquals($applicationId, $response['application_id']);
        $this->assertEquals('2025-10-01', $response['confirmed_date']);
        $this->assertEquals('morning', $response['confirmed_time_slot']);

        // 確定後の状態確認
        $this->assertApplicationState($applicationId, 'confirmed', '2025-10-01', 'morning');
        $this->assertReservationSlotState('2025-10-01', 'morning', 0, false);
        $this->assertReservationSlotState('2025-10-02', 'afternoon', 0, true);
        $this->assertPreferredSlotsDeleted($applicationId);
    }

    /**
     * 不正な確定API呼び出しのテスト
     */
    public function testInvalidConfirmReservationApi(): void
    {
        // 存在しないアプリケーションでの確定試行
        $response = $this->callConfirmReservationApi(99999, '2025-10-01', 'morning');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('申し込みが見つかりません', $response['message']);
    }

    /**
     * 複数の申し込みでの確定処理統合テスト
     */
    public function testMultipleApplicationsIntegration(): void
    {
        // 複数の申し込みを作成
        $app1Id = $this->createTestApplication(['customer_name' => '田中太郎']);
        $app2Id = $this->createTestApplication(['customer_name' => '佐藤次郎']);
        $app3Id = $this->createTestApplication(['customer_name' => '鈴木三郎']);

        // 重複する希望日時を設定
        $this->createTestPreferredSlots($app1Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning'], // 共通
            ['date' => '2025-10-02', 'time_slot' => 'afternoon']
        ]);
        $this->createTestPreferredSlots($app2Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning'], // 共通
            ['date' => '2025-10-03', 'time_slot' => 'evening']
        ]);
        $this->createTestPreferredSlots($app3Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning'], // 共通
            ['date' => '2025-10-04', 'time_slot' => 'afternoon']
        ]);

        // 初期状態の確認
        $response = $this->callGetAvailableSlotsApi('2025-10-01');
        $morningSlot = $this->findSlotByTimeSlot($response['slots'], 'morning');
        $this->assertEquals(3, $morningSlot['current_bookings']); // 3件の希望

        // 順次確定していく
        $this->callConfirmReservationApi($app1Id, '2025-10-02', 'afternoon');

        // 1件確定後の状態確認
        $response = $this->callGetAvailableSlotsApi('2025-10-01');
        $morningSlot = $this->findSlotByTimeSlot($response['slots'], 'morning');
        $this->assertEquals(2, $morningSlot['current_bookings']); // 3→2に減少

        $this->callConfirmReservationApi($app2Id, '2025-10-01', 'morning');

        // 2件目確定後の状態確認
        $response = $this->callGetAvailableSlotsApi('2025-10-01');
        $morningSlot = $this->findSlotByTimeSlot($response['slots'], 'morning');
        $this->assertEquals(1, $morningSlot['current_bookings']); // 2→1に減少
        $this->assertFalse($morningSlot['available']); // 確定済みで予約不可

        // 最後の申し込みは別日時で確定
        $this->callConfirmReservationApi($app3Id, '2025-10-04', 'afternoon');

        // 最終状態の確認
        $response = $this->callGetAvailableSlotsApi('2025-10-01');
        $morningSlot = $this->findSlotByTimeSlot($response['slots'], 'morning');
        $this->assertEquals(0, $morningSlot['current_bookings']); // 1→0に減少
        $this->assertFalse($morningSlot['available']); // 確定済みで予約不可のまま
    }

    /**
     * ヘルパーメソッド: 空き状況取得API呼び出し
     */
    private function callGetAvailableSlotsApi($date): array
    {
        // get_available_slots.phpの処理を模擬
        $reservationSlot = new \ReservationSlot();
        $timeSlots = ['morning', 'afternoon', 'evening'];
        $availableSlots = [];

        foreach ($timeSlots as $slot) {
            $slotInfo = $reservationSlot->getSlotInfo($date, $slot);

            if ($slotInfo && $slotInfo['is_active']) {
                $isAvailable = $slotInfo['is_available'] &&
                              $slotInfo['current_bookings'] < $slotInfo['max_capacity'];

                $availableSlots[] = [
                    'time_slot' => $slot,
                    'display_name' => $slotInfo['display_name'],
                    'start_time' => $slotInfo['start_time'],
                    'end_time' => $slotInfo['end_time'],
                    'max_capacity' => $slotInfo['max_capacity'],
                    'current_bookings' => $slotInfo['current_bookings'],
                    'available_count' => $slotInfo['max_capacity'] - $slotInfo['current_bookings'],
                    'available' => $isAvailable
                ];
            }
        }

        return [
            'success' => true,
            'date' => $date,
            'slots' => $availableSlots
        ];
    }

    /**
     * ヘルパーメソッド: 予約確定API呼び出し
     */
    private function callConfirmReservationApi($applicationId, $date, $timeSlot): array
    {
        return $this->confirmReservationViaApi($applicationId, $date, $timeSlot);
    }

    /**
     * ヘルパーメソッド: 確定処理の実行
     */
    private function confirmReservationViaApi($applicationId, $date, $timeSlot): array
    {
        try {
            $this->testConnection->beginTransaction();

            $application = new \Application();
            $applicationPreferredSlot = new \ApplicationPreferredSlot();
            $reservationSlot = new \ReservationSlot();

            $appData = $application->getById($applicationId);
            if (!$appData) {
                throw new \Exception('申し込みが見つかりません。');
            }

            if ($appData['status'] !== 'pending') {
                throw new \Exception('この申し込みは既に処理済みです。');
            }

            if (!$reservationSlot->isSlotAvailable($date, $timeSlot)) {
                throw new \Exception('選択された日時は既に満席です。');
            }

            $preferredSlots = $applicationPreferredSlot->getByApplicationId($applicationId);

            $updateResult = $application->updateStatus($applicationId, 'confirmed', $date, $timeSlot);
            if (!$updateResult) {
                throw new \Exception('申し込みステータスの更新に失敗しました。');
            }

            foreach ($preferredSlots as $slot) {
                $reservationSlot->decrementBooking($slot['preferred_date'], $slot['time_slot']);
            }

            $deleteResult = $applicationPreferredSlot->deleteByApplicationId($applicationId);
            if (!$deleteResult) {
                throw new \Exception('希望日時レコードの削除に失敗しました。');
            }

            $reservationSlot->setSlotAvailability($date, $timeSlot, false);

            $this->testConnection->commit();

            return [
                'success' => true,
                'message' => '予約が確定されました。',
                'application_id' => $applicationId,
                'confirmed_date' => $date,
                'confirmed_time_slot' => $timeSlot
            ];

        } catch (\Exception $e) {
            $this->testConnection->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * ヘルパーメソッド: 時間帯でスロットを検索
     */
    private function findSlotByTimeSlot($slots, $timeSlot): ?array
    {
        foreach ($slots as $slot) {
            if ($slot['time_slot'] === $timeSlot) {
                return $slot;
            }
        }
        return null;
    }
}