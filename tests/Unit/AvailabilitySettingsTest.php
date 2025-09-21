<?php

namespace Tests\Unit;

require_once __DIR__ . '/../TestCase.php';
require_once __DIR__ . '/../../src/models/AvailabilitySettings.php';

use Tests\TestCase;

class AvailabilitySettingsTest extends TestCase
{
    private $availabilitySettings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilitySettings = new \AvailabilitySettings();
    }

    public function testGetWeeklySettings(): void
    {
        $settings = $this->availabilitySettings->getWeeklySettings();

        // 7日 × 3時間帯 = 21レコードが存在することを確認
        $this->assertCount(21, $settings);

        // 各曜日と時間帯の組み合わせが存在することを確認
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $timeSlots = ['morning', 'afternoon', 'evening'];

        foreach ($days as $day) {
            foreach ($timeSlots as $slot) {
                $found = false;
                foreach ($settings as $setting) {
                    if ($setting['day_of_week'] === $day && $setting['time_slot'] === $slot) {
                        $found = true;
                        break;
                    }
                }
                $this->assertTrue($found, "Setting for {$day} {$slot} not found");
            }
        }
    }

    public function testUpdateWeeklySetting(): void
    {
        // 月曜日の午前を無効にする
        $result = $this->availabilitySettings->updateWeeklySetting('monday', 'morning', false);
        $this->assertTrue($result);

        // 設定が更新されたことを確認
        $this->assertFalse($this->availabilitySettings->isWeeklySlotAvailable('monday', 'morning'));

        // 元に戻す
        $this->availabilitySettings->updateWeeklySetting('monday', 'morning', true);
        $this->assertTrue($this->availabilitySettings->isWeeklySlotAvailable('monday', 'morning'));
    }

    public function testDateOverrideFunctionality(): void
    {
        $testDate = '2024-12-25'; // クリスマス
        $timeSlot = 'morning';

        // 初期状態では基本設定が適用される
        $this->assertTrue($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // 特定日を無効にする
        $this->availabilitySettings->setDateOverride($testDate, $timeSlot, false, 'クリスマス休業', 'admin');

        // オーバーライドが適用されていることを確認
        $this->assertFalse($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // オーバーライド情報を取得して確認
        $override = $this->availabilitySettings->getDateOverride($testDate, $timeSlot);
        $this->assertNotNull($override);
        $this->assertFalse((bool)$override['is_available']);
        $this->assertEquals('クリスマス休業', $override['reason']);
        $this->assertEquals('admin', $override['created_by']);

        // オーバーライドを削除
        $this->availabilitySettings->removeDateOverride($testDate, $timeSlot);

        // 基本設定に戻ったことを確認
        $this->assertTrue($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));
        $this->assertFalse($this->availabilitySettings->getDateOverride($testDate, $timeSlot));
    }

    public function testCannotModifyConfirmedReservationSlot(): void
    {
        $testDate = '2024-01-15';
        $timeSlot = 'afternoon';

        // 確定済み予約を作成
        $this->createTestApplicationWithConfirmedReservation($testDate, $timeSlot);

        // 確定済み予約がある時間帯の変更を試行
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('確定済み予約があるため、この日時の設定は変更できません。');

        $this->availabilitySettings->setDateOverride($testDate, $timeSlot, false, 'テスト', 'admin');
    }

    public function testCannotRemoveOverrideWithConfirmedReservation(): void
    {
        $testDate = '2024-01-16';
        $timeSlot = 'evening';

        // 最初にオーバーライドを設定
        $this->availabilitySettings->setDateOverride($testDate, $timeSlot, false, 'テスト休業', 'admin');

        // 確定済み予約を作成
        $this->createTestApplicationWithConfirmedReservation($testDate, $timeSlot);

        // オーバーライドの削除を試行
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('確定済み予約があるため、この日時の設定は変更できません。');

        $this->availabilitySettings->removeDateOverride($testDate, $timeSlot);
    }

    public function testMonthlyOverrides(): void
    {
        $year = 2024;
        $month = 12;

        // 複数の日付でオーバーライドを設定
        $overrides = [
            ['2024-12-24', 'morning', false, 'クリスマスイブ'],
            ['2024-12-25', 'morning', false, 'クリスマス'],
            ['2024-12-25', 'afternoon', false, 'クリスマス'],
            ['2024-12-31', 'evening', false, '年末休業'],
        ];

        foreach ($overrides as $override) {
            $this->availabilitySettings->setDateOverride($override[0], $override[1], $override[2], $override[3], 'admin');
        }

        // 月間オーバーライドを取得
        $monthlyOverrides = $this->availabilitySettings->getMonthlyOverrides($year, $month);

        // 設定したオーバーライドが含まれていることを確認
        $this->assertGreaterThanOrEqual(4, count($monthlyOverrides));

        // 特定のオーバーライドが存在することを確認
        $foundChristmas = false;
        foreach ($monthlyOverrides as $override) {
            if ($override['date'] === '2024-12-25' && $override['time_slot'] === 'morning') {
                $foundChristmas = true;
                $this->assertFalse((bool)$override['is_available']);
                $this->assertEquals('クリスマス', $override['reason']);
                break;
            }
        }
        $this->assertTrue($foundChristmas, 'Christmas override not found');
    }

    public function testDayOfWeekConversion(): void
    {
        // 英語→日本語変換
        $this->assertEquals('月', $this->availabilitySettings->getDayOfWeekJp('monday'));
        $this->assertEquals('火', $this->availabilitySettings->getDayOfWeekJp('tuesday'));
        $this->assertEquals('日', $this->availabilitySettings->getDayOfWeekJp('sunday'));

        // 日付→英語曜日変換
        $this->assertEquals('monday', $this->availabilitySettings->getDayOfWeekEn('2024-01-15')); // 月曜日
        $this->assertEquals('friday', $this->availabilitySettings->getDayOfWeekEn('2024-01-19')); // 金曜日
    }

    public function testComplexAvailabilityLogic(): void
    {
        $testDate = '2024-02-14'; // バレンタインデー（水曜日）
        $timeSlot = 'afternoon';

        // 1. 基本設定では水曜日午後は利用可能
        $this->assertTrue($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // 2. 水曜日を全て無効にする
        $this->availabilitySettings->updateWeeklySetting('wednesday', $timeSlot, false);
        $this->assertFalse($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // 3. 特定日のオーバーライドで有効にする
        $this->availabilitySettings->setDateOverride($testDate, $timeSlot, true, 'バレンタイン特別営業', 'admin');
        $this->assertTrue($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // 4. 確定済み予約を追加
        $this->createTestApplicationWithConfirmedReservation($testDate, $timeSlot);
        $this->assertFalse($this->availabilitySettings->isDateTimeAvailable($testDate, $timeSlot));

        // クリーンアップ
        $this->availabilitySettings->updateWeeklySetting('wednesday', $timeSlot, true);
    }

    private function createTestApplicationWithConfirmedReservation($date, $timeSlot): int
    {
        // テストアプリケーションを作成
        $query = "INSERT INTO applications
                  (customer_name, customer_phone, customer_email, postal_code, address,
                   building_type, room_type, room_size, ac_type, status, confirmed_date, confirmed_time_slot)
                  VALUES
                  ('テスト太郎', '090-1234-5678', 'test@example.com', '123-4567', '東京都渋谷区',
                   'マンション', 'リビング', '10畳', '標準', 'confirmed', ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$date, $timeSlot]);

        return $this->conn->lastInsertId();
    }
}