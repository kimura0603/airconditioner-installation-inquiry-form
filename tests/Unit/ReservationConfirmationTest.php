<?php

namespace Tests\Unit;

use Tests\TestCase;

class ReservationConfirmationTest extends TestCase
{
    /**
     * 基本的な予約確定処理のテスト
     */
    public function testBasicReservationConfirmation(): void
    {
        // テストデータの準備
        $applicationId = $this->createTestApplication(['customer_name' => '田中太郎']);
        $this->createTestPreferredSlots($applicationId, [
            ['date' => '2025-10-01', 'time_slot' => 'morning'],
            ['date' => '2025-10-02', 'time_slot' => 'afternoon'],
            ['date' => '2025-10-03', 'time_slot' => 'evening']
        ]);

        // 初期状態の確認
        $this->assertApplicationState($applicationId, 'pending');

        // 確定処理の実行
        $this->confirmReservation($applicationId, '2025-10-01', 'morning');

        // 結果の検証
        $this->assertApplicationState($applicationId, 'confirmed', '2025-10-01', 'morning');
        $this->assertReservationSlotState('2025-10-01', 'morning', 0, false); // 確定済みなので利用不可
        $this->assertReservationSlotState('2025-10-02', 'afternoon', 0, true); // リリースされて利用可能
        $this->assertReservationSlotState('2025-10-03', 'evening', 0, true); // リリースされて利用可能
        $this->assertPreferredSlotsDeleted($applicationId);
    }

    /**
     * 複数の申し込みがある場合の予約確定テスト
     */
    public function testMultipleApplicationsReservationConfirmation(): void
    {
        // 1つ目の申し込み
        $app1Id = $this->createTestApplication(['customer_name' => '田中太郎']);
        $this->createTestPreferredSlots($app1Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning'],
            ['date' => '2025-10-02', 'time_slot' => 'afternoon']
        ]);

