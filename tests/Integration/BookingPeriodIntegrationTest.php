<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/BookingSettings.php';
require_once __DIR__ . '/../../src/config/database.php';

class BookingPeriodIntegrationTest extends TestCase
{
    private $bookingSettings;

    protected function setUp(): void
    {
        $this->bookingSettings = new BookingSettings();

        // テスト用の設定にリセット
        $this->bookingSettings->setBookingEnabled(true);
        $this->bookingSettings->setBookingAdvanceDays(30);
        $this->bookingSettings->setMinimumAdvanceHours(24);
    }

    public function testGetAvailableSlotsApiRespectsBookingPeriod()
    {
        // 設定を変更：7日先まで、48時間前までに制限
        $this->bookingSettings->setBookingAdvanceDays(7);
        $this->bookingSettings->setMinimumAdvanceHours(48);

        // 有効期間内の日付をテスト
        $validDate = date('Y-m-d', strtotime('+3 days'));
        $response = $this->callGetAvailableSlotsApi($validDate);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('slots', $response);

        // 期間外（早すぎる）の日付をテスト
        $tooEarlyDate = date('Y-m-d', strtotime('+1 day'));
        $response = $this->callGetAvailableSlotsApi($tooEarlyDate);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('予約受付期間外', $response['message']);

        // 期間外（遅すぎる）の日付をテスト
        $tooLateDate = date('Y-m-d', strtotime('+10 days'));
        $response = $this->callGetAvailableSlotsApi($tooLateDate);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('予約受付期間外', $response['message']);
    }

    public function testFormProcessingRespectsBookingPeriod()
    {
        // 設定を変更：7日先まで、48時間前までに制限
        $this->bookingSettings->setBookingAdvanceDays(7);
        $this->bookingSettings->setMinimumAdvanceHours(48);

        // 有効期間内の申し込みをテスト
        $validDate = date('Y-m-d', strtotime('+3 days'));
        $response = $this->submitApplication($validDate);
        $this->assertStringNotContainsString('予約受付期間外', $response);

        // 期間外の申し込みをテスト（早すぎる）
        $tooEarlyDate = date('Y-m-d', strtotime('+1 day'));
        $response = $this->submitApplication($tooEarlyDate);
        $this->assertStringContainsString('予約受付期間外', $response);

        // 期間外の申し込みをテスト（遅すぎる）
        $tooLateDate = date('Y-m-d', strtotime('+10 days'));
        $response = $this->submitApplication($tooLateDate);
        $this->assertStringContainsString('予約受付期間外', $response);
    }

    public function testBookingDisabledPreventsAllBookings()
    {
        // 予約受付を無効化
        $this->bookingSettings->setBookingEnabled(false);

        $validDate = date('Y-m-d', strtotime('+3 days'));

        // API呼び出し
        $response = $this->callGetAvailableSlotsApi($validDate);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('予約受付期間外', $response['message']);

        // フォーム送信
        $response = $this->submitApplication($validDate);
        $this->assertStringContainsString('予約受付期間外', $response);
    }

    public function testBookingPeriodDateRangeCalculation()
    {
        // 設定：14日先まで、72時間前まで
        $this->bookingSettings->setBookingAdvanceDays(14);
        $this->bookingSettings->setMinimumAdvanceHours(72);

        $range = $this->bookingSettings->getBookingDateRange();

        $this->assertTrue($range['enabled']);
        $this->assertNotNull($range['start_date']);
        $this->assertNotNull($range['end_date']);

        // 開始日が72時間後以降であることを確認
        $expectedStartDate = date('Y-m-d', strtotime('+72 hours'));
        $this->assertEquals($expectedStartDate, $range['start_date']);

        // 終了日が14日後であることを確認
        $expectedEndDate = date('Y-m-d', strtotime('+14 days'));
        $this->assertEquals($expectedEndDate, $range['end_date']);

        // 予約無効化時の動作確認
        $this->bookingSettings->setBookingEnabled(false);
        $disabledRange = $this->bookingSettings->getBookingDateRange();
        $this->assertFalse($disabledRange['enabled']);
        $this->assertNull($disabledRange['start_date']);
        $this->assertNull($disabledRange['end_date']);
    }

    private function callGetAvailableSlotsApi($date)
    {
        $url = 'http://localhost:8080/get_available_slots.php?date=' . urlencode($date);

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            $this->fail('API呼び出しに失敗しました: ' . $url);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail('JSONデコードに失敗しました: ' . $response);
        }

        return $decoded;
    }

    private function submitApplication($date)
    {
        $url = 'http://localhost:8080/process_form.php';

        $postData = http_build_query([
            'customer_name' => 'テスト太郎',
            'customer_phone' => '090-1234-5678',
            'customer_email' => 'test@example.com',
            'postal_code' => '123-4567',
            'address' => 'テスト住所',
            'building_type' => 'house',
            'room_type' => 'living',
            'room_size' => '8jo',
            'ac_type' => 'wall_mounted',
            'ac_capacity' => '2.8kw',
            'existing_ac' => 'no',
            'electrical_work' => 'outlet_addition',
            'piping_work' => 'new',
            'wall_drilling' => 'yes',
            'preferred_dates' => [$date],
            'preferred_times' => ['morning']
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

        if ($response === false) {
            $this->fail('フォーム送信に失敗しました');
        }

        return $response;
    }
}