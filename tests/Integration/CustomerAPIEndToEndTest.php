<?php

namespace Tests\Integration;

require_once __DIR__ . '/../TestCase.php';
require_once __DIR__ . '/../../src/models/AvailabilitySettings.php';

use Tests\TestCase;

class CustomerAPIEndToEndTest extends TestCase
{
    private $availabilitySettings;
    private $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilitySettings = new \AvailabilitySettings();
        $this->baseUrl = 'http://localhost:8080';
    }

    public function testGetAvailableSlotsHTTPEndpoint(): void
    {
        $testDate = '2025-09-27'; // 土曜日

        // 1. 土曜日午前を無効にする
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', false);

        // 2. HTTPリクエストを送信
        $response = $this->makeHttpRequest("GET", "/get_available_slots.php?date={$testDate}");

        // 3. レスポンスを検証
        $this->assertNotNull($response, 'HTTP response should not be null');

        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        $this->assertTrue($data['success'], 'API should return success');
        $this->assertEquals($testDate, $data['date']);

        // 4. 午前の時間帯が無効になっていることを確認
        $morningSlot = null;
        foreach ($data['slots'] as $slot) {
            if ($slot['time_slot'] === 'morning') {
                $morningSlot = $slot;
                break;
            }
        }

        $this->assertNotNull($morningSlot, 'Morning slot should exist in response');
        $this->assertFalse($morningSlot['available'], 'Morning slot should be unavailable');
        $this->assertTrue($morningSlot['admin_disabled'], 'Morning slot should be admin disabled');

        // クリーンアップ
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', true);
    }

    public function testProcessFormHTTPEndpointRejectsDisabledSlots(): void
    {
        $testDate = '2025-09-27'; // 土曜日

        // 1. 土曜日午前を無効にする
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', false);

        // 2. 申し込みデータを準備
        $postData = [
            'customer_name' => 'HTTPテスト太郎',
            'customer_phone' => '090-1234-5678',
            'customer_email' => 'httptest@example.com',
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

        // 3. HTTPリクエストを送信
        $response = $this->makeHttpRequest("POST", "/process_form.php", $postData);

        // 4. エラーレスポンスを検証
        $this->assertNotNull($response, 'HTTP response should not be null');
        $this->assertStringContains('エラーが発生しました', $response, 'Response should contain error message');
        $this->assertStringContains('現在受付を停止しています', $response, 'Response should contain specific error about disabled slot');

        // クリーンアップ
        $this->availabilitySettings->updateWeeklySetting('saturday', 'morning', true);
    }

    public function testProcessFormHTTPEndpointAcceptsAvailableSlots(): void
    {
        $testDate = '2025-09-23'; // 月曜日

        // 1. 月曜日午前が有効であることを確認
        $this->availabilitySettings->updateWeeklySetting('monday', 'morning', true);

        // 2. 申し込みデータを準備
        $postData = [
            'customer_name' => 'HTTPテスト花子',
            'customer_phone' => '090-9876-5432',
            'customer_email' => 'httphanako@example.com',
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

        // 3. HTTPリクエストを送信
        $response = $this->makeHttpRequest("POST", "/process_form.php", $postData);

        // 4. 成功レスポンスを検証
        $this->assertNotNull($response, 'HTTP response should not be null');
        $this->assertStringNotContains('エラーが発生しました', $response, 'Response should not contain error');
        $this->assertStringContains('申し込みが正常に受け付けられました', $response, 'Response should contain success message');
        $this->assertStringContains('申し込み番号:', $response, 'Response should contain application ID');
    }

    public function testDateSpecificOverrideViaHTTP(): void
    {
        $testDate = '2025-09-24'; // 水曜日

        // 1. 特定日をオーバーライドして無効にする
        $this->availabilitySettings->updateWeeklySetting('wednesday', 'morning', true);
        $this->availabilitySettings->setDateOverride($testDate, 'morning', false, 'HTTPテスト用臨時休業', 'test_admin');

        // 2. APIで無効になっていることを確認
        $response = $this->makeHttpRequest("GET", "/get_available_slots.php?date={$testDate}");
        $data = json_decode($response, true);

        $morningSlot = null;
        foreach ($data['slots'] as $slot) {
            if ($slot['time_slot'] === 'morning') {
                $morningSlot = $slot;
                break;
            }
        }

        $this->assertFalse($morningSlot['available'], 'Morning slot should be disabled by override');
        $this->assertTrue($morningSlot['admin_disabled'], 'Should be marked as admin disabled');

        // 3. 申し込みが拒否されることを確認
        $postData = [
            'customer_name' => 'HTTPオーバーライドテスト',
            'customer_phone' => '090-7777-8888',
            'customer_email' => 'override@example.com',
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

        $response = $this->makeHttpRequest("POST", "/process_form.php", $postData);
        $this->assertStringContains('現在受付を停止しています', $response, 'Should reject booking for overridden slot');

        // クリーンアップ
        $this->availabilitySettings->removeDateOverride($testDate, 'morning');
    }

    public function testBulkAvailabilityChangeViaHTTP(): void
    {
        // 1. 管理画面での一括設定変更をシミュレート
        $bulkSettingsData = [
            'action' => 'update_weekly_settings',
            'weekly_settings' => [
                'monday' => ['morning' => '0', 'afternoon' => '1', 'evening' => '1'],
                'tuesday' => ['morning' => '1', 'afternoon' => '0', 'evening' => '1'],
                'wednesday' => ['morning' => '1', 'afternoon' => '1', 'evening' => '0'],
                'thursday' => ['morning' => '1', 'afternoon' => '1', 'evening' => '1'],
                'friday' => ['morning' => '1', 'afternoon' => '1', 'evening' => '1'],
                'saturday' => ['morning' => '0', 'afternoon' => '0', 'evening' => '0'],
                'sunday' => ['morning' => '0', 'afternoon' => '0', 'evening' => '0']
            ]
        ];

        // 2. 管理画面で設定を変更
        $adminResponse = $this->makeHttpRequest("POST", "/admin/availability_settings.php", $bulkSettingsData);
        $this->assertStringContains('基本設定を更新しました', $adminResponse, 'Admin settings should be updated');

        // 3. 各曜日のAPIレスポンスを確認
        $testCases = [
            ['2025-09-22', 'morning', false], // 月曜日午前（無効）
            ['2025-09-22', 'afternoon', true], // 月曜日午後（有効）
            ['2025-09-23', 'morning', true],  // 火曜日午前（有効）
            ['2025-09-23', 'afternoon', false], // 火曜日午後（無効）
            ['2025-09-24', 'evening', false], // 水曜日夕方（無効）
            ['2025-09-27', 'morning', false], // 土曜日午前（無効）
        ];

        foreach ($testCases as [$date, $timeSlot, $expectedAvailable]) {
            $response = $this->makeHttpRequest("GET", "/get_available_slots.php?date={$date}");
            $data = json_decode($response, true);

            $targetSlot = null;
            foreach ($data['slots'] as $slot) {
                if ($slot['time_slot'] === $timeSlot) {
                    $targetSlot = $slot;
                    break;
                }
            }

            $this->assertNotNull($targetSlot, "Slot {$timeSlot} should exist for {$date}");
            $this->assertEquals($expectedAvailable, $targetSlot['available'],
                "Slot {$timeSlot} on {$date} should be " . ($expectedAvailable ? 'available' : 'unavailable'));
        }

        // 4. デフォルト設定に戻す
        $this->resetToDefaultSettings();
    }

    /**
     * HTTPリクエストを送信（cURLのシミュレート）
     */
    private function makeHttpRequest($method, $path, $data = null): ?string
    {
        // 実際のテスト環境では、以下のような実装になります：
        // - cURLを使用してHTTPリクエストを送信
        // - または、Webサーバーが起動していることを前提とした統合テスト

        // ここでは、PHPの直接実行でシミュレートします
        if ($method === 'GET') {
            // GETリクエストのシミュレート
            $_GET = [];
            if (strpos($path, '?') !== false) {
                [$path, $queryString] = explode('?', $path, 2);
                parse_str($queryString, $_GET);
            }
            $_POST = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
        } elseif ($method === 'POST') {
            // POSTリクエストのシミュレート
            $_GET = [];
            $_POST = $data ?: [];
            $_SERVER['REQUEST_METHOD'] = 'POST';
        }

        // アウトプットバッファリングを使用してレスポンスをキャプチャ
        ob_start();

        try {
            // src/ディレクトリに移動してファイルを実行
            $originalDir = getcwd();
            chdir(__DIR__ . '/../../src');

            if ($path === '/get_available_slots.php') {
                include 'get_available_slots.php';
            } elseif ($path === '/process_form.php') {
                include 'process_form.php';
            } elseif ($path === '/admin/availability_settings.php') {
                include 'admin/availability_settings.php';
            }

            chdir($originalDir);
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * デフォルト設定に戻す
     */
    private function resetToDefaultSettings(): void
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $weekends = ['saturday', 'sunday'];
        $timeSlots = ['morning', 'afternoon', 'evening'];

        // 平日を有効に
        foreach ($days as $day) {
            foreach ($timeSlots as $slot) {
                $this->availabilitySettings->updateWeeklySetting($day, $slot, true);
            }
        }

        // 土日を無効に
        foreach ($weekends as $day) {
            foreach ($timeSlots as $slot) {
                $this->availabilitySettings->updateWeeklySetting($day, $slot, false);
            }
        }
    }

    /**
     * 文字列に指定の文字列が含まれているかチェック
     */
    private function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(strpos($haystack, $needle) !== false, $message . " (Looking for '{$needle}' in response)");
    }

    /**
     * 文字列に指定の文字列が含まれていないかチェック
     */
    private function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertFalse(strpos($haystack, $needle) !== false, $message . " (Should not contain '{$needle}' in response)");
    }
}