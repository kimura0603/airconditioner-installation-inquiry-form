<?php
require_once '../models/Application.php';
require_once '../models/ApplicationPreferredSlot.php';
require_once '../models/ReservationSlot.php';

$application = new Application();
$applicationPreferredSlot = new ApplicationPreferredSlot();
$reservationSlot = new ReservationSlot();

// URLパラメータから申し込みIDを取得
$applicationId = $_GET['id'] ?? null;

if (!$applicationId) {
    header('Location: reservations.php?tab=list');
    exit;
}

// 申し込み情報を取得
$app = $application->getById($applicationId);

if (!$app) {
    header('Location: reservations.php?tab=list');
    exit;
}

// 希望日時情報を取得
$preferredSlots = $applicationPreferredSlot->getByApplicationId($applicationId);
$allPreferredSlots = $applicationPreferredSlot->getAllByApplicationId($applicationId);

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
        'room_size' => [
            '6jo' => '6畳まで',
            '8jo' => '8畳まで',
            '10jo' => '10畳まで',
            '12jo' => '12畳まで',
            '14jo' => '14畳まで',
            '16jo' => '16畳まで',
            '18jo_over' => '18畳以上'
        ],
        'ac_type' => [
            'wall_mounted' => '壁掛け型',
            'ceiling_cassette' => '天井カセット型',
            'floor_standing' => '床置き型',
            'ceiling_concealed' => '天井埋込型'
        ],
        'ac_capacity' => [
            '2.2kw' => '2.2kW（〜6畳）',
            '2.5kw' => '2.5kW（〜8畳）',
            '2.8kw' => '2.8kW（〜10畳）',
            '3.6kw' => '3.6kW（〜12畳）',
            '4.0kw' => '4.0kW（〜14畳）',
            '5.6kw' => '5.6kW（〜18畳）',
            '6.3kw' => '6.3kW（〜20畳）'
        ],
        'electrical_work' => [
            'none' => '不要',
            'outlet_addition' => 'コンセント増設',
            'voltage_change' => '電圧変更',
            'circuit_addition' => '回路増設'
        ],
        'piping_work' => [
            'new' => '新規配管',
            'existing_reuse' => '既設配管再利用',
            'partial_replacement' => '一部配管交換'
        ],
        'yes_no' => [
            'yes' => 'はい',
            'no' => 'いいえ'
        ],
        'status' => [
            'pending' => '受付中',
            'confirmed' => '確定済み',
            'cancelled' => 'キャンセル',
            'completed' => '完了'
        ]
    ];

    if (isset($translations[$field][$value])) {
        return $translations[$field][$value];
    } elseif (in_array($field, ['existing_ac', 'existing_ac_removal', 'wall_drilling']) && isset($translations['yes_no'][$value])) {
        return $translations['yes_no'][$value];
    }

    return $value;
}

