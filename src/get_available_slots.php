<?php
mb_internal_encoding('UTF-8');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/models/ReservationSlot.php';
require_once __DIR__ . '/models/AvailabilitySettings.php';
require_once __DIR__ . '/models/BookingSettings.php';

if (!isset($_GET['date'])) {
    echo json_encode(['success' => false, 'message' => 'Date parameter is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$date = $_GET['date'];

// 日付の妥当性チェック
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 過去の日付は選択不可
if (strtotime($date) < strtotime('today')) {
    echo json_encode(['success' => false, 'message' => 'Past dates are not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $reservationSlot = new ReservationSlot();
    $availabilitySettings = new AvailabilitySettings();
    $bookingSettings = new BookingSettings();

    // 期間チェック
    if (!$bookingSettings->isDateWithinBookingPeriod($date)) {
        echo json_encode([
            'success' => false,
            'message' => 'この日付は予約受付期間外です。',
            'booking_period' => $bookingSettings->getBookingDateRange()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 指定日のすべての時間帯を取得（満席も含む）
    $timeSlots = ['morning', 'afternoon', 'evening'];
    $availableSlots = [];

    foreach ($timeSlots as $slot) {
        $slotInfo = $reservationSlot->getSlotInfo($date, $slot);

        if ($slotInfo && $slotInfo['is_active']) {
            // 可用性設定をチェック
            $isDateTimeAvailable = $availabilitySettings->isDateTimeAvailable($date, $slot);

            // 最終的な利用可能性判定
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

    echo json_encode([
        'success' => true,
        'date' => $date,
        'slots' => $availableSlots
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>