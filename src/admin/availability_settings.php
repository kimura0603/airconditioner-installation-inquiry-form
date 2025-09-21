<?php
require_once '../models/AvailabilitySettings.php';
require_once '../models/BookingSettings.php';

$availabilitySettings = new AvailabilitySettings();
$bookingSettings = new BookingSettings();

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_weekly_settings') {
        $updates = $_POST['weekly_settings'] ?? [];
        $success = true;
        $errors = [];

        foreach ($updates as $dayOfWeek => $timeSlots) {
            foreach ($timeSlots as $timeSlot => $isAvailable) {
                try {
                    $availabilitySettings->updateWeeklySetting($dayOfWeek, $timeSlot, $isAvailable === '1');
                } catch (Exception $e) {
                    $success = false;
                    $errors[] = "エラー: {$dayOfWeek} {$timeSlot} - " . $e->getMessage();
                }
            }
        }

        if ($success) {
            $success_message = '基本設定を更新しました。';
        } else {
            $error_message = implode('<br>', $errors);
        }
    } elseif ($action === 'update_booking_settings') {
        try {
            $advanceDays = $_POST['booking_advance_days'] ?? 30;
            $minimumHours = $_POST['booking_minimum_advance_hours'] ?? 24;
            $bookingEnabled = isset($_POST['booking_enabled']) ? 1 : 0;

            $bookingSettings->updateMultipleSettings([
                'booking_advance_days' => $advanceDays,
                'booking_minimum_advance_hours' => $minimumHours,
                'booking_enabled' => $bookingEnabled
            ]);

            $success_message = '予約受付期間設定を更新しました。';
        } catch (Exception $e) {
            $error_message = '設定更新中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}

// 現在の設定を取得
$weeklySettings = $availabilitySettings->getWeeklySettings();

// 予約期間設定を取得
$currentBookingSettings = [
    'advance_days' => $bookingSettings->getBookingAdvanceDays(),
    'minimum_hours' => $bookingSettings->getMinimumAdvanceHours(),
    'enabled' => $bookingSettings->isBookingEnabled()
];

// 予約可能期間を計算
$dateRange = $bookingSettings->getBookingDateRange();

// 曜日別・時間帯別に整理
$settingsMatrix = [];
foreach ($weeklySettings as $setting) {
    $settingsMatrix[$setting['day_of_week']][$setting['time_slot']] = $setting['is_available'];
}

$dayNames = [
    'monday' => '月曜日',
    'tuesday' => '火曜日',
    'wednesday' => '水曜日',
    'thursday' => '木曜日',
    'friday' => '金曜日',
    'saturday' => '土曜日',
    'sunday' => '日曜日'
];

$timeSlotNames = [
    'morning' => '午前（9:00-12:00）',
    'afternoon' => '午後（12:00-15:00）',
    'evening' => '夕方（15:00-18:00）'
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予約可能時間設定 - エアコン工事</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .settings-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .nav-tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }

        .nav-tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-bottom: none;
            cursor: pointer;
            text-decoration: none;
            color: #495057;
            margin-right: 2px;
        }

        .nav-tab.active {
            background: white;
            border-bottom: 2px solid white;
            margin-bottom: -2px;
            color: #007bff;
            font-weight: bold;
        }

        .settings-section {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .settings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .settings-table th,
        .settings-table td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: center;
        }

        .settings-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .day-header {
            background: #e9ecef;
            font-weight: bold;
            text-align: left;
            padding-left: 15px;
        }

        .availability-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 24px;
            background: #ccc;
            border-radius: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .toggle-switch.active {
            background: #28a745;
        }

        .toggle-switch .slider {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }

        .toggle-switch.active .slider {
            transform: translateX(26px);
        }

        .status-text {
            font-size: 12px;
            font-weight: bold;
        }

        .status-available {
            color: #28a745;
        }

        .status-unavailable {
            color: #dc3545;
        }

        .form-actions {
            text-align: center;
            margin-top: 20px;
        }

        .submit-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background: #0056b3;
        }

        .help-text {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .bulk-actions {
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .bulk-actions h4 {
            margin: 0 0 15px 0;
            color: #495057;
        }

        .bulk-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .bulk-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .bulk-btn.enable-all {
            background: #28a745;
            color: white;
        }

        .bulk-btn.enable-all:hover {
            background: #218838;
        }

        .bulk-btn.disable-all {
            background: #dc3545;
            color: white;
        }

        .bulk-btn.disable-all:hover {
            background: #c82333;
        }

        .bulk-btn.enable-weekdays {
            background: #17a2b8;
            color: white;
        }

        .bulk-btn.enable-weekdays:hover {
            background: #138496;
        }

        .bulk-btn.disable-weekends {
            background: #fd7e14;
            color: white;
        }

        .bulk-btn.disable-weekends:hover {
            background: #e36209;
        }

        .bulk-btn.reset {
            background: #6c757d;
            color: white;
        }

        .bulk-btn.reset:hover {
            background: #5a6268;
        }

        /* 予約期間設定のスタイル */
        .booking-period-form {
            margin-bottom: 30px;
        }

        .period-settings {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .setting-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .setting-label {
            font-weight: bold;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-with-unit {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-with-unit input {
            width: 100px;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
        }

        .unit {
            color: #6c757d;
            font-size: 14px;
        }

        .setting-description {
            font-size: 12px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }

        .current-period-display {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 2px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .current-period-display.disabled {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border-color: #f44336;
        }

        .current-period-display h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }

        .current-period-display.disabled h4 {
            color: #d32f2f;
        }

        .period-range {
            font-size: 18px;
            font-weight: bold;
            color: #0d47a1;
            margin-bottom: 5px;
        }

        .period-separator {
            margin: 0 10px;
            color: #666;
        }

        .period-info {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <header>
            <h1>予約システム管理</h1>
            <p>予約可能時間の基本設定</p>
        </header>

        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">ダッシュボード</a>
            <a href="reservations.php" class="nav-tab">予約管理</a>
            <a href="reservations.php?tab=list" class="nav-tab">申し込み一覧</a>
            <a href="availability_settings.php" class="nav-tab active">基本設定</a>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="help-text">
            <strong>基本設定について：</strong><br>
            ここで設定した曜日・時間帯が、デフォルトの予約可能時間となります。<br>
            個別の日付については、カレンダー画面から個別に変更できます。<br>
            <strong>注意：</strong> 確定済み予約がある時間帯は変更できません。
        </div>

        <!-- 予約受付期間設定 -->
        <form method="POST" class="booking-period-form">
            <input type="hidden" name="action" value="update_booking_settings">

            <div class="settings-section">
                <h3>予約受付期間設定</h3>

                <div class="period-settings">
                    <div class="setting-group">
                        <label for="booking_enabled" class="setting-label">
                            <input type="checkbox" id="booking_enabled" name="booking_enabled"
                                   <?php echo $currentBookingSettings['enabled'] ? 'checked' : ''; ?>>
                            予約受付を有効にする
                        </label>
                    </div>

                    <div class="setting-group">
                        <label for="booking_advance_days" class="setting-label">
                            何日先まで予約受付可能にするか:
                        </label>
                        <div class="input-with-unit">
                            <input type="number" id="booking_advance_days" name="booking_advance_days"
                                   value="<?php echo $currentBookingSettings['advance_days']; ?>"
                                   min="1" max="365" required>
                            <span class="unit">日先まで</span>
                        </div>
                        <div class="setting-description">
                            現在の設定: <?php echo $currentBookingSettings['advance_days']; ?>日先まで
                            <?php if ($dateRange['enabled']): ?>
                                （<?php echo $dateRange['end_date']; ?>まで）
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="setting-group">
                        <label for="booking_minimum_advance_hours" class="setting-label">
                            最低何時間前まで予約受付するか:
                        </label>
                        <div class="input-with-unit">
                            <input type="number" id="booking_minimum_advance_hours" name="booking_minimum_advance_hours"
                                   value="<?php echo $currentBookingSettings['minimum_hours']; ?>"
                                   min="1" max="168" required>
                            <span class="unit">時間前まで</span>
                        </div>
                        <div class="setting-description">
                            現在の設定: <?php echo $currentBookingSettings['minimum_hours']; ?>時間前まで
                            <?php if ($dateRange['enabled']): ?>
                                （<?php echo $dateRange['start_date']; ?>以降）
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($dateRange['enabled']): ?>
                    <div class="current-period-display">
                        <h4>現在の予約受付期間</h4>
                        <div class="period-range">
                            <span class="period-start"><?php echo $dateRange['start_date']; ?></span>
                            <span class="period-separator">〜</span>
                            <span class="period-end"><?php echo $dateRange['end_date']; ?></span>
                        </div>
                        <div class="period-info">
                            （約<?php echo ceil((strtotime($dateRange['end_date']) - strtotime($dateRange['start_date'])) / 86400); ?>日間）
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="current-period-display disabled">
                        <h4>予約受付停止中</h4>
                        <p>予約受付が無効になっています。</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">期間設定を保存</button>
                </div>
            </div>
        </form>

        <div class="bulk-actions">
            <h4>一括設定</h4>
            <div class="bulk-buttons">
                <button type="button" onclick="toggleAllSlots(true)" class="bulk-btn enable-all">全て有効</button>
                <button type="button" onclick="toggleAllSlots(false)" class="bulk-btn disable-all">全て無効</button>
                <button type="button" onclick="toggleWeekdays(true)" class="bulk-btn enable-weekdays">平日有効</button>
                <button type="button" onclick="toggleWeekends(false)" class="bulk-btn disable-weekends">土日無効</button>
                <button type="button" onclick="resetToDefault()" class="bulk-btn reset">デフォルト設定</button>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update_weekly_settings">

            <div class="settings-section">
                <h3>曜日別予約可能時間設定</h3>

                <table class="settings-table">
                    <thead>
                        <tr>
                            <th style="width: 150px;">曜日</th>
                            <th>午前（9:00-12:00）</th>
                            <th>午後（12:00-15:00）</th>
                            <th>夕方（15:00-18:00）</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dayNames as $dayKey => $dayName): ?>
                        <tr>
                            <td class="day-header"><?php echo $dayName; ?></td>
                            <?php foreach (['morning', 'afternoon', 'evening'] as $timeSlot): ?>
                            <td>
                                <div class="availability-toggle">
                                    <?php
                                    $isAvailable = $settingsMatrix[$dayKey][$timeSlot] ?? false;
                                    $toggleId = "toggle_{$dayKey}_{$timeSlot}";
                                    ?>
                                    <div class="toggle-switch <?php echo $isAvailable ? 'active' : ''; ?>"
                                         onclick="toggleAvailability('<?php echo $dayKey; ?>', '<?php echo $timeSlot; ?>')">
                                        <div class="slider"></div>
                                    </div>
                                    <div class="status-text <?php echo $isAvailable ? 'status-available' : 'status-unavailable'; ?>"
                                         id="status_<?php echo $dayKey; ?>_<?php echo $timeSlot; ?>">
                                        <?php echo $isAvailable ? '受付可' : '受付不可'; ?>
                                    </div>
                                    <input type="hidden"
                                           name="weekly_settings[<?php echo $dayKey; ?>][<?php echo $timeSlot; ?>]"
                                           value="<?php echo $isAvailable ? '1' : '0'; ?>"
                                           id="input_<?php echo $dayKey; ?>_<?php echo $timeSlot; ?>">
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-btn">設定を保存</button>
            </div>
        </form>
    </div>

    <script>
        function toggleAvailability(dayOfWeek, timeSlot) {
            const toggleElement = document.querySelector(`.toggle-switch[onclick*="${dayOfWeek}"][onclick*="${timeSlot}"]`);
            const statusElement = document.getElementById(`status_${dayOfWeek}_${timeSlot}`);
            const inputElement = document.getElementById(`input_${dayOfWeek}_${timeSlot}`);

            const isCurrentlyActive = toggleElement.classList.contains('active');

            if (isCurrentlyActive) {
                // 現在有効 → 無効にする
                toggleElement.classList.remove('active');
                statusElement.textContent = '受付不可';
                statusElement.className = 'status-text status-unavailable';
                inputElement.value = '0';
            } else {
                // 現在無効 → 有効にする
                toggleElement.classList.add('active');
                statusElement.textContent = '受付可';
                statusElement.className = 'status-text status-available';
                inputElement.value = '1';
            }
        }

        function setSlotState(dayOfWeek, timeSlot, isAvailable) {
            const toggleElement = document.querySelector(`.toggle-switch[onclick*="${dayOfWeek}"][onclick*="${timeSlot}"]`);
            const statusElement = document.getElementById(`status_${dayOfWeek}_${timeSlot}`);
            const inputElement = document.getElementById(`input_${dayOfWeek}_${timeSlot}`);

            if (isAvailable) {
                toggleElement.classList.add('active');
                statusElement.textContent = '受付可';
                statusElement.className = 'status-text status-available';
                inputElement.value = '1';
            } else {
                toggleElement.classList.remove('active');
                statusElement.textContent = '受付不可';
                statusElement.className = 'status-text status-unavailable';
                inputElement.value = '0';
            }
        }

        function toggleAllSlots(isAvailable) {
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            const timeSlots = ['morning', 'afternoon', 'evening'];

            days.forEach(day => {
                timeSlots.forEach(slot => {
                    setSlotState(day, slot, isAvailable);
                });
            });
        }

        function toggleWeekdays(isAvailable) {
            const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            const timeSlots = ['morning', 'afternoon', 'evening'];

            weekdays.forEach(day => {
                timeSlots.forEach(slot => {
                    setSlotState(day, slot, isAvailable);
                });
            });
        }

        function toggleWeekends(isAvailable) {
            const weekends = ['saturday', 'sunday'];
            const timeSlots = ['morning', 'afternoon', 'evening'];

            weekends.forEach(day => {
                timeSlots.forEach(slot => {
                    setSlotState(day, slot, isAvailable);
                });
            });
        }

        function resetToDefault() {
            // デフォルト設定：平日は全て有効、土日は全て無効
            const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            const weekends = ['saturday', 'sunday'];
            const timeSlots = ['morning', 'afternoon', 'evening'];

            weekdays.forEach(day => {
                timeSlots.forEach(slot => {
                    setSlotState(day, slot, true);
                });
            });

            weekends.forEach(day => {
                timeSlots.forEach(slot => {
                    setSlotState(day, slot, false);
                });
            });
        }
    </script>
</body>
</html>