function getTimeSlotDisplayName($timeSlot) {
    $slotNames = [
        'morning' => '午前（9:00-12:00）',
        'afternoon' => '午後（12:00-15:00）',
        'evening' => '夕方（15:00-18:00）'
    ];
    return $slotNames[$timeSlot] ?? $timeSlot;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申し込み詳細 #<?php echo $app['id']; ?> - エアコン工事管理システム</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .detail-container {
            max-width: 1000px;
        }

        .detail-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .detail-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 15px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .navigation-buttons {
            margin-bottom: 20px;
        }

        .nav-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-right: 10px;
            transition: background-color 0.3s;
        }

        .nav-btn:hover {
            background: #5a6268;
        }

        .nav-btn.primary {
            background: #007bff;
        }

        .nav-btn.primary:hover {
            background: #0056b3;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .info-section {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .info-section h3 {
            color: #007bff;
            margin: 0 0 15px 0;
            font-size: 18px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }

        .info-item {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 15px;
            margin-bottom: 12px;
            align-items: start;
        }

        .info-label {
            font-weight: bold;
            color: #495057;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
        }

        .info-value {
            padding: 8px 0;
            color: #333;
            line-height: 1.4;
        }

        .preferred-slots-section {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .preferred-slots-section h3 {
            color: #007bff;
            margin: 0 0 15px 0;
            font-size: 18px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }

        .slot-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .slot-item.confirmed {
            background: #d4edda;
            border-color: #28a745;
        }

        .slot-item.deleted {
            background: #f8d7da;
            border-color: #dc3545;
            opacity: 0.7;
        }

        .slot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .slot-priority {
            font-weight: bold;
            color: #007bff;
        }

        .slot-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 10px;
            font-weight: bold;
        }

        .slot-status.active {
            background: #28a745;
            color: white;
        }

        .slot-status.deleted {
            background: #dc3545;
            color: white;
        }

        .slot-datetime {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .confirmed-section {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .confirmed-section h3 {
            color: #155724;
            margin: 0 0 15px 0;
            font-size: 20px;
        }

        .confirmed-datetime {
            font-size: 24px;
            font-weight: bold;
            color: #155724;
            text-align: center;
            padding: 15px;
            background: rgba(255,255,255,0.7);
            border-radius: 6px;
        }

        .action-buttons {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .action-buttons h3 {
            color: #007bff;
            margin: 0 0 15px 0;
            font-size: 18px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            min-width: 120px;
        }

        .btn-confirm {
            background: #28a745;
            color: white;
        }

        .btn-confirm:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-disabled {
            background: #6c757d;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .special-requests {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }

        .special-requests h4 {
            color: #856404;
            margin: 0 0 10px 0;
        }

        .special-requests p {
            margin: 0;
            line-height: 1.5;
            color: #333;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .info-item {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .header-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container detail-container">
        <div class="navigation-buttons">
            <a href="reservations.php?tab=list" class="nav-btn">← 申し込み一覧に戻る</a>
            <a href="reservations.php?tab=list" class="nav-btn">申し込み管理</a>
            <a href="reservations.php?tab=calendar" class="nav-btn primary">カレンダー表示</a>
        </div>

        <div class="detail-header">
            <div class="header-info">
                <div>
                    <h1>申し込み詳細 #<?php echo $app['id']; ?></h1>
                    <p style="margin: 0; opacity: 0.9;">申し込み日時: <?php echo date('Y年m月d日 H:i', strtotime($app['created_at'])); ?></p>
                    <?php if ($app['updated_at'] !== $app['created_at']): ?>
                        <p style="margin: 0; opacity: 0.9; font-size: 14px;">最終更新: <?php echo date('Y年m月d日 H:i', strtotime($app['updated_at'])); ?></p>
                    <?php endif; ?>
                </div>
                <div class="status-badge status-<?php echo $app['status']; ?>">
                    <?php echo translateValue($app['status'], 'status'); ?>
                </div>
            </div>
        </div>

        <?php if ($app['status'] === 'confirmed' && $app['confirmed_date'] && $app['confirmed_time_slot']): ?>
            <div class="confirmed-section">
                <h3>✅ 確定済み予約</h3>
                <div class="confirmed-datetime">
                    <?php echo date('Y年m月d日', strtotime($app['confirmed_date'])); ?>
                    <?php echo getTimeSlotDisplayName($app['confirmed_time_slot']); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-section">
                <h3>👤 お客様情報</h3>
                <div class="info-item">
                    <span class="info-label">お名前</span>
                    <span class="info-value"><?php echo htmlspecialchars($app['customer_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">電話番号</span>
                    <span class="info-value"><?php echo htmlspecialchars($app['customer_phone']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">メールアドレス</span>
                    <span class="info-value"><?php echo htmlspecialchars($app['customer_email']); ?></span>
                </div>
            </div>

            <div class="info-section">
                <h3>🏠 設置場所</h3>
                <div class="info-item">
                    <span class="info-label">郵便番号</span>
                    <span class="info-value"><?php echo htmlspecialchars($app['postal_code']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">住所</span>
                    <span class="info-value"><?php echo htmlspecialchars($app['address']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">建物種別</span>
                    <span class="info-value"><?php echo translateValue($app['building_type'], 'building_type'); ?></span>
                </div>
                <?php if ($app['floor_number']): ?>
                    <div class="info-item">
                        <span class="info-label">階数</span>
                        <span class="info-value"><?php echo htmlspecialchars($app['floor_number']); ?>階</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-section">
                <h3>❄️ エアコン詳細</h3>
                <div class="info-item">
                    <span class="info-label">設置部屋</span>
                    <span class="info-value"><?php echo translateValue($app['room_type'], 'room_type'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">部屋の広さ</span>
                    <span class="info-value"><?php echo translateValue($app['room_size'], 'room_size'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">エアコン種類</span>
                    <span class="info-value"><?php echo translateValue($app['ac_type'], 'ac_type'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">容量</span>
                    <span class="info-value"><?php echo translateValue($app['ac_capacity'], 'ac_capacity'); ?></span>
                </div>
            </div>

            <div class="info-section">
                <h3>🔧 工事詳細</h3>
                <div class="info-item">
                    <span class="info-label">既存エアコン</span>
                    <span class="info-value"><?php echo translateValue($app['existing_ac'], 'existing_ac'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">既存機撤去</span>
                    <span class="info-value"><?php echo translateValue($app['existing_ac_removal'], 'existing_ac_removal'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">電気工事</span>
                    <span class="info-value"><?php echo translateValue($app['electrical_work'], 'electrical_work'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">配管工事</span>
                    <span class="info-value"><?php echo translateValue($app['piping_work'], 'piping_work'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">壁穴あけ</span>
                    <span class="info-value"><?php echo translateValue($app['wall_drilling'], 'wall_drilling'); ?></span>
                </div>
            </div>
        </div>

        <div class="preferred-slots-section">
            <h3>📅 希望日時</h3>
            <?php if (!empty($allPreferredSlots)): ?>
                <?php foreach ($allPreferredSlots as $slot): ?>
                    <div class="slot-item <?php echo $slot['deleted_at'] ? 'deleted' : ($app['status'] === 'confirmed' && $app['confirmed_date'] === $slot['preferred_date'] && $app['confirmed_time_slot'] === $slot['time_slot'] ? 'confirmed' : ''); ?>">
                        <div class="slot-header">
                            <span class="slot-priority">第<?php echo $slot['priority']; ?>希望</span>
                            <span class="slot-status <?php echo $slot['deleted_at'] ? 'deleted' : 'active'; ?>">
                                <?php if ($slot['deleted_at']): ?>
                                    削除済み (<?php echo $slot['deleted_reason']; ?>)
                                <?php elseif ($app['status'] === 'confirmed' && $app['confirmed_date'] === $slot['preferred_date'] && $app['confirmed_time_slot'] === $slot['time_slot']): ?>
                                    確定
                                <?php else: ?>
                                    有効
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="slot-datetime">
                            <?php echo date('Y年m月d日', strtotime($slot['preferred_date'])); ?>
                            <?php echo getTimeSlotDisplayName($slot['time_slot']); ?>
                        </div>
                        <?php if ($slot['deleted_at']): ?>
                            <small style="color: #666;">削除日時: <?php echo date('Y年m月d日 H:i', strtotime($slot['deleted_at'])); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; font-style: italic;">希望日時の情報がありません。</p>
            <?php endif; ?>
        </div>

        <?php if ($app['special_requests']): ?>
            <div class="special-requests">
                <h4>📝 特記事項・ご要望</h4>
                <p><?php echo nl2br(htmlspecialchars($app['special_requests'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($app['status'] === 'pending' && !empty($preferredSlots)): ?>
            <div class="action-buttons">
                <h3>🎯 管理操作</h3>
                <div class="btn-group">
                    <?php foreach ($preferredSlots as $slot): ?>
                        <?php
                        $isAvailable = $reservationSlot->isSlotAvailable($slot['preferred_date'], $slot['time_slot']);
                        ?>
                        <form method="POST" action="reservations.php" style="display: inline;">
                            <input type="hidden" name="action" value="confirm_reservation">
                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                            <input type="hidden" name="confirmed_date" value="<?php echo $slot['preferred_date']; ?>">
                            <input type="hidden" name="confirmed_time_slot" value="<?php echo $slot['time_slot']; ?>">
                            <button type="submit" class="action-btn <?php echo $isAvailable ? 'btn-confirm' : 'btn-disabled'; ?>"
                                    <?php echo !$isAvailable ? 'disabled title="満席のため予約できません"' : ''; ?>>
                                第<?php echo $slot['priority']; ?>希望で確定
                                <?php if (!$isAvailable): ?>(満席)<?php endif; ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($app['status'] !== 'cancelled'): ?>
            <div class="action-buttons">
                <h3>⚠️ キャンセル操作</h3>
                <div class="btn-group">
                    <button onclick="cancelApplication(<?php echo $app['id']; ?>)" class="action-btn btn-cancel">
                        この申し込みをキャンセル
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        async function cancelApplication(applicationId) {
            if (!confirm('この申し込みをキャンセルしますか？\n\nこの操作は取り消せません。')) {
                return;
            }

            try {
                const response = await fetch('cancel_application.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=cancel_application&application_id=${applicationId}`
                });

                const result = await response.json();

                if (result.success) {
                    alert('申し込みをキャンセルしました。');
                    location.reload();
                } else {
                    alert('エラーが発生しました: ' + result.message);
                }
            } catch (error) {
                alert('通信エラーが発生しました。');
                console.error('Error:', error);
            }
        }
    </script>
</body>
</html>