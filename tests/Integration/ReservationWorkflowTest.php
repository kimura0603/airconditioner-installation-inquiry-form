<?php

namespace Tests\Integration;

use Tests\TestCase;

/**
 * 予約システム全体のワークフローテスト
 * 実際の使用シナリオに基づいたエンドツーエンドテスト
 */
class ReservationWorkflowTest extends TestCase
{
    /**
     * シナリオ1: 基本的な予約申し込みから確定までの流れ
     */
    public function testBasicReservationWorkflow(): void
    {
        // ステップ1: 顧客が申し込みを行う
        $applicationId = $this->createTestApplication([
            'customer_name' => '田中太郎',
            'customer_phone' => '090-1234-5678',
            'customer_email' => 'tanaka@example.com'
        ]);

        // ステップ2: 希望日時を3つ選択
        $this->createTestPreferredSlots($applicationId, [
            ['date' => '2025-10-15', 'time_slot' => 'morning'],    // 第1希望
            ['date' => '2025-10-16', 'time_slot' => 'afternoon'],  // 第2希望
            ['date' => '2025-10-17', 'time_slot' => 'evening']     // 第3希望
        ]);

        // ステップ3: 管理者が空き状況を確認
        $availableSlots = $this->callGetAvailableSlotsApi('2025-10-15');
        $this->assertTrue($availableSlots['success']);
        $morningSlot = $this->findSlotByTimeSlot($availableSlots['slots'], 'morning');
        $this->assertEquals(1, $morningSlot['current_bookings']); // 1件の希望
        $this->assertTrue($morningSlot['available']); // 予約可能

        // ステップ4: 管理者が第1希望で確定
        $confirmResult = $this->callConfirmReservationApi($applicationId, '2025-10-15', 'morning');
        $this->assertTrue($confirmResult['success']);

        // ステップ5: 確定後の状態確認
        $this->assertApplicationState($applicationId, 'confirmed', '2025-10-15', 'morning');
        $this->assertPreferredSlotsDeleted($applicationId);

        // ステップ6: 他の希望日時が解放されているか確認
        $slot2 = $this->callGetAvailableSlotsApi('2025-10-16');
        $afternoonSlot = $this->findSlotByTimeSlot($slot2['slots'], 'afternoon');
        $this->assertEquals(0, $afternoonSlot['current_bookings']); // 解放済み

        $slot3 = $this->callGetAvailableSlotsApi('2025-10-17');
        $eveningSlot = $this->findSlotByTimeSlot($slot3['slots'], 'evening');
        $this->assertEquals(0, $eveningSlot['current_bookings']); // 解放済み

        // ステップ7: 確定した日時は予約不可になっているか確認
        $confirmedSlot = $this->callGetAvailableSlotsApi('2025-10-15');
        $confirmedMorning = $this->findSlotByTimeSlot($confirmedSlot['slots'], 'morning');
        $this->assertFalse($confirmedMorning['available']); // 予約不可
    }

