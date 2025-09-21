<?php

namespace Tests\Integration;

require_once __DIR__ . '/../TestCase.php';
require_once __DIR__ . '/../../src/models/AvailabilitySettings.php';
require_once __DIR__ . '/../../src/models/Application.php';
require_once __DIR__ . '/../../src/models/ReservationSlot.php';

use Tests\TestCase;

class AvailabilityControlWorkflowTest extends TestCase
{
    private $availabilitySettings;
    private $application;
    private $reservationSlot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilitySettings = new \AvailabilitySettings();
        $this->application = new \Application();
        $this->reservationSlot = new \ReservationSlot();
    }

    public function testBasicAvailabilityControlWorkflow(): void
    {
        $testDate = '2024-03-15';
        $timeSlot = 'morning';

        // 1. 初期状態確認：基本設定で利用可能
        $this->assertTrue($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // 2. 管理者が特定日を無効にする
        $this->availabilitySettings->setDateOverride($testDate, $timeSlot, false, 'システムメンテナンス', 'admin');
        $this->assertFalse($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // 3. 顧客が申し込みを試行（実際のAPIを使用）
        $availableSlots = $this->getAvailableSlotsViaAPI($testDate);
        $this->assertFalse($availableSlots[$timeSlot]['available']);

        // 4. 管理者が設定を有効に戻す
        $this->availabilitySettings->setDateOverride($testDate, $timeSlot, true, 'メンテナンス完了', 'admin');
        $this->assertTrue($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // 5. 顧客が申し込み可能になることを確認
        $availableSlots = $this->getAvailableSlotsViaAPI($testDate);
        $this->assertTrue($availableSlots[$timeSlot]['available']);
    }

    public function testAvailabilityControlWithExistingReservations(): void
    {
        $testDate = '2024-03-20';
        $timeSlot = 'afternoon';

        // 1. 申し込みを作成
        $applicationId = $this->createTestApplication([
            'preferred_dates' => [$testDate],
            'preferred_time_slots' => [$timeSlot]
        ]);

        // 2. 申し込みを確定
        $this->application->updateStatus($applicationId, 'confirmed', $testDate, $timeSlot);
        $this->reservationSlot->incrementBooking($testDate, $timeSlot);

        // 3. 確定済み予約がある状態での可用性変更を試行
        try {
            $this->availabilitySettings->setDateOverride($testDate, $timeSlot, false, 'テスト', 'admin');
            $this->fail('確定済み予約がある時間帯の変更が許可されてしまった');
        } catch (Exception $e) {
            $this->assertStringContains('確定済み予約があるため', $e->getMessage());
        }

        // 4. 確定済み予約がある状態でも可用性状態は正しく返される
        $this->assertFalse($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));
    }

    public function testWeeklySettingsImpactOnFutureReservations(): void
    {
        // 1. 金曜日の夕方を基本設定で無効にする
        $this->availabilitySettings->updateWeeklySetting('friday', 'evening', false);

        // 2. 今後の金曜日夕方が全て無効になることを確認
        $futureFridays = [
            '2024-03-22', // 金曜日
            '2024-03-29', // 金曜日
            '2024-04-05'  // 金曜日
        ];

        foreach ($futureFridays as $friday) {
            $this->assertFalse($this->availabilitySettings->isDateTimeAvailable($friday, 'evening'));

            // APIでも無効として返されることを確認
            $availableSlots = $this->getAvailableSlotsViaAPI($friday);
            $this->assertFalse($availableSlots['evening']['available']);
        }

        // 3. 特定の金曜日のみ有効にする
        $specialFriday = '2024-03-29';
        $this->availabilitySettings->setDateOverride($specialFriday, 'evening', true, '特別営業', 'admin');
        $this->assertTrue($this->availabilitySettings->isDateTimeAvailable($specialFriday, 'evening'));

        // 4. 他の金曜日は依然として無効
        $this->assertFalse($this->availabilitySettings->isDateTimeAvailable('2024-03-22', 'evening'));
        $this->assertFalse($this->availabilitySettings->isDateTimeAvailable('2024-04-05', 'evening'));

        // クリーンアップ
        $this->availabilitySettings->updateWeeklySetting('friday', 'evening', true);
    }

    public function testComplexReservationScenarioWithAvailabilityControl(): void
    {
        $popularDate = '2024-04-01'; // 人気の日
        $timeSlot = 'morning';

        // 1. 3件の申し込みを作成（同じ日時希望）
        $applicationIds = [];
        for ($i = 0; $i < 3; $i++) {
            $applicationIds[] = $this->createTestApplication([
                'customer_name' => "顧客{$i}",
                'preferred_dates' => [$popularDate],
                'preferred_time_slots' => [$timeSlot]
            ]);
        }

        // 2. 1件目を確定
        $this->application->updateStatus($applicationIds[0], 'confirmed', $popularDate, $timeSlot);
        $this->reservationSlot->incrementBooking($popularDate, $timeSlot);

        // 3. 管理者が当日を無効にしようと試行
        try {
            $this->availabilitySettings->setDateOverride($popularDate, $timeSlot, false, 'スタッフ不足', 'admin');
            $this->fail('確定済み予約がある時間帯の変更が許可されてしまった');
        } catch (Exception $e) {
            $this->assertStringContains('確定済み予約があるため', $e->getMessage());
        }

        // 4. 別の時間帯は変更可能
        $this->availabilitySettings->setDateOverride($popularDate, 'afternoon', false, 'スタッフ不足', 'admin');
        $this->assertFalse($this->availabilitySettings->isDateTimeAvailable($popularDate, 'afternoon'));

        // 5. 残りの申し込みは他の日時で確定可能
        $alternativeDate = '2024-04-02';
        $this->application->updateStatus($applicationIds[1], 'confirmed', $alternativeDate, $timeSlot);
        $this->reservationSlot->incrementBooking($alternativeDate, $timeSlot);

        // 6. 確定後の状態確認
        $confirmedApp1 = $this->application->getById($applicationIds[0]);
        $confirmedApp2 = $this->application->getById($applicationIds[1]);

        $this->assertEquals('confirmed', $confirmedApp1['status']);
        $this->assertEquals($popularDate, $confirmedApp1['confirmed_date']);

        $this->assertEquals('confirmed', $confirmedApp2['status']);
        $this->assertEquals($alternativeDate, $confirmedApp2['confirmed_date']);
    }

    public function testAvailabilityControlAPIIntegration(): void
    {
        $testDate = '2024-05-01';

        // 1. 基本状態の確認
        $availableSlots = $this->getAvailableSlotsViaAPI($testDate);
        $this->assertTrue($availableSlots['morning']['available']);
        $this->assertTrue($availableSlots['afternoon']['available']);
        $this->assertTrue($availableSlots['evening']['available']);

        // 2. 午前を無効にする
        $this->availabilitySettings->setDateOverride($testDate, 'morning', false, 'GW休業', 'admin');

        // 3. APIレスポンスに反映されることを確認
        $availableSlots = $this->getAvailableSlotsViaAPI($testDate);
        $this->assertFalse($availableSlots['morning']['available']);
        $this->assertTrue($availableSlots['afternoon']['available']);
        $this->assertTrue($availableSlots['evening']['available']);

        // 4. 午後に予約を入れる
        $applicationId = $this->createTestApplication([
            'preferred_dates' => [$testDate],
            'preferred_time_slots' => ['afternoon']
        ]);

        $this->application->updateStatus($applicationId, 'confirmed', $testDate, 'afternoon');
        $this->reservationSlot->incrementBooking($testDate, 'afternoon');

        // 5. 午後が満席として表示されることを確認
        $availableSlots = $this->getAvailableSlotsViaAPI($testDate);
        $this->assertFalse($availableSlots['morning']['available']); // 管理者設定で無効
        $this->assertFalse($availableSlots['afternoon']['available']); // 予約で満席
        $this->assertTrue($availableSlots['evening']['available']); // 利用可能
    }

    private function getAvailableSlotsViaAPI($date): array
    {
        // get_available_slots.phpの内容をシミュレート
        $reservationSlot = new \ReservationSlot();
        $availabilitySettings = new \AvailabilitySettings();

        $slots = $reservationSlot->getAvailableSlots($date);
        $result = [];

        foreach ($slots as $slot) {
            $timeSlot = $slot['time_slot'];
            $isAvailable = $availabilitySettings->isDateTimeAvailable($date, $timeSlot);
            $hasCapacity = $slot['current_bookings'] < $slot['max_capacity'];

            $result[$timeSlot] = [
                'available' => $isAvailable && $hasCapacity,
                'remaining_slots' => $hasCapacity ? ($slot['max_capacity'] - $slot['current_bookings']) : 0,
                'reason' => !$isAvailable ? 'disabled_by_admin' : (!$hasCapacity ? 'fully_booked' : 'available')
            ];
        }

        return $result;
    }

    private function createTestApplication($params = []): int
    {
        $defaults = [
            'customer_name' => 'テスト太郎',
            'customer_phone' => '090-1234-5678',
            'customer_email' => 'test@example.com',
            'postal_code' => '123-4567',
            'address' => '東京都渋谷区',
            'building_type' => 'マンション',
            'room_type' => 'リビング',
            'room_size' => '10畳',
            'ac_type' => '標準',
            'preferred_dates' => ['2024-03-15'],
            'preferred_time_slots' => ['morning']
        ];

        $data = array_merge($defaults, $params);

        // アプリケーションを作成
        $query = "INSERT INTO applications
                  (customer_name, customer_phone, customer_email, postal_code, address,
                   building_type, room_type, room_size, ac_type, status)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['customer_name'],
            $data['customer_phone'],
            $data['customer_email'],
            $data['postal_code'],
            $data['address'],
            $data['building_type'],
            $data['room_type'],
            $data['room_size'],
            $data['ac_type']
        ]);

        $applicationId = $this->conn->lastInsertId();

        // 希望日時を追加
        $slotQuery = "INSERT INTO application_preferred_slots (application_id, preferred_date, time_slot, priority)
                      VALUES (?, ?, ?, ?)";
        $slotStmt = $this->conn->prepare($slotQuery);

        $priority = 1;
        foreach ($data['preferred_dates'] as $date) {
            foreach ($data['preferred_time_slots'] as $timeSlot) {
                $slotStmt->execute([$applicationId, $date, $timeSlot, $priority]);
                $priority++;
            }
        }

        return $applicationId;
    }
}