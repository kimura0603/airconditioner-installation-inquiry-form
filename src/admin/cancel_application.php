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

if (!isset($_POST['action']) || $_POST['action'] !== 'cancel_application') {
    echo json_encode(['success' => false, 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
    exit;
}

$applicationId = $_POST['application_id'] ?? null;

if (!$applicationId) {
    echo json_encode(['success' => false, 'message' => 'Missing application ID'], JSON_UNESCAPED_UNICODE);
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

    // 既にキャンセル済みかチェック
    if ($appData['status'] === 'cancelled') {
        throw new Exception('この申し込みは既にキャンセル済みです。');
    }

    // 確定済みの場合の処理
    if ($appData['status'] === 'confirmed') {
        // 確定済みの場合、確定した時間帯の利用可能性を復元
        if ($appData['confirmed_date'] && $appData['confirmed_time_slot']) {
            $reservationSlot->setSlotAvailability($appData['confirmed_date'], $appData['confirmed_time_slot'], true);
        }
    }

    // この申し込みの全ての希望日時を取得（削除済み含む）
    $allPreferredSlots = $applicationPreferredSlot->getAllByApplicationId($applicationId);

    // 各希望日時の予約カウントを減らす（削除済みでないもののみ）
    $activeSlots = $applicationPreferredSlot->getByApplicationId($applicationId);
    foreach ($activeSlots as $slot) {
        $reservationSlot->decrementBooking($slot['preferred_date'], $slot['time_slot']);
    }

    // 全ての希望日時を論理削除（キャンセル理由で）
    foreach ($allPreferredSlots as $slot) {
        if (empty($slot['deleted_at'])) {
            $applicationPreferredSlot->softDelete(
                $applicationId,
                $slot['preferred_date'],
                $slot['time_slot'],
                'cancelled'
            );
        }
    }

    // アプリケーションのステータスをキャンセルに更新
    $query = "UPDATE applications SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $applicationId);
    $updateResult = $stmt->execute();

    if (!$updateResult) {
        throw new Exception('申し込みステータスの更新に失敗しました。');
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => '申し込みをキャンセルしました。',
        'application_id' => $applicationId
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>