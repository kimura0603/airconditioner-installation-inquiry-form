<?php

namespace Tests\Integration;

require_once __DIR__ . '/../TestCase.php';
require_once __DIR__ . '/../../src/models/AvailabilitySettings.php';
require_once __DIR__ . '/../../src/models/Application.php';
require_once __DIR__ . '/../../src/models/ApplicationPreferredSlot.php';
require_once __DIR__ . '/../../src/models/ReservationSlot.php';

use Tests\TestCase;

class CustomerAvailabilityTest extends TestCase
{
    private $availabilitySettings;
    private $application;
    private $applicationPreferredSlot;
    private $reservationSlot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilitySettings = new \AvailabilitySettings();
        $this->application = new \Application();
        $this->applicationPreferredSlot = new \ApplicationPreferredSlot();
        $this->reservationSlot = new \ReservationSlot();
    }

    public function testGetAvailableSlotsAPIReflectsAvailabilitySettings(): void
    {
        $testDate = '2025-09-27'; // 土曜日

        // 1. 土曜日午前を無効にする
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', false);

        // 2. APIをテスト
        $apiResult = $this->callGetAvailableSlotsAPI($testDate);

        $this->assertTrue($apiResult['success']);
        $this->assertEquals($testDate, $apiResult['date']);

        // 3. 午前の時間帯が無効になっていることを確認
        $morningSlot = null;
        foreach ($apiResult['slots'] as $slot) {
            if ($slot['time_slot'] === 'morning') {
                $morningSlot = $slot;
                break;
            }
        }

        $this->assertNotNull($morningSlot, 'Morning slot should exist');
        $this->assertFalse($morningSlot['available'], 'Morning slot should be unavailable');
        $this->assertTrue($morningSlot['admin_disabled'], 'Morning slot should be marked as admin disabled');
        $this->assertEquals(0, $morningSlot['available_count'], 'Available count should be 0');

        // クリーンアップ
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', true);
    }

    public function testCustomerBookingRejectedForDisabledSlots(): void
    {
        $testDate = '2025-09-27'; // 土曜日

        // 1. 土曜日午前を無効にする
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', false);

        // 2. 申し込みデータを準備
        $formData = [
            'customer_name' => 'テスト太郎',
            'customer_phone' => '090-1234-5678',
            'customer_email' => 'test@example.com',
            'postal_code' => '123-4567',
            'address' => '東京都',
            'building_type' => 'house',
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'electrical_work' => 'none',
            'piping_work' => 'new',
            'wall_drilling' => 'no',
            'preferred_dates' => [$testDate],
            'preferred_times' => ['morning']
        ];

        // 3. 申し込み処理を実行して例外が発生することを確認
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('選択された時間帯（1番目）は現在受付を停止しています。');

        $this->processCustomerBooking($formData);

        // クリーンアップ
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', true);
    }

    public function testCustomerBookingSucceedsForAvailableSlots(): void
    {
        $testDate = '2025-09-23'; // 月曜日

        // 1. 月曜日午前が有効であることを確認
        $this->availabilitySettings->updateWeeklySetting('monday', 'morning', true);

        // 2. 申し込みデータを準備
        $formData = [
            'customer_name' => 'テスト花子',
            'customer_phone' => '090-9876-5432',
            'customer_email' => 'hanako@example.com',
            'postal_code' => '123-4567',
            'address' => '東京都',
            'building_type' => 'house',
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'electrical_work' => 'none',
            'piping_work' => 'new',
            'wall_drilling' => 'no',
            'preferred_dates' => [$testDate],
            'preferred_times' => ['morning']
        ];

        // 3. 申し込み処理を実行
        $applicationId = $this->processCustomerBooking($formData);

        // 4. 申し込みが正常に作成されたことを確認
        $this->assertIsInt($applicationId);
        $this->assertGreaterThan(0, $applicationId);

        // 5. 申し込み詳細を確認
        $applicationData = $this->application->getById($applicationId);
        $this->assertEquals('テスト花子', $applicationData['customer_name']);
        $this->assertEquals('pending', $applicationData['status']);

        // 6. 希望日時が正しく保存されていることを確認
        $preferredSlots = $this->applicationPreferredSlot->getByApplicationId($applicationId);
        $this->assertCount(1, $preferredSlots);
        $this->assertEquals($testDate, $preferredSlots[0]['preferred_date']);
        $this->assertEquals('morning', $preferredSlots[0]['time_slot']);
    }

    public function testMixedAvailabilityInWeeklySettings(): void
    {
        // 1. 週の設定を混在させる（火曜日午後のみ無効）
        $this->availabilitySettings->updateWeeklySetting('tuesday', 'morning', true);
        $this->availabilitySettings->updateWeeklySetting('tuesday', 'afternoon', false);
        $this->availabilitySettings->updateWeeklySetting('tuesday', 'evening', true);

        $testDate = '2025-09-23'; // 火曜日

        // 2. APIの結果を確認
        $apiResult = $this->callGetAvailableSlotsAPI($testDate);

        $slotAvailability = [];
        foreach ($apiResult['slots'] as $slot) {
            $slotAvailability[$slot['time_slot']] = $slot['available'];
        }

        // 3. 設定が正しく反映されていることを確認
        $this->assertTrue($slotAvailability['morning'], 'Morning should be available');
        $this->assertFalse($slotAvailability['afternoon'], 'Afternoon should be disabled');
        $this->assertTrue($slotAvailability['evening'], 'Evening should be available');

        // 4. 無効な時間帯での申し込みテスト
        $formData = [
            'customer_name' => 'テスト次郎',
            'customer_phone' => '090-1111-2222',
            'customer_email' => 'jiro@example.com',
            'postal_code' => '123-4567',
            'address' => '東京都',
            'building_type' => 'house',
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'electrical_work' => 'none',
            'piping_work' => 'new',
            'wall_drilling' => 'no',
            'preferred_dates' => [$testDate],
            'preferred_times' => ['afternoon']
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('選択された時間帯（1番目）は現在受付を停止しています。');

        $this->processCustomerBooking($formData);

        // クリーンアップ
        $this->availabilitySettings->updateWeeklySetting('tuesday', 'afternoon', true);
    }

    public function testDateSpecificOverrideAffectsCustomerBooking(): void
    {
        $testDate = '2025-09-24'; // 水曜日

        // 1. 水曜日は通常有効だが、特定日をオーバーライドして無効にする
        $this->availabilitySettings->updateWeeklySetting('wednesday', 'morning', true);
        $this->availabilitySettings->setDateOverride($testDate, 'morning', false, '臨時休業', 'admin');

        // 2. APIで無効になっていることを確認
        $apiResult = $this->callGetAvailableSlotsAPI($testDate);

        $morningSlot = null;
        foreach ($apiResult['slots'] as $slot) {
            if ($slot['time_slot'] === 'morning') {
                $morningSlot = $slot;
                break;
            }
        }

        $this->assertFalse($morningSlot['available'], 'Morning slot should be disabled by override');
        $this->assertTrue($morningSlot['admin_disabled'], 'Should be marked as admin disabled');

        // 3. 申し込み拒否を確認
        $formData = [
            'customer_name' => 'テスト三郎',
            'customer_phone' => '090-3333-4444',
            'customer_email' => 'saburo@example.com',
            'postal_code' => '123-4567',
            'address' => '東京都',
            'building_type' => 'house',
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'electrical_work' => 'none',
            'piping_work' => 'new',
            'wall_drilling' => 'no',
            'preferred_dates' => [$testDate],
            'preferred_times' => ['morning']
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('選択された時間帯（1番目）は現在受付を停止しています。');

        $this->processCustomerBooking($formData);

        // クリーンアップ
        $this->availabilitySettings->removeDateOverride($testDate, 'morning');
    }

    public function testMultiplePreferredSlotsWithMixedAvailability(): void
    {
        $testDate1 = '2025-09-22'; // 月曜日
        $testDate2 = '2025-09-27'; // 土曜日（無効）
        $testDate3 = '2025-09-24'; // 水曜日

        // 1. 土曜日を無効にする
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', false);

        // 2. 複数希望（2番目が無効）で申し込み
        $formData = [
            'customer_name' => 'テスト四郎',
            'customer_phone' => '090-4444-5555',
            'customer_email' => 'shiro@example.com',
            'postal_code' => '123-4567',
            'address' => '東京都',
            'building_type' => 'house',
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'electrical_work' => 'none',
            'piping_work' => 'new',
            'wall_drilling' => 'no',
            'preferred_dates' => [$testDate1, $testDate2, $testDate3],
            'preferred_times' => ['morning', 'morning', 'morning']
        ];

        // 3. 2番目の希望でエラーが発生することを確認
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('選択された時間帯（2番目）は現在受付を停止しています。');

        $this->processCustomerBooking($formData);

        // クリーンアップ
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', true);
    }

    /**
     * get_available_slots.php APIをシミュレート
     */
    private function callGetAvailableSlotsAPI($date): array
    {
        // get_available_slots.phpの処理をシミュレート
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['success' => false, 'message' => 'Invalid date format'];
        }

        if (strtotime($date) < strtotime('today')) {
            return ['success' => false, 'message' => 'Past dates are not allowed'];
        }

        $reservationSlot = new \ReservationSlot();
        $availabilitySettings = new \AvailabilitySettings();

        $timeSlots = ['morning', 'afternoon', 'evening'];
        $availableSlots = [];

        foreach ($timeSlots as $slot) {
            $slotInfo = $reservationSlot->getSlotInfo($date, $slot);

            if ($slotInfo && $slotInfo['is_active']) {
                $isDateTimeAvailable = $availabilitySettings->isDateTimeAvailable($date, $slot);

                $isAvailable = $isDateTimeAvailable &&
                              $slotInfo['is_available'] &&
                              $slotInfo['current_bookings'] < $slotInfo['max_capacity'];

                $availableSlots[] = [
                    'time_slot' => $slot,
                    'display_name' => $slotInfo['display_name'],
                    'start_time' => $slotInfo['start_time'],
                    'end_time' => $slotInfo['end_time'],
                    'max_capacity' => $slotInfo['max_capacity'],
                    'current_bookings' => $slotInfo['current_bookings'],
                    'available_count' => $isAvailable ? ($slotInfo['max_capacity'] - $slotInfo['current_bookings']) : 0,
                    'available' => $isAvailable,
                    'admin_disabled' => !$isDateTimeAvailable
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
     * process_form.php の申し込み処理をシミュレート
     */
    private function processCustomerBooking($data): int
    {
        $preferredDates = $data['preferred_dates'];
        $preferredTimes = $data['preferred_times'];

        // バリデーション（簡略化）
        if (empty($data['customer_name']) || empty($preferredDates[0]) || empty($preferredTimes[0])) {
            throw new \Exception("必須フィールドが不足しています。");
        }

        // トランザクション開始
        $database = new \Database();
        $conn = $database->getConnection();
        $conn->beginTransaction();

        try {
            // 申し込み作成
            $applicationId = $this->application->create($data);

            if ($applicationId) {
                // 希望日時を保存
                for ($i = 0; $i < count($preferredDates) && $i < 3; $i++) {
                    if (!empty($preferredDates[$i]) && !empty($preferredTimes[$i])) {
                        // 可用性設定を確認
                        if (!$this->availabilitySettings->isDateTimeAvailable($preferredDates[$i], $preferredTimes[$i])) {
                            throw new \Exception("選択された時間帯（" . ($i + 1) . "番目）は現在受付を停止しています。");
                        }

                        // 空き状況を再確認
                        if (!$this->reservationSlot->isSlotAvailable($preferredDates[$i], $preferredTimes[$i])) {
                            throw new \Exception("選択された時間帯（" . ($i + 1) . "番目）は既に満席です。");
                        }

                        $this->applicationPreferredSlot->create(
                            $applicationId,
                            $preferredDates[$i],
                            $preferredTimes[$i],
                            $i + 1
                        );

                        // 予約枠カウントをインクリメント
                        $this->reservationSlot->incrementBooking($preferredDates[$i], $preferredTimes[$i]);
                    }
                }

                $conn->commit();
                return $applicationId;
            } else {
                throw new \Exception("申し込み処理中にエラーが発生しました。");
            }
        } catch (\Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}