    /**
     * シナリオ2: 複数顧客の競合する予約申し込み処理
     */
    public function testCompetingReservationsWorkflow(): void
    {
        // 3人の顧客が同じ人気日時を第1希望とする状況
        $popularDate = '2025-11-01';
        $popularSlot = 'morning';

        // 顧客A: 人気日時 + 代替日時2つ
        $appAId = $this->createTestApplication(['customer_name' => '顧客A']);
        $this->createTestPreferredSlots($appAId, [
            ['date' => $popularDate, 'time_slot' => $popularSlot],      // 人気日時
            ['date' => '2025-11-02', 'time_slot' => 'afternoon'],
            ['date' => '2025-11-03', 'time_slot' => 'evening']
        ]);

        // 顧客B: 人気日時 + 代替日時2つ
        $appBId = $this->createTestApplication(['customer_name' => '顧客B']);
        $this->createTestPreferredSlots($appBId, [
            ['date' => $popularDate, 'time_slot' => $popularSlot],      // 人気日時
            ['date' => '2025-11-04', 'time_slot' => 'morning'],
            ['date' => '2025-11-05', 'time_slot' => 'afternoon']
        ]);

        // 顧客C: 人気日時のみ（代替なし）
        $appCId = $this->createTestApplication(['customer_name' => '顧客C']);
        $this->createTestPreferredSlots($appCId, [
            ['date' => $popularDate, 'time_slot' => $popularSlot]       // 人気日時のみ
        ]);

        // 管理者が人気日時の状況を確認（3件の希望で満席＋待ち）
        $popularSlotInfo = $this->callGetAvailableSlotsApi($popularDate);
        $morning = $this->findSlotByTimeSlot($popularSlotInfo['slots'], $popularSlot);
        $this->assertEquals(3, $morning['current_bookings']); // 3件の希望
        $this->assertFalse($morning['available']); // 満席で新規受付不可

        // 管理者の判断: 顧客Aを人気日時で確定
        $confirmA = $this->callConfirmReservationApi($appAId, $popularDate, $popularSlot);
        $this->assertTrue($confirmA['success']);

        // 確定後の人気日時状況確認
        $afterConfirmA = $this->callGetAvailableSlotsApi($popularDate);
        $morningAfterA = $this->findSlotByTimeSlot($afterConfirmA['slots'], $popularSlot);
        $this->assertEquals(2, $morningAfterA['current_bookings']); // 3→2に減少
        $this->assertFalse($morningAfterA['available']); // 確定済みで予約不可

        // 管理者の判断: 顧客Bを代替日時で確定
        $confirmB = $this->callConfirmReservationApi($appBId, '2025-11-04', 'morning');
        $this->assertTrue($confirmB['success']);

        // 顧客Bの確定後、人気日時の希望が1つ減る
        $afterConfirmB = $this->callGetAvailableSlotsApi($popularDate);
        $morningAfterB = $this->findSlotByTimeSlot($afterConfirmB['slots'], $popularSlot);
        $this->assertEquals(1, $morningAfterB['current_bookings']); // 2→1に減少（顧客Cのみ）

        // 管理者の判断: 顧客Cを人気日時で確定しようとするが失敗（既に確定済み）
        $confirmC = $this->callConfirmReservationApi($appCId, $popularDate, $popularSlot);
        $this->assertFalse($confirmC['success']); // 既に確定済みなので失敗
        $this->assertStringContainsString('満席', $confirmC['message']);

        // 最終状態の確認
        $this->assertApplicationState($appAId, 'confirmed', $popularDate, $popularSlot);
        $this->assertApplicationState($appBId, 'confirmed', '2025-11-04', 'morning');
        $this->assertApplicationState($appCId, 'pending'); // 確定できず
    }

    /**
     * シナリオ3: 予約キャンセルと枠の復活（将来実装）
     */
    public function testReservationCancellationWorkflow(): void
    {
        // 基本的な確定処理
        $applicationId = $this->createTestApplication(['customer_name' => '田中太郎']);
        $this->createTestPreferredSlots($applicationId, [
            ['date' => '2025-12-01', 'time_slot' => 'morning']
        ]);

        $this->callConfirmReservationApi($applicationId, '2025-12-01', 'morning');

        // 確定状態の確認
        $this->assertApplicationState($applicationId, 'confirmed', '2025-12-01', 'morning');
        $this->assertReservationSlotState('2025-12-01', 'morning', 0, false);

        // TODO: キャンセル機能の実装とテスト
        // - アプリケーションステータスを'cancelled'に変更
        // - 予約枠を再度利用可能にする
        // - 他の顧客が予約できるようになる

        $this->markTestIncomplete('キャンセル機能は将来実装予定');
    }

    /**
     * シナリオ4: データ不整合の検出と修正
     */
    public function testDataConsistencyValidation(): void
    {
        // 正常な状態を作成
        $app1Id = $this->createTestApplication(['customer_name' => '田中太郎']);
        $this->createTestPreferredSlots($app1Id, [
            ['date' => '2025-12-15', 'time_slot' => 'morning'],
            ['date' => '2025-12-16', 'time_slot' => 'afternoon']
        ]);

        // 確定処理
        $this->callConfirmReservationApi($app1Id, '2025-12-15', 'morning');

        // データ整合性の検証
        $this->validateDataConsistency($app1Id, '2025-12-15', 'morning');
    }

