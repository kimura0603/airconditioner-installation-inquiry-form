<?php
require_once 'models/Application.php';

$application = new Application();
$applications = $application->getAll();

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
        'preferred_time' => [
            'morning' => '午前中',
            'afternoon' => '午後',
            'evening' => '夕方',
            'flexible' => '指定なし'
        ],
        'yes_no' => [
            'yes' => 'はい',
            'no' => 'いいえ'
        ]
    ];

    if (isset($translations[$field][$value])) {
        return $translations[$field][$value];
    } elseif (in_array($field, ['existing_ac', 'existing_ac_removal', 'wall_drilling']) && isset($translations['yes_no'][$value])) {
        return $translations['yes_no'][$value];
    }

    return $value;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申し込み一覧 - エアコン工事管理システム</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-container {
            max-width: 1200px;
        }

        .application-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .application-header {
            background: #007bff;
            color: white;
            padding: 10px 15px;
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .application-id {
            font-weight: bold;
            font-size: 18px;
        }

        .application-date {
            font-size: 14px;
            opacity: 0.9;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }

        .info-section h4 {
            color: #007bff;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-item {
            margin-bottom: 8px;
            display: flex;
        }

        .info-label {
            font-weight: bold;
            min-width: 120px;
            color: #333;
        }

        .info-value {
            color: #666;
        }

        .special-requests {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }

        .special-requests h4 {
            color: #856404;
            margin-bottom: 10px;
        }

        .no-applications {
            text-align: center;
            padding: 50px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #666;
        }

        .actions {
            text-align: center;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .application-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .info-item {
                flex-direction: column;
            }

            .info-label {
                min-width: auto;
                margin-bottom: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="container admin-container">
        <header>
            <h1>エアコン工事申し込み一覧</h1>
            <p>受け付けた申し込み内容を確認できます。</p>
        </header>

        <div class="actions">
            <a href="index.php" class="submit-btn" style="text-decoration: none; display: inline-block;">新規申し込みフォーム</a>
        </div>

        <?php if (empty($applications)): ?>
            <div class="no-applications">
                <h3>申し込みがありません</h3>
                <p>まだ申し込みが受け付けられていません。</p>
            </div>
        <?php else: ?>
            <?php foreach ($applications as $app): ?>
                <div class="application-card">
                    <div class="application-header">
                        <div class="application-id">申し込み番号: #<?php echo htmlspecialchars($app['id']); ?></div>
                        <div class="application-date"><?php echo date('Y年m月d日 H:i', strtotime($app['created_at'])); ?></div>
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
                            <?php if (!empty($app['customer_email'])): ?>
                            <div class="info-item">
                                <span class="info-label">メール:</span>
                                <span class="info-value"><?php echo htmlspecialchars($app['customer_email']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="info-section">
                            <h4>設置場所</h4>
                            <div class="info-item">
                                <span class="info-label">郵便番号:</span>
                                <span class="info-value"><?php echo htmlspecialchars($app['postal_code']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">住所:</span>
                                <span class="info-value"><?php echo htmlspecialchars($app['address']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">建物種別:</span>
                                <span class="info-value"><?php echo translateValue($app['building_type'], 'building_type'); ?></span>
                            </div>
                            <?php if (!empty($app['floor_number'])): ?>
                            <div class="info-item">
                                <span class="info-label">階数:</span>
                                <span class="info-value"><?php echo htmlspecialchars($app['floor_number']); ?>階</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="info-section">
                            <h4>エアコン詳細</h4>
                            <div class="info-item">
                                <span class="info-label">設置部屋:</span>
                                <span class="info-value"><?php echo translateValue($app['room_type'], 'room_type'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">部屋の広さ:</span>
                                <span class="info-value"><?php echo translateValue($app['room_size'], 'room_size'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">エアコン種類:</span>
                                <span class="info-value"><?php echo translateValue($app['ac_type'], 'ac_type'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">エアコン能力:</span>
                                <span class="info-value"><?php echo htmlspecialchars($app['ac_capacity']); ?></span>
                            </div>
                        </div>

                        <div class="info-section">
                            <h4>工事内容</h4>
                            <div class="info-item">
                                <span class="info-label">既設エアコン:</span>
                                <span class="info-value"><?php echo translateValue($app['existing_ac'], 'existing_ac'); ?></span>
                            </div>
                            <?php if ($app['existing_ac'] === 'yes'): ?>
                            <div class="info-item">
                                <span class="info-label">撤去:</span>
                                <span class="info-value"><?php echo translateValue($app['existing_ac_removal'], 'existing_ac_removal'); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <span class="info-label">電気工事:</span>
                                <span class="info-value"><?php echo translateValue($app['electrical_work'], 'electrical_work'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">配管工事:</span>
                                <span class="info-value"><?php echo translateValue($app['piping_work'], 'piping_work'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">壁穴あけ:</span>
                                <span class="info-value"><?php echo translateValue($app['wall_drilling'], 'wall_drilling'); ?></span>
                            </div>
                        </div>

                        <div class="info-section">
                            <h4>希望日時</h4>
                            <?php if (!empty($app['preferred_date'])): ?>
                            <div class="info-item">
                                <span class="info-label">希望日:</span>
                                <span class="info-value"><?php echo date('Y年m月d日', strtotime($app['preferred_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <span class="info-label">希望時間:</span>
                                <span class="info-value"><?php echo translateValue($app['preferred_time'], 'preferred_time'); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($app['special_requests'])): ?>
                    <div class="special-requests">
                        <h4>特記事項・ご要望</h4>
                        <p><?php echo nl2br(htmlspecialchars($app['special_requests'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>