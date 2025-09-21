<?php
// 管理画面のダッシュボード
require_once '../models/Application.php';
require_once '../models/ReservationSlot.php';

$application = new Application();
$reservationSlot = new ReservationSlot();

// 統計データの取得
$totalApplications = count($application->getAll());
$pendingApplications = count($application->getByStatus('pending'));
$confirmedApplications = count($application->getByStatus('confirmed'));
$cancelledApplications = count($application->getByStatus('cancelled'));

// 今月のカレンダーデータ
$year = date('Y');
$month = date('m');
$calendarData = $reservationSlot->getMonthlyCalendar($year, $month);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面ダッシュボード - エアコン工事管理システム</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 8px;
        }

        .dashboard-nav {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .nav-btn {
            background: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .nav-btn:hover {
            background: #0056b3;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }

        .quick-actions {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .quick-actions h3 {
            margin-bottom: 15px;
            color: #495057;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }

        .action-btn.secondary {
            background: #6c757d;
        }

        .action-btn:hover {
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .dashboard-nav {
                flex-direction: column;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>エアコン工事管理システム</h1>
                <p>予約・申し込み管理ダッシュボード</p>
            </div>
            <div>
                <span><?php echo date('Y年m月d日 H:i'); ?></span>
            </div>
        </div>

        <nav class="dashboard-nav">
            <a href="index.php" class="nav-btn">ダッシュボード</a>
            <a href="reservations.php" class="nav-btn">予約管理</a>
            <a href="applications.php" class="nav-btn">申し込み一覧</a>
            <a href="../index.php" class="nav-btn secondary">申し込みフォーム</a>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalApplications; ?></div>
                <div class="stat-label">総申し込み数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pendingApplications; ?></div>
                <div class="stat-label">申し込み中</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $confirmedApplications; ?></div>
                <div class="stat-label">予約確定</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $cancelledApplications; ?></div>
                <div class="stat-label">キャンセル</div>
            </div>
        </div>

        <div class="quick-actions">
            <h3>クイックアクション</h3>
            <div class="action-buttons">
                <a href="reservations.php?tab=calendar" class="action-btn">カレンダー表示</a>
                <a href="reservations.php?tab=list" class="action-btn">申し込み一覧</a>
                <a href="applications.php" class="action-btn secondary">詳細管理画面</a>
                <a href="../index.php" class="action-btn secondary">申し込みフォーム</a>
            </div>
        </div>

        <?php if ($pendingApplications > 0): ?>
        <div class="info-section">
            <h4>⚠️ 対応が必要な申し込み</h4>
            <p><?php echo $pendingApplications; ?>件の申し込みが予約確定待ちです。</p>
            <a href="reservations.php?tab=list" class="action-btn">今すぐ確認</a>
        </div>
        <?php endif; ?>

        <div class="info-section">
            <h4>📋 管理機能一覧</h4>
            <ul>
                <li><strong>予約管理</strong>: カレンダー表示、予約確定・キャンセル処理</li>
                <li><strong>申し込み一覧</strong>: 従来の詳細表示形式</li>
                <li><strong>時間枠管理</strong>: 各日時の利用可能設定</li>
            </ul>
        </div>
    </div>
</body>
</html>