    /**
     * シナリオ5: 大量申し込みでの性能テスト
     */
    public function testHighVolumeReservations(): void
    {
        $startTime = microtime(true);

        // 10件の申し込みを作成
        $applicationIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $appId = $this->createTestApplication(['customer_name' => "顧客{$i}"]);
            $this->createTestPreferredSlots($appId, [
                ['date' => '2025-12-20', 'time_slot' => 'morning'],
                ['date' => '2025-12-21', 'time_slot' => 'afternoon'],
                ['date' => '2025-12-22', 'time_slot' => 'evening']
            ]);
            $applicationIds[] = $appId;
        }

        // 人気日時の確認
        $popularSlotInfo = $this->callGetAvailableSlotsApi('2025-12-20');
        $morning = $this->findSlotByTimeSlot($popularSlotInfo['slots'], 'morning');
        $this->assertEquals(10, $morning['current_bookings']); // 10件の希望

        // 2件を確定
        $this->callConfirmReservationApi($applicationIds[0], '2025-12-20', 'morning');
        $this->callConfirmReservationApi($applicationIds[1], '2025-12-21', 'afternoon');

        // 残りの状況確認
        $afterConfirm = $this->callGetAvailableSlotsApi('2025-12-20');
        $morningAfter = $this->findSlotByTimeSlot($afterConfirm['slots'], 'morning');
        $this->assertEquals(8, $morningAfter['current_bookings']); // 10→8に減少

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // 性能チェック（10秒以内で完了することを確認）
        $this->assertLessThan(10.0, $executionTime, '大量データ処理が10秒以内に完了すること');
    }

    /**
     * データ整合性の検証ヘルパー
     */
    private function validateDataConsistency($applicationId, $confirmedDate, $confirmedTimeSlot): void
    {
        // 1. アプリケーションの状態確認
        $this->assertApplicationState($applicationId, 'confirmed', $confirmedDate, $confirmedTimeSlot);

        // 2. 希望日時レコードが削除されていることを確認
        $this->assertPreferredSlotsDeleted($applicationId);

        // 3. 確定した日時が予約不可になっていることを確認
        $this->assertReservationSlotState($confirmedDate, $confirmedTimeSlot, 0, false);

        // 4. 確定済みアプリケーションに対する希望日時の残存チェック
        $stmt = $this->testConnection->prepare("
            SELECT COUNT(*) as count
            FROM application_preferred_slots aps
            JOIN applications a ON aps.application_id = a.id
            WHERE a.status = 'confirmed'
        ");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals(0, $result['count'], '確定済みアプリケーションに希望日時レコードが残存していない');

        // 5. 予約枠の不整合チェック
        $stmt = $this->testConnection->prepare("
            SELECT rs.reservation_date, rs.time_slot, rs.current_bookings,
                   COUNT(aps.id) as actual_bookings
            FROM reservation_slots rs
            LEFT JOIN application_preferred_slots aps ON
                rs.reservation_date = aps.preferred_date AND rs.time_slot = aps.time_slot
            LEFT JOIN applications a ON aps.application_id = a.id AND a.status = 'pending'
            WHERE rs.reservation_date = ?
            GROUP BY rs.reservation_date, rs.time_slot
        ");
        $stmt->execute([$confirmedDate]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($row['time_slot'] === $confirmedTimeSlot) {
                // 確定済みスロットは current_bookings = 0 であるべき
                $this->assertEquals(0, $row['current_bookings'],
                    "確定済みスロット {$confirmedDate} {$confirmedTimeSlot} のcurrent_bookingsは0であるべき");
            } else {
                // 他のスロットは実際の希望数と一致するべき
                $this->assertEquals($row['actual_bookings'], $row['current_bookings'],
                    "スロット {$row['reservation_date']} {$row['time_slot']} のカウントが不整合");
            }
        }
    }

    // 既存のヘルパーメソッドを継承...
    private function callGetAvailableSlotsApi($date): array
    {
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

    private function callConfirmReservationApi($applicationId, $date, $timeSlot): array
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