<?php
require_once 'models/Application.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'customer_name' => $_POST['customer_name'] ?? '',
        'customer_phone' => $_POST['customer_phone'] ?? '',
        'customer_email' => $_POST['customer_email'] ?? '',
        'postal_code' => $_POST['postal_code'] ?? '',
        'address' => $_POST['address'] ?? '',
        'building_type' => $_POST['building_type'] ?? '',
        'floor_number' => !empty($_POST['floor_number']) ? (int)$_POST['floor_number'] : null,
        'room_type' => $_POST['room_type'] ?? '',
        'room_size' => $_POST['room_size'] ?? '',
        'ac_type' => $_POST['ac_type'] ?? '',
        'ac_capacity' => $_POST['ac_capacity'] ?? '',
        'existing_ac' => $_POST['existing_ac'] ?? '',
        'existing_ac_removal' => $_POST['existing_ac_removal'] ?? 'no',
        'electrical_work' => $_POST['electrical_work'] ?? '',
        'piping_work' => $_POST['piping_work'] ?? '',
        'wall_drilling' => $_POST['wall_drilling'] ?? '',
        'preferred_date' => !empty($_POST['preferred_date']) ? $_POST['preferred_date'] : null,
        'preferred_time' => $_POST['preferred_time'] ?? 'flexible',
        'special_requests' => $_POST['special_requests'] ?? ''
    ];

    $errors = [];

    if (empty($data['customer_name'])) {
        $errors[] = 'お名前は必須です。';
    }
    if (empty($data['customer_phone'])) {
        $errors[] = '電話番号は必須です。';
    }
    if (empty($data['postal_code'])) {
        $errors[] = '郵便番号は必須です。';
    }
    if (empty($data['address'])) {
        $errors[] = '住所は必須です。';
    }
    if (empty($data['building_type'])) {
        $errors[] = '建物種別は必須です。';
    }
    if (empty($data['room_type'])) {
        $errors[] = '設置予定の部屋は必須です。';
    }
    if (empty($data['room_size'])) {
        $errors[] = '部屋の広さは必須です。';
    }
    if (empty($data['ac_type'])) {
        $errors[] = 'エアコンの種類は必須です。';
    }
    if (empty($data['ac_capacity'])) {
        $errors[] = 'エアコンの能力は必須です。';
    }
    if (empty($data['existing_ac'])) {
        $errors[] = '既設エアコンの有無は必須です。';
    }
    if (empty($data['electrical_work'])) {
        $errors[] = '電気工事の選択は必須です。';
    }
    if (empty($data['piping_work'])) {
        $errors[] = '配管工事の選択は必須です。';
    }
    if (empty($data['wall_drilling'])) {
        $errors[] = '壁穴あけ工事の選択は必須です。';
    }

    if (empty($errors)) {
        try {
            $application = new Application();
            $id = $application->create($data);

            if ($id) {
                $success_message = "申し込みが正常に受け付けられました。申し込み番号: " . $id;
            } else {
                $errors[] = "申し込み処理中にエラーが発生しました。";
            }
        } catch (Exception $e) {
            $errors[] = "データベースエラーが発生しました: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申し込み結果 - エアコン工事申し込みフォーム</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>申し込み結果</h1>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <h2>申し込み完了</h2>
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <p>担当者より後日ご連絡させていただきます。</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <h2>エラーが発生しました</h2>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <a href="index.php" class="submit-btn" style="text-decoration: none; display: inline-block;">新しい申し込み</a>
            <a href="admin.php" class="submit-btn" style="text-decoration: none; display: inline-block; background-color: #6c757d; margin-left: 10px;">申し込み一覧（管理画面）</a>
        </div>
    </div>
</body>
</html>