<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/BookingSettings.php';
require_once __DIR__ . '/../../src/config/database.php';

class BookingSettingsTest extends TestCase
{
    private $bookingSettings;
    private $conn;

    protected function setUp(): void
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->bookingSettings = new BookingSettings();

        // テスト用のクリーンアップ
        $this->conn->exec("DELETE FROM booking_settings WHERE setting_key LIKE 'test_%'");

        // デフォルト値をリセット
        $this->conn->exec("UPDATE booking_settings SET setting_value = '30' WHERE setting_key = 'booking_advance_days'");
        $this->conn->exec("UPDATE booking_settings SET setting_value = '24' WHERE setting_key = 'booking_minimum_advance_hours'");
        $this->conn->exec("UPDATE booking_settings SET setting_value = '1' WHERE setting_key = 'booking_enabled'");
    }

    protected function tearDown(): void
    {
        // テストデータをクリーンアップ
        $this->conn->exec("DELETE FROM booking_settings WHERE setting_key LIKE 'test_%'");
    }

    public function testGetSetting()
    {
        // 存在する設定値の取得
        $result = $this->bookingSettings->getSetting('booking_advance_days');
        $this->assertEquals('30', $result);

        // 存在しない設定値の取得（デフォルト値あり）
        $result = $this->bookingSettings->getSetting('nonexistent_key', 'default_value');
        $this->assertEquals('default_value', $result);

        // 存在しない設定値の取得（デフォルト値なし）
        $result = $this->bookingSettings->getSetting('nonexistent_key');
        $this->assertNull($result);
    }

    public function testUpdateSetting()
    {
        $result = $this->bookingSettings->updateSetting('test_key', 'test_value', 'Test description');
        $this->assertTrue($result);

        // 設定値が正しく保存されているか確認
        $savedValue = $this->bookingSettings->getSetting('test_key');
        $this->assertEquals('test_value', $savedValue);

        // 同じキーで更新
        $result = $this->bookingSettings->updateSetting('test_key', 'updated_value');
        $this->assertTrue($result);

        $updatedValue = $this->bookingSettings->getSetting('test_key');
        $this->assertEquals('updated_value', $updatedValue);
    }

    public function testGetBookingAdvanceDays()
    {
        $days = $this->bookingSettings->getBookingAdvanceDays();
        $this->assertEquals(30, $days);
        $this->assertIsInt($days);
    }

    public function testSetBookingAdvanceDays()
    {
        // 正常な値の設定
        $result = $this->bookingSettings->setBookingAdvanceDays(45);
        $this->assertTrue($result);
        $this->assertEquals(45, $this->bookingSettings->getBookingAdvanceDays());

        // 境界値テスト
        $this->assertTrue($this->bookingSettings->setBookingAdvanceDays(1));
        $this->assertEquals(1, $this->bookingSettings->getBookingAdvanceDays());

        $this->assertTrue($this->bookingSettings->setBookingAdvanceDays(365));
        $this->assertEquals(365, $this->bookingSettings->getBookingAdvanceDays());

        // 異常値のテスト
        $this->expectException(Exception::class);
        $this->bookingSettings->setBookingAdvanceDays(0);
    }

    public function testSetBookingAdvanceDaysInvalidValues()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('予約受付期間は1日から365日の間で設定してください。');
        $this->bookingSettings->setBookingAdvanceDays(366);
    }

    public function testGetMinimumAdvanceHours()
    {
        $hours = $this->bookingSettings->getMinimumAdvanceHours();
        $this->assertEquals(24, $hours);
        $this->assertIsInt($hours);
    }

    public function testSetMinimumAdvanceHours()
    {
        // 正常な値の設定
        $result = $this->bookingSettings->setMinimumAdvanceHours(48);
        $this->assertTrue($result);
        $this->assertEquals(48, $this->bookingSettings->getMinimumAdvanceHours());

        // 境界値テスト
        $this->assertTrue($this->bookingSettings->setMinimumAdvanceHours(1));
        $this->assertEquals(1, $this->bookingSettings->getMinimumAdvanceHours());

        $this->assertTrue($this->bookingSettings->setMinimumAdvanceHours(168));
        $this->assertEquals(168, $this->bookingSettings->getMinimumAdvanceHours());
    }

    public function testSetMinimumAdvanceHoursInvalidValues()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('最低予約受付時間は1時間から168時間（1週間）の間で設定してください。');
        $this->bookingSettings->setMinimumAdvanceHours(0);
    }

    public function testIsBookingEnabled()
    {
        $this->assertTrue($this->bookingSettings->isBookingEnabled());

        // 無効に設定
        $this->bookingSettings->setBookingEnabled(false);
        $this->assertFalse($this->bookingSettings->isBookingEnabled());

        // 有効に戻す
        $this->bookingSettings->setBookingEnabled(true);
        $this->assertTrue($this->bookingSettings->isBookingEnabled());
    }

    public function testSetBookingEnabled()
    {
        // Boolean値での設定
        $result = $this->bookingSettings->setBookingEnabled(false);
        $this->assertTrue($result);
        $this->assertFalse($this->bookingSettings->isBookingEnabled());

        $result = $this->bookingSettings->setBookingEnabled(true);
        $this->assertTrue($result);
        $this->assertTrue($this->bookingSettings->isBookingEnabled());

        // 数値での設定（0, 1）
        $result = $this->bookingSettings->setBookingEnabled(0);
        $this->assertTrue($result);
        $this->assertFalse($this->bookingSettings->isBookingEnabled());

        $result = $this->bookingSettings->setBookingEnabled(1);
        $this->assertTrue($result);
        $this->assertTrue($this->bookingSettings->isBookingEnabled());
    }

    public function testIsDateWithinBookingPeriod()
    {
        // 予約受付が無効の場合
        $this->bookingSettings->setBookingEnabled(false);
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->assertFalse($this->bookingSettings->isDateWithinBookingPeriod($tomorrow));

        // 予約受付を有効にして再テスト
        $this->bookingSettings->setBookingEnabled(true);

        // 期間内の日付
        $validDate = date('Y-m-d', strtotime('+3 days'));
        $this->assertTrue($this->bookingSettings->isDateWithinBookingPeriod($validDate));

        // 最小時間未満（今日や明日など）
        $today = date('Y-m-d');
        $this->assertFalse($this->bookingSettings->isDateWithinBookingPeriod($today));

        // 最大期間を超える日付
        $farFuture = date('Y-m-d', strtotime('+40 days'));
        $this->assertFalse($this->bookingSettings->isDateWithinBookingPeriod($farFuture));

        // 境界値テスト
        $this->bookingSettings->setBookingAdvanceDays(7);
        $this->bookingSettings->setMinimumAdvanceHours(48);

        // 48時間後の日付（3日後）を取得
        $minValidDate = date('Y-m-d', strtotime('+3 days')); // 48時間 = 2日なので、3日後は確実に有効
        $maxDate = date('Y-m-d', strtotime('+7 days'));

        $this->assertTrue($this->bookingSettings->isDateWithinBookingPeriod($minValidDate));
        $this->assertTrue($this->bookingSettings->isDateWithinBookingPeriod($maxDate));

        $tooEarly = date('Y-m-d', strtotime('+1 day')); // 24時間後なので、48時間最低制限に引っかかる
        $tooLate = date('Y-m-d', strtotime('+8 days'));

        $this->assertFalse($this->bookingSettings->isDateWithinBookingPeriod($tooEarly));
        $this->assertFalse($this->bookingSettings->isDateWithinBookingPeriod($tooLate));
    }

    public function testGetBookingDateRange()
    {
        // 予約受付が無効の場合
        $this->bookingSettings->setBookingEnabled(false);
        $range = $this->bookingSettings->getBookingDateRange();
        $this->assertFalse($range['enabled']);
        $this->assertNull($range['start_date']);
        $this->assertNull($range['end_date']);

        // 予約受付が有効の場合
        $this->bookingSettings->setBookingEnabled(true);
        $this->bookingSettings->setMinimumAdvanceHours(24);
        $this->bookingSettings->setBookingAdvanceDays(30);

        $range = $this->bookingSettings->getBookingDateRange();
        $this->assertTrue($range['enabled']);
        $this->assertNotNull($range['start_date']);
        $this->assertNotNull($range['end_date']);

        // 日付形式の確認
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $range['start_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $range['end_date']);

        // 論理的な順序の確認
        $this->assertLessThanOrEqual(strtotime($range['end_date']), strtotime($range['start_date']));
    }

    public function testUpdateMultipleSettings()
    {
        $settings = [
            'booking_advance_days' => 60,
            'booking_minimum_advance_hours' => 48,
            'booking_enabled' => 0,
            'test_custom_setting' => 'custom_value'
        ];

        $result = $this->bookingSettings->updateMultipleSettings($settings);
        $this->assertTrue($result);

        // 設定値が正しく更新されているか確認
        $this->assertEquals(60, $this->bookingSettings->getBookingAdvanceDays());
        $this->assertEquals(48, $this->bookingSettings->getMinimumAdvanceHours());
        $this->assertFalse($this->bookingSettings->isBookingEnabled());
        $this->assertEquals('custom_value', $this->bookingSettings->getSetting('test_custom_setting'));
    }

    public function testUpdateMultipleSettingsWithInvalidValue()
    {
        $settings = [
            'booking_advance_days' => 500, // 無効な値
            'booking_minimum_advance_hours' => 48
        ];

        $this->expectException(Exception::class);
        $this->bookingSettings->updateMultipleSettings($settings);

        // ロールバックされているため、値は変更されていないはず
        $this->assertEquals(30, $this->bookingSettings->getBookingAdvanceDays());
    }

    public function testGetAllSettings()
    {
        $settings = $this->bookingSettings->getAllSettings();
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('booking_advance_days', $settings);
        $this->assertArrayHasKey('booking_minimum_advance_hours', $settings);
        $this->assertArrayHasKey('booking_enabled', $settings);

        // 設定項目の構造確認
        $advanceDaysSetting = $settings['booking_advance_days'];
        $this->assertArrayHasKey('setting_key', $advanceDaysSetting);
        $this->assertArrayHasKey('setting_value', $advanceDaysSetting);
        $this->assertArrayHasKey('setting_description', $advanceDaysSetting);
        $this->assertArrayHasKey('created_at', $advanceDaysSetting);
        $this->assertArrayHasKey('updated_at', $advanceDaysSetting);
    }
}