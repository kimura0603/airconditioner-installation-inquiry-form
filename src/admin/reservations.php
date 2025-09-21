<?php
require_once '../models/Application.php';
require_once '../models/ApplicationPreferredSlot.php';
require_once '../models/ReservationSlot.php';
require_once '../models/AvailabilitySettings.php';

$application = new Application();
$applicationPreferredSlot = new ApplicationPreferredSlot();
$reservationSlot = new ReservationSlot();
$availabilitySettings = new AvailabilitySettings();

$action = $_GET['action'] ?? '';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// アクション処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'confirm_reservation') {
        $applicationId = $_POST['application_id'];
        $confirmedDate = $_POST['confirmed_date'];
        $confirmedTimeSlot = $_POST['confirmed_time_slot'];

        try {
            // トランザクション開始
            $database = new Database();
            $conn = $database->getConnection();
            $conn->beginTransaction();

            // 申し込みステータスを確定に変更
            $application->updateStatus($applicationId, 'confirmed', $confirmedDate, $confirmedTimeSlot);

            // 予約枠をインクリメント
            $reservationSlot->incrementBooking($confirmedDate, $confirmedTimeSlot);

            $conn->commit();
            $success_message = "予約を確定しました。";

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "予約確定中にエラーが発生しました: " . $e->getMessage();
        }
    } elseif ($action === 'cancel_reservation') {
        $applicationId = $_POST['application_id'];

        try {
            $database = new Database();
            $conn = $database->getConnection();
            $conn->beginTransaction();

            // 現在の申し込み情報を取得
            $app = $application->getById($applicationId);

            if ($app && $app['status'] === 'confirmed' && $app['confirmed_date'] && $app['confirmed_time_slot']) {
                // 予約枠をデクリメント
                $reservationSlot->decrementBooking($app['confirmed_date'], $app['confirmed_time_slot']);
            }

            // 申し込みステータスをキャンセルに変更
            $application->updateStatus($applicationId, 'cancelled');

            $conn->commit();
            $success_message = "予約をキャンセルしました。";

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "予約キャンセル中にエラーが発生しました: " . $e->getMessage();
        }
    } elseif ($action === 'set_date_availability') {
        $date = $_POST['date'] ?? '';
        $timeSlot = $_POST['time_slot'] ?? '';
        $isAvailable = $_POST['is_available'] ?? '';
        $reason = $_POST['reason'] ?? '';

        try {
            $availabilitySettings->setDateOverride($date, $timeSlot, $isAvailable === '1', $reason, 'admin');
            $success_message = "日時の予約可用性を更新しました。";
        } catch (Exception $e) {
            $error_message = "可用性の更新中にエラーが発生しました: " . $e->getMessage();
        }
    } elseif ($action === 'remove_date_override') {
        $date = $_POST['date'] ?? '';
        $timeSlot = $_POST['time_slot'] ?? '';

        try {
            $availabilitySettings->removeDateOverride($date, $timeSlot);
            $success_message = "日時設定をデフォルトに戻しました。";
        } catch (Exception $e) {
            $error_message = "設定リセット中にエラーが発生しました: " . $e->getMessage();
        }
    }
}

// データ取得
$statusFilter = $_GET['status'] ?? 'all';
if ($statusFilter === 'all') {
    $applications = $application->getAll();
} else {
    $applications = $application->getByStatus($statusFilter);
}
$calendarData = $reservationSlot->getMonthlyCalendar($year, $month);

// カレンダー整理
$calendar = [];
foreach ($calendarData as $slot) {
    $date = $slot['reservation_date'];
    if (!isset($calendar[$date])) {
        $calendar[$date] = [];
    }
    $calendar[$date][$slot['time_slot']] = $slot;
}

