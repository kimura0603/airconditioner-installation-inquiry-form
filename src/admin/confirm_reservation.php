<?php
mb_internal_encoding('UTF-8');
header('Content-Type: application/json; charset=utf-8');

require_once '../models/Application.php';
require_once '../models/ApplicationPreferredSlot.php';
require_once '../models/ReservationSlot.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_POST['action']) || $_POST['action'] !== 'confirm_reservation') {
    echo json_encode(['success' => false, 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
    exit;
}

$applicationId = $_POST['application_id'] ?? null;
$confirmedDate = $_POST['confirmed_date'] ?? null;
$confirmedTimeSlot = $_POST['confirmed_time_slot'] ?? null;

if (!$applicationId || !$confirmedDate || !$confirmedTimeSlot) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    $application = new Application();
    $applicationPreferredSlot = new ApplicationPreferredSlot();
    $reservationSlot = new ReservationSlot();

    // アプリケーションの存在確認
    $appData = $application->getById($applicationId);
    if (!$appData) {
        throw new Exception('申し込みが見つかりません。');
    }

    // 既に確定済みかチェック
    if ($appData['status'] !== 'pending') {
        throw new Exception('この申し込みは既に処理済みです。');
    }

    // 選択された日時の空き状況を確認
    if (!$reservationSlot->isSlotAvailable($confirmedDate, $confirmedTimeSlot)) {
        throw new Exception('選択された日時は既に満席です。');
    }

    // このお客様の全ての希望日時を取得
    $preferredSlots = $applicationPreferredSlot->getByApplicationId($applicationId);

    // アプリケーションのステータスを確定済みに更新
    $updateResult = $application->updateStatus($applicationId, 'confirmed', $confirmedDate, $confirmedTimeSlot);
    if (!$updateResult) {
        throw new Exception('申し込みステータスの更新に失敗しました。');
    }

    // 全ての希望日時のカウントを減らす
    foreach ($preferredSlots as $slot) {
        $reservationSlot->decrementBooking($slot['preferred_date'], $slot['time_slot']);
    }

    // 確定した日時以外の希望日時を論理削除
    $softDeleteResult = $applicationPreferredSlot->softDeleteOtherPreferences($applicationId, $confirmedDate, $confirmedTimeSlot);
    if (!$softDeleteResult) {
        throw new Exception('他の希望日時の論理削除に失敗しました。');
    }

    // 確定した日時を他の予約が入れないように無効化
    $reservationSlot->setSlotAvailability($confirmedDate, $confirmedTimeSlot, false);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => '予約が確定されました。',
        'application_id' => $applicationId,
        'confirmed_date' => $confirmedDate,
        'confirmed_time_slot' => $confirmedTimeSlot
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>