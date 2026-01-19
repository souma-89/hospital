<?php
date_default_timezone_set('Asia/Tokyo');
session_start();

// DB接続
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';
try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("接続エラー");
}

// ユーザー設定
$_SESSION['user_id'] = '山田きよえ';
$current_user_id = $_SESSION['user_id'];
$daily_dose_target = 3; // 1日3回（朝・昼・夜）

// --- 1日3回記録がある日だけを取得するロジック ---
$current_month = date('Y-m');
$stmt_cal = $pdo->prepare("
    SELECT DATE_FORMAT(record_timestamp, '%e') as day, COUNT(*) as cnt 
    FROM medication_records 
    WHERE user_id = ? AND DATE_FORMAT(record_timestamp, '%Y-%m') = ? 
    GROUP BY DATE_FORMAT(record_timestamp, '%Y-%m-%d')
");
$stmt_cal->execute([$current_user_id, $current_month]);
$cal_records = $stmt_cal->fetchAll(PDO::FETCH_ASSOC);

$recorded_days = [];
foreach ($cal_records as $row) {
    if ($row['cnt'] >= $daily_dose_target) {
        $recorded_days[] = $row['day'];
    }
}

// 本日の記録状況の判定
$all_slots = ['朝', '昼', '夜'];
$slots_recorded = $_SESSION['recorded_slots_today'] ?? [];
$is_goal_achieved = (count($slots_recorded) >= $daily_dose_target);
$remaining_slots = array_diff($all_slots, $slots_recorded); 

$message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メディケア・リワード</title>
    <style>
        body { font-family: "Hiragino Sans", sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 400px; margin: 0 auto; }
        .card { background: white; border-radius: 25px; padding: 30px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); text-align: center; margin-bottom: 20px; }
        
        h1 { font-size: 22px; margin-bottom: 10px; color: #333; }
        .user-info { font-size: 18px; color: #0078d7; font-weight: bold; margin-bottom: 20px; }
        select { padding: 15px; border-radius: 12px; border: 2px solid #0078d7; font-size: 20px; width: 90%; margin-bottom: 25px; background: white; }
        
        .camera-label { display: block; background: #ff9800; color: white; padding: 25px; font-size: 26px; border-radius: 15px; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 0 #e68a00; }
        .camera-label:active { transform: translateY(2px); box-shadow: none; background: #e68a00; }
        #camera-input { display: none; }
        
        .goal-msg { background: #e8f5e9; color: #2e7d32; padding: 20px; border-radius: 15px; font-size: 20px; font-weight: bold; border: 2px solid #2e7d32; }

        .calendar-card { background: white; border-radius: 25px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .cal-header { font-size: 18px; font-weight: bold; color: #444; margin-bottom: 15px; text-align: center; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day { aspect-ratio: 1/1; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 8px; font-size: 14px; color: #bbb; background: #fafafa; }
        .cal-day.has-record { color: #333; background: #fff; border-color: #ffd180; }
        .hanamaru { position: absolute; font-size: 28px; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #ff5252; opacity: 0.9; pointer-events: none; z-index: 2; }
        .today-circle { border: 2px solid #0078d7 !important; color: #0078d7 !important; font-weight: bold; background: #eef7ff; }
        .target-info { text-align: center; font-size: 12px; color: #666; margin-top: 10px; }

        /* リセットボタン用のスタイル */
        .reset-container { margin-top: 25px; text-align: center; padding-bottom: 40px; }
        .reset-btn {
            display: inline-block;
            padding: 12px 20px;
            background-color: #fff;
            color: #d9534f;
            border: 2px solid #d9534f;
            border-radius: 10px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            transition: 0.2s;
        }
        .reset-btn:hover { background-color: #fdf2f2; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>メディケア・リワード</h1>
        <div class="user-info"><?= $current_user_id ?> さんの記録</div>

        <?php if ($message): ?>
            <div style="background:#fff3cd; padding:10px; border-radius:10px; margin-bottom:15px; font-size:14px; border: 1px solid #ffeeba;"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($is_goal_achieved): ?>
            <div class="goal-msg">💮 本日の服薬は<br>すべて完了しました！</div>
        <?php else: ?>
            <form id="recordForm" action="record_process.php" method="post" enctype="multipart/form-data">
                <label style="display:block; margin-bottom:10px; font-weight:bold; font-size: 18px;">次に飲むお薬を記録：</label>
                <select name="time" required>
                    <?php foreach ($remaining_slots as $slot): ?>
                        <option value="<?= htmlspecialchars($slot) ?>"><?= htmlspecialchars($slot) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="file" accept="image/*" capture="camera" name="med_photo" id="camera-input" onchange="document.getElementById('recordForm').submit()">
                <label for="camera-input" class="camera-label">📸 写真を撮る</label>
            </form>
        <?php endif; ?>
    </div>

    <div class="calendar-card">
        <div class="cal-header">📅 <?= date('n') ?>月の「はなまる」表</div>
        <div class="cal-grid">
            <?php
            $days_in_month = date('t');
            $today_d = date('j');
            for ($d = 1; $d <= $days_in_month; $d++):
                $is_recorded = in_array((string)$d, $recorded_days);
                $is_today = ($d == $today_d);
            ?>
                <div class="cal-day <?= $is_recorded ? 'has-record' : '' ?> <?= $is_today ? 'today-circle' : '' ?>">
                    <?= $d ?>
                    <?php if ($is_recorded): ?>
                        <span class="hanamaru">💮</span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        <div class="target-info">※1日3回の服用で💮がつきます</div>
    </div>

    <div class="reset-container">
        <p style="font-size: 13px; color: #999; margin-bottom: 8px;">【開発・テスト用操作】</p>
        <a href="reset_day.php" class="reset-btn">本日分をリセットしてやり直す</a>
    </div>

</div>
</body>
</html>