// 各日時の予約詳細を取得
$reservationDetails = [];
foreach ($applications as $app) {
    if ($app['status'] === 'confirmed' && $app['confirmed_date'] && $app['confirmed_time_slot']) {
        $key = $app['confirmed_date'] . '_' . $app['confirmed_time_slot'];
        if (!isset($reservationDetails[$key])) {
            $reservationDetails[$key] = [];
        }
        $reservationDetails[$key][] = $app;
    }

    // 申し込み中の予約も表示
    if ($app['status'] === 'pending') {
        $preferredSlots = $applicationPreferredSlot->getByApplicationId($app['id']);
        foreach ($preferredSlots as $slot) {
            $key = $slot['preferred_date'] . '_' . $slot['time_slot'];
            if (!isset($reservationDetails[$key])) {
                $reservationDetails[$key] = [];
            }
            $reservationDetails[$key][] = array_merge($app, ['is_pending' => true, 'priority' => $slot['priority']]);
        }
    }
}

function translateValue($value, $field) {
    $translations = [
        'building_type' => [
            'house' => '一戸建て',
            'apartment' => 'アパート・マンション',
            'office' => 'オフィス',
            'store' => '店舗'
        ],
        'room_type' => [
            'living' => 'リビング・居間',
            'bedroom' => '寝室',
            'kitchen' => 'キッチン・台所',
            'office' => '書斎・オフィス',
            'other' => 'その他'
        ],
        'status' => [
            'pending' => '申し込み中',
            'confirmed' => '確定',
            'cancelled' => 'キャンセル'
        ]
    ];

    return $translations[$field][$value] ?? $value;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予約管理システム - エアコン工事</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .management-container {
            max-width: 1400px;
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
        }

        .nav-tab.active {
            background: white;
            border-bottom: 2px solid white;
            margin-bottom: -2px;
            color: #007bff;
            font-weight: bold;
        }

        .calendar-container {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #dee2e6;
            border: 1px solid #dee2e6;
        }

        .calendar-header {
            background: #007bff;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }

        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 5px;
            position: relative;
        }

        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
        }

        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .time-slot {
            font-size: 11px;
            padding: 2px 4px;
            margin: 1px 0;
            border-radius: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .time-slot.available {
            background: #d4edda;
            color: #155724;
        }

        .time-slot.full {
            background: #f8d7da;
            color: #721c24;
        }

        .time-slot.disabled {
            background: #e9ecef;
            color: #6c757d;
        }

        .reservation-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
            padding: 3px 5px;
            margin: 1px 0;
            font-size: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .reservation-item.confirmed {
            background: linear-gradient(135deg, #28a745, #34ce57);
            border: 2px solid #1e7e34;
            color: white;
            font-weight: bold;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .reservation-item.pending {
            background: linear-gradient(135deg, #ffc107, #ffda44);
            border: 2px solid #e0a800;
            color: #212529;
            font-weight: bold;
            text-shadow: 0 1px 2px rgba(255,255,255,0.5);
        }

        .reservation-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .reservation-item.confirmed:hover {
            background: linear-gradient(135deg, #1e7e34, #28a745);
        }

        .reservation-item.pending:hover {
            background: linear-gradient(135deg, #e0a800, #ffc107);
        }

        .reservation-count {
            font-size: 9px;
            color: #666;
            margin-top: 2px;
        }

        .reservation-actions {
            display: flex;
            gap: 10px;
            margin: 10px 0;
        }

        .availability-btn {
            background: none;
            border: none;
            font-size: 12px;
            cursor: pointer;
            padding: 2px;
            border-radius: 3px;
            transition: background-color 0.2s;
        }

        .availability-btn:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        .reset-btn {
            background: none;
            border: none;
            font-size: 12px;
            cursor: pointer;
            padding: 2px;
            border-radius: 3px;
            transition: background-color 0.2s;
        }

        .reset-btn:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        /* カレンダー凡例スタイル */
        .calendar-legend {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .calendar-legend h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #333;
        }

        .legend-items {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #555;
        }

        .status-text {
            font-size: 8px;
            font-weight: bold;
            opacity: 0.9;
            padding: 1px 3px;
            border-radius: 2px;
        }

        .reservation-item.confirmed .status-text {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
        }

        .reservation-item.pending .status-text {
            background: rgba(0,0,0,0.1);
            color: #212529;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-confirm {
            background: #28a745;
            color: white;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-toggle {
            background: #6c757d;
            color: white;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .submit-btn.active {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            font-weight: bold;
            border: 2px solid rgba(255,255,255,0.8);
        }

        @media (max-width: 768px) {
            .calendar-container {
                grid-template-columns: 1fr;
            }

            .calendar-day {
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container management-container">
        <header>
            <h1>予約管理システム</h1>
            <p>エアコン工事の予約状況を管理できます。</p>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">ダッシュボード</a>
            <a href="?tab=calendar" class="nav-tab <?php echo ($_GET['tab'] ?? 'calendar') === 'calendar' ? 'active' : ''; ?>">カレンダー表示</a>
            <a href="?tab=list" class="nav-tab <?php echo ($_GET['tab'] ?? '') === 'list' ? 'active' : ''; ?>">申し込み一覧</a>
            <a href="availability_settings.php" class="nav-tab">基本設定</a>
            <a href="../index.php" class="nav-tab">申し込みフォーム</a>
        </div>

        <?php if (($_GET['tab'] ?? 'calendar') === 'calendar'): ?>
            <!-- カレンダー表示 -->
            <div class="form-actions">
                <a href="?tab=calendar&year=<?php echo $year; ?>&month=<?php echo $month-1; ?>" class="submit-btn" style="display: inline-block; text-decoration: none; background: #6c757d; padding: 8px 15px;">← 前月</a>
                <span style="margin: 0 20px; font-size: 18px; font-weight: bold;"><?php echo $year; ?>年<?php echo $month; ?>月</span>
                <a href="?tab=calendar&year=<?php echo $year; ?>&month=<?php echo $month+1; ?>" class="submit-btn" style="display: inline-block; text-decoration: none; background: #6c757d; padding: 8px 15px;">次月 →</a>
            </div>

            <!-- 凡例 -->
            <div class="calendar-legend">
                <h4>凡例</h4>
                <div class="legend-items">
                    <div class="legend-item">
                        <div class="reservation-item confirmed" style="display: inline-block; margin: 0 10px 0 0; min-width: 80px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span>✓ 田中</span>
                                <span class="status-text">確定</span>
                            </div>
                        </div>
                        確定済み予約
                    </div>
                    <div class="legend-item">
                        <div class="reservation-item pending" style="display: inline-block; margin: 0 10px 0 0; min-width: 80px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span>🕐 佐藤</span>
                                <span class="status-text">調整中</span>
                            </div>
                        </div>
                        調整中の申し込み
                    </div>
                </div>
            </div>

            <div class="calendar-container">
                <div class="calendar-header">日</div>
                <div class="calendar-header">月</div>
                <div class="calendar-header">火</div>
                <div class="calendar-header">水</div>
                <div class="calendar-header">木</div>
                <div class="calendar-header">金</div>
                <div class="calendar-header">土</div>

                <?php
                $firstDay = date('Y-m-01', strtotime("$year-$month-01"));
                $lastDay = date('Y-m-t', strtotime("$year-$month-01"));
                $startDate = date('Y-m-d', strtotime($firstDay . ' -' . date('w', strtotime($firstDay)) . ' days'));
                $endDate = date('Y-m-d', strtotime($lastDay . ' +' . (6 - date('w', strtotime($lastDay))) . ' days'));

                $currentDate = $startDate;
                while ($currentDate <= $endDate):
                    $dayOfMonth = date('j', strtotime($currentDate));
                    $isCurrentMonth = date('Y-m', strtotime($currentDate)) === sprintf('%04d-%02d', $year, $month);
                    $daySlots = $calendar[$currentDate] ?? [];
                ?>
                    <div class="calendar-day <?php echo !$isCurrentMonth ? 'other-month' : ''; ?>">
                        <div class="day-number"><?php echo $dayOfMonth; ?></div>

                        <?php foreach (['morning', 'afternoon', 'evening'] as $slot): ?>
                            <?php
                            $slotData = $daySlots[$slot] ?? null;
                            $status = 'available';
                            $text = '';

                            if ($slotData) {
                                if (!$slotData['is_available']) {
                                    $status = 'disabled';
                                    $text = '無効';
                                } elseif ($slotData['current_bookings'] >= $slotData['max_capacity']) {
                                    $status = 'full';
                                    $text = '満席';
                                } else {
                                    $remaining = $slotData['max_capacity'] - $slotData['current_bookings'];
                                    $text = "残{$remaining}";
                                }
                            } else {
                                $text = '空き';
                            }

                            $slotNames = ['morning' => '午前', 'afternoon' => '午後', 'evening' => '夕方'];
                            $reservationKey = $currentDate . '_' . $slot;
                            $dayReservations = $reservationDetails[$reservationKey] ?? [];

                            // 可用性チェック
                            $isDateTimeAvailable = $availabilitySettings->isDateTimeAvailable($currentDate, $slot);
                            $hasOverride = $availabilitySettings->getDateOverride($currentDate, $slot);
                            $hasConfirmedReservation = false;

                            foreach ($dayReservations as $reservation) {
                                if (!isset($reservation['is_pending'])) {
                                    $hasConfirmedReservation = true;
                                    break;
                                }
                            }
                            ?>
                            <div class="time-slot <?php echo $status; ?>" style="position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                    <span><?php echo $slotNames[$slot]; ?></span>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <span><?php echo $text; ?></span>
                                        <?php if ($isCurrentMonth): ?>
                                            <div class="availability-control" style="display: flex; gap: 2px;">
                                                <?php if (!$hasConfirmedReservation): ?>
                                                    <button onclick="toggleAvailability('<?php echo $currentDate; ?>', '<?php echo $slot; ?>', <?php echo $isDateTimeAvailable ? 'false' : 'true'; ?>)"
                                                            class="availability-btn <?php echo $isDateTimeAvailable ? 'enabled' : 'disabled'; ?>"
                                                            title="<?php echo $isDateTimeAvailable ? '受付可能を無効にする' : '受付可能にする'; ?>">
                                                        <?php echo $isDateTimeAvailable ? '🟢' : '🔴'; ?>
                                                    </button>
                                                <?php else: ?>
                                                    <span title="確定済み予約があるため変更不可" style="opacity: 0.5;">🔒</span>
                                                <?php endif; ?>
                                                <?php if ($hasOverride): ?>
                                                    <button onclick="removeOverride('<?php echo $currentDate; ?>', '<?php echo $slot; ?>')"
                                                            class="reset-btn"
                                                            title="基本設定に戻す">
                                                        ↩️
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($dayReservations)): ?>
                                    <?php foreach (array_slice($dayReservations, 0, 2) as $reservation): ?>
                                        <div class="reservation-item <?php echo isset($reservation['is_pending']) ? 'pending' : 'confirmed'; ?>"
                                             onclick="showReservationDetail(<?php echo htmlspecialchars(json_encode($reservation)); ?>)"
                                             title="<?php echo isset($reservation['is_pending']) ? '調整中の申し込み' : '確定済み予約'; ?>">
                                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                                <span>
                                                    <?php if (isset($reservation['is_pending'])): ?>
                                                        🕐
                                                    <?php else: ?>
                                                        ✓
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars(mb_substr($reservation['customer_name'], 0, 4)); ?>
                                                    <?php if (mb_strlen($reservation['customer_name']) > 4): ?>...<?php endif; ?>
                                                </span>
                                                <span class="status-text">
                                                    <?php echo isset($reservation['is_pending']) ? '調整中' : '確定'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (count($dayReservations) > 2): ?>
                                        <div class="reservation-count">
                                            他<?php echo count($dayReservations) - 2; ?>件
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php
                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                endwhile;
                ?>
            </div>

        <?php else: ?>
            <!-- 申し込み一覧 -->
            <div class="form-actions">
                <a href="?tab=list&status=all" class="submit-btn btn-small <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">全て</a>
                <a href="?tab=list&status=pending" class="submit-btn btn-small <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" style="background: <?php echo $statusFilter === 'pending' ? '#e0a800' : '#ffc107'; ?>;">申し込み中</a>
                <a href="?tab=list&status=confirmed" class="submit-btn btn-small <?php echo $statusFilter === 'confirmed' ? 'active' : ''; ?>" style="background: <?php echo $statusFilter === 'confirmed' ? '#1e7e34' : '#28a745'; ?>;">確定済み</a>
                <a href="?tab=list&status=cancelled" class="submit-btn btn-small <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>" style="background: <?php echo $statusFilter === 'cancelled' ? '#c82333' : '#dc3545'; ?>;">キャンセル</a>
            </div>

            <?php foreach ($applications as $app): ?>
                <?php $preferredSlots = $applicationPreferredSlot->getByApplicationId($app['id']); ?>
                <div class="application-card" data-status="<?php echo $app['status']; ?>">
                    <div class="application-header">
                        <div>
                            <span class="application-id">申し込み #<?php echo $app['id']; ?></span>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php echo translateValue($app['status'], 'status'); ?>
                            </span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="application-date"><?php echo date('Y年m月d日 H:i', strtotime($app['created_at'])); ?></div>
                            <a href="application_detail.php?id=<?php echo $app['id']; ?>"
                               style="background: rgba(255,255,255,0.2); color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); transition: all 0.3s;"
                               onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                               onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                                📋 詳細表示
                            </a>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-section">
                            <h4>お客様情報</h4>
                            <div class="info-item">
                                <span class="info-label">お名前:</span>
                                <span class="info-value"><?php echo htmlspecialchars($app['customer_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">電話番号:</span>
                                <span class="info-value"><?php echo htmlspecialchars($app['customer_phone']); ?></span>
                            </div>
                        </div>

                        <div class="info-section">
                            <h4>希望日時</h4>
                            <?php foreach ($preferredSlots as $index => $slot): ?>
                                <div class="info-item">
                                    <span class="info-label">第<?php echo $index + 1; ?>希望:</span>
                                    <span class="info-value">
                                        <?php echo date('Y年m月d日', strtotime($slot['preferred_date'])); ?>
                                        <?php echo htmlspecialchars($slot['display_name']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($app['status'] === 'confirmed' && $app['confirmed_date']): ?>
                                <div class="info-item">
                                    <span class="info-label">確定日時:</span>
                                    <span class="info-value" style="font-weight: bold; color: #28a745;">
                                        <?php echo date('Y年m月d日', strtotime($app['confirmed_date'])); ?>
                                        <?php
                                        $slotNames = ['morning' => '午前（9:00-12:00）', 'afternoon' => '午後（12:00-15:00）', 'evening' => '夕方（15:00-18:00）'];
                                        echo $slotNames[$app['confirmed_time_slot']];
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($app['status'] === 'pending' && !empty($preferredSlots)): ?>
                        <div class="reservation-actions">
                            <strong>予約確定:</strong>
                            <?php foreach ($preferredSlots as $slot): ?>
                                <?php
                                $isAvailable = $reservationSlot->isSlotAvailable($slot['preferred_date'], $slot['time_slot']);
                                ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="confirm_reservation">
                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="confirmed_date" value="<?php echo $slot['preferred_date']; ?>">
                                    <input type="hidden" name="confirmed_time_slot" value="<?php echo $slot['time_slot']; ?>">
                                    <button type="submit" class="btn-small btn-confirm"
                                            <?php echo !$isAvailable ? 'disabled title="満席"' : ''; ?>>
                                        <?php echo date('m/d', strtotime($slot['preferred_date'])); ?>
                                        <?php echo htmlspecialchars($slot['display_name']); ?>
                                        <?php echo !$isAvailable ? '(満席)' : ''; ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($app['status'] !== 'cancelled'): ?>
                        <div class="reservation-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="cancel_reservation">
                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                <button type="submit" class="btn-small btn-cancel"
                                        onclick="return confirm('この申し込みをキャンセルしますか？')">
                                    キャンセル
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function filterApplications(status) {
            const cards = document.querySelectorAll('.application-card');
            cards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function showReservationDetail(reservation) {
            // 既存のモーダルを削除
            const existingModal = document.querySelector('.reservation-detail-modal');
            if (existingModal) {
                existingModal.remove();
            }

            // ステータス表示
            let statusText = '';
            let statusColor = '';
            if (reservation.is_pending) {
                statusText = '申し込み中（第' + reservation.priority + '希望）';
                statusColor = '#856404';
            } else if (reservation.status === 'confirmed') {
                statusText = '予約確定';
                statusColor = '#155724';
            } else if (reservation.status === 'cancelled') {
                statusText = 'キャンセル';
                statusColor = '#721c24';
            }

            // モーダル作成
            const modal = document.createElement('div');
            modal.className = 'reservation-detail-modal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;

            modal.innerHTML = `
                <div style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #dee2e6; background: #f8f9fa; border-radius: 8px 8px 0 0;">
                        <h3 style="margin: 0; color: #495057;">予約詳細</h3>
                        <button onclick="this.closest('.reservation-detail-modal').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6c757d;">&times;</button>
                    </div>
                    <div style="padding: 20px;">
                        <div style="margin-bottom: 15px;">
                            <strong style="color: ${statusColor};">${statusText}</strong>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #007bff;">お客様情報</h4>
                            <p><strong>お名前:</strong> ${reservation.customer_name || '不明'}</p>
                            <p><strong>電話番号:</strong> ${reservation.customer_phone || '不明'}</p>
                            ${reservation.customer_email ? `<p><strong>メール:</strong> ${reservation.customer_email}</p>` : ''}
                        </div>

                        <div style="margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #007bff;">設置場所</h4>
                            <p><strong>住所:</strong> ${reservation.postal_code || ''} ${reservation.address || '不明'}</p>
                            ${reservation.building_type ? `<p><strong>建物種別:</strong> ${reservation.building_type}</p>` : ''}
                        </div>

                        <div style="margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #007bff;">エアコン詳細</h4>
                            <p><strong>設置部屋:</strong> ${reservation.room_type || '不明'}</p>
                            <p><strong>部屋の広さ:</strong> ${reservation.room_size || '不明'}</p>
                            <p><strong>エアコン種類:</strong> ${reservation.ac_type || '不明'}</p>
                        </div>

                        ${reservation.special_requests ? `
                        <div style="margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #007bff;">特記事項</h4>
                            <p style="background: #f8f9fa; padding: 10px; border-radius: 4px;">${reservation.special_requests}</p>
                        </div>
                        ` : ''}

                        <div style="text-align: center; margin-top: 20px;">
                            <a href="application_detail.php?id=${reservation.id}" style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-right: 10px;">📋 詳細画面で確認</a>
                            <a href="?tab=list" style="background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-right: 10px;">申し込み一覧で確認</a>
                            <button onclick="this.closest('.reservation-detail-modal').remove()" style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">閉じる</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // モーダル外クリックで閉じる
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }

        function toggleAvailability(date, timeSlot, isAvailable) {
            if (!confirm('この日時の予約受付可能性を変更しますか？')) {
                return;
            }

            const reason = isAvailable ? '' : prompt('予約受付を停止する理由（任意）:') ?? '';

            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'set_date_availability';
            form.appendChild(actionInput);

            const dateInput = document.createElement('input');
            dateInput.name = 'date';
            dateInput.value = date;
            form.appendChild(dateInput);

            const timeSlotInput = document.createElement('input');
            timeSlotInput.name = 'time_slot';
            timeSlotInput.value = timeSlot;
            form.appendChild(timeSlotInput);

            const isAvailableInput = document.createElement('input');
            isAvailableInput.name = 'is_available';
            isAvailableInput.value = isAvailable ? '1' : '0';
            form.appendChild(isAvailableInput);

            const reasonInput = document.createElement('input');
            reasonInput.name = 'reason';
            reasonInput.value = reason;
            form.appendChild(reasonInput);

            document.body.appendChild(form);
            form.submit();
        }

        function removeOverride(date, timeSlot) {
            if (!confirm('この日時の設定を基本設定に戻しますか？')) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'remove_date_override';
            form.appendChild(actionInput);

            const dateInput = document.createElement('input');
            dateInput.name = 'date';
            dateInput.value = date;
            form.appendChild(dateInput);

            const timeSlotInput = document.createElement('input');
            timeSlotInput.name = 'time_slot';
            timeSlotInput.value = timeSlot;
            form.appendChild(timeSlotInput);

            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>