        // 2つ目の申し込み（同じ日時を希望）
        $app2Id = $this->createTestApplication(['customer_name' => '佐藤次郎']);
        $this->createTestPreferredSlots($app2Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning'],
            ['date' => '2025-10-03', 'time_slot' => 'evening']
        ]);

        // 予約枠の初期状態確認（2つの申し込みで2件ずつカウント）
        $this->assertReservationSlotState('2025-10-01', 'morning', 2, true);
        $this->assertReservationSlotState('2025-10-02', 'afternoon', 1, true);
        $this->assertReservationSlotState('2025-10-03', 'evening', 1, true);

        // 1つ目の申し込みを確定
        $this->confirmReservation($app1Id, '2025-10-01', 'morning');

        // 結果の検証
        $this->assertApplicationState($app1Id, 'confirmed', '2025-10-01', 'morning');
        $this->assertApplicationState($app2Id, 'pending'); // まだ未確定

        // 予約枠の状態確認
        $this->assertReservationSlotState('2025-10-01', 'morning', 1, false); // 確定済み＋残り1件の希望
        $this->assertReservationSlotState('2025-10-02', 'afternoon', 0, true); // app1の希望がリリース
        $this->assertReservationSlotState('2025-10-03', 'evening', 1, true); // app2の希望は残存

        // 1つ目の申し込みの希望日時が削除されていることを確認
        $this->assertPreferredSlotsDeleted($app1Id);

        // 2つ目の申し込みの希望日時は残っていることを確認
        $preferredSlot = new \ApplicationPreferredSlot();
        $app2Slots = $preferredSlot->getByApplicationId($app2Id);
        $this->assertCount(2, $app2Slots, 'App2の希望日時は残っているべき');
    }

    /**
     * 満席の時間帯での確定試行のテスト
     */
    public function testConfirmationOnFullSlot(): void
    {
        // 満席の状況を作成（max_capacity = 2に対して2件の確定済み予約）
        $app1Id = $this->createTestApplication(['customer_name' => '田中太郎']);
        $app2Id = $this->createTestApplication(['customer_name' => '佐藤次郎']);

        $this->createTestPreferredSlots($app1Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning']
        ]);
        $this->createTestPreferredSlots($app2Id, [
            ['date' => '2025-10-01', 'time_slot' => 'morning']
        ]);

        // 2件とも確定して満席にする
        $this->confirmReservation($app1Id, '2025-10-01', 'morning');
        $this->confirmReservation($app2Id, '2025-10-01', 'morning'); // これは失敗するはず

        // 2つ目の確定は失敗し、pendingのままであることを確認
        $this->assertApplicationState($app1Id, 'confirmed', '2025-10-01', 'morning');
        $this->assertApplicationState($app2Id, 'pending'); // 確定に失敗
    }

    /**
     * 既に確定済みの申し込みに対する再確定試行のテスト
     */
    public function testConfirmationOnAlreadyConfirmedApplication(): void
    {
        $applicationId = $this->createTestApplication(['customer_name' => '田中太郎']);
        $this->createTestPreferredSlots($applicationId, [
            ['date' => '2025-10-01', 'time_slot' => 'morning']
        ]);

        // 最初の確定
        $this->confirmReservation($applicationId, '2025-10-01', 'morning');
        $this->assertApplicationState($applicationId, 'confirmed', '2025-10-01', 'morning');

        // 再度確定を試行（エラーになるはず）
        $result = $this->confirmReservation($applicationId, '2025-10-02', 'afternoon', false);
        $this->assertFalse($result['success'], '既に確定済みの申し込みの再確定は失敗するべき');
        $this->assertStringContainsString('既に処理済み', $result['message']);

        // 状態が変わっていないことを確認
        $this->assertApplicationState($applicationId, 'confirmed', '2025-10-01', 'morning');
    }

    /**
     * 予約枠のリリースによる空き状況復活のテスト
     */
    public function testSlotReleaseAfterConfirmation(): void
    {
        // 3つの申し込みで同じ日時を希望
        $app1Id = $this->createTestApplication(['customer_name' => '田中太郎']);
        $app2Id = $this->createTestApplication(['customer_name' => '佐藤次郎']);
        $app3Id = $this->createTestApplication(['customer_name' => '鈴木三郎']);

        $sharedSlot = ['date' => '2025-10-01', 'time_slot' => 'morning'];

        $this->createTestPreferredSlots($app1Id, [
            $sharedSlot,
            ['date' => '2025-10-02', 'time_slot' => 'afternoon']
        ]);
        $this->createTestPreferredSlots($app2Id, [
            $sharedSlot,
            ['date' => '2025-10-03', 'time_slot' => 'evening']
        ]);
        $this->createTestPreferredSlots($app3Id, [
            $sharedSlot
        ]);

        // 初期状態：3件の希望で満席＋待ち
        $this->assertReservationSlotState('2025-10-01', 'morning', 3, true);

        // 1つ目を別の日時で確定
        $this->confirmReservation($app1Id, '2025-10-02', 'afternoon');

        // 共有スロットのカウントが減ることを確認
        $this->assertReservationSlotState('2025-10-01', 'morning', 2, true); // 3→2に減少
        $this->assertReservationSlotState('2025-10-02', 'afternoon', 0, false); // 確定済み

        // 2つ目も別の日時で確定
        $this->confirmReservation($app2Id, '2025-10-03', 'evening');

        // さらにカウントが減ることを確認
        $this->assertReservationSlotState('2025-10-01', 'morning', 1, true); // 2→1に減少
        $this->assertReservationSlotState('2025-10-03', 'evening', 0, false); // 確定済み

        // 3つ目を共有スロットで確定
        $this->confirmReservation($app3Id, '2025-10-01', 'morning');

        // 最終的に共有スロットは確定済みになる
        $this->assertReservationSlotState('2025-10-01', 'morning', 0, false); // 確定済み
    }

    /**
     * 確定処理のヘルパーメソッド
     */
    private function confirmReservation($applicationId, $date, $timeSlot, $expectSuccess = true): array
    {
        // 確定処理の実行（confirm_reservation.phpの処理を模擬）
        try {
            $this->testConnection->beginTransaction();

            $application = new \Application();
            $applicationPreferredSlot = new \ApplicationPreferredSlot();
            $reservationSlot = new \ReservationSlot();

            // アプリケーションの存在確認
            $appData = $application->getById($applicationId);
            if (!$appData) {
                throw new \Exception('申し込みが見つかりません。');
            }

            // 既に確定済みかチェック
            if ($appData['status'] !== 'pending') {
                throw new \Exception('この申し込みは既に処理済みです。');
            }

            // 選択された日時の空き状況を確認
            if (!$reservationSlot->isSlotAvailable($date, $timeSlot)) {
                throw new \Exception('選択された日時は既に満席です。');
            }

            // このお客様の全ての希望日時を取得
            $preferredSlots = $applicationPreferredSlot->getByApplicationId($applicationId);

            // アプリケーションのステータスを確定済みに更新
            $updateResult = $application->updateStatus($applicationId, 'confirmed', $date, $timeSlot);
            if (!$updateResult) {
                throw new \Exception('申し込みステータスの更新に失敗しました。');
            }

            // 全ての希望日時のカウントを減らす
            foreach ($preferredSlots as $slot) {
                $reservationSlot->decrementBooking($slot['preferred_date'], $slot['time_slot']);
            }

            // このお客様の希望日時レコードを削除（もう不要）
            $deleteResult = $applicationPreferredSlot->deleteByApplicationId($applicationId);
            if (!$deleteResult) {
                throw new \Exception('希望日時レコードの削除に失敗しました。');
            }

            // 確定した日時を他の予約が入れないように無効化
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
}