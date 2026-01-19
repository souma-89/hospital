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

// 今月の「はなまる」がつく日（記録がある日）を取得
$current_month = date('Y-m');
$stmt_cal = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(record_timestamp, '%e') as day FROM medication_records WHERE user_id = ? AND DATE_FORMAT(record_timestamp, '%Y-%m') = ?");
$stmt_cal->execute([$current_user_id, $current_month]);
$recorded_days = $stmt_cal->fetchAll(PDO::FETCH_COLUMN);

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
        
        .user-info { font-size: 18px; color: #0078d7; font-weight: bold; margin-bottom: 20px; }
        select { padding: 15px; border-radius: 12px; border: 2px solid #ccc; font-size: 20px; width: 90%; margin-bottom: 25px; }
        
        .camera-label { display: block; background: #ff9800; color: white; padding: 25px; font-size: 26px; border-radius: 15px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .camera-label:active { transform: scale(0.95); background: #e68a00; }
        #camera-input { display: none; }
        
        .goal-msg { background: #e8f5e9; color: #2e7d32; padding: 20px; border-radius: 15px; font-size: 20px; font-weight: bold; }

        /* カレンダー */
        .calendar-card { background: white; border-radius: 25px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .cal-header { font-size: 18px; font-weight: bold; color: #444; margin-bottom: 15px; text-align: center; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day { aspect-ratio: 1/1; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 8px; font-size: 14px; color: #bbb; background: #fafafa; }
        .cal-day.has-record { color: #333; background: #fff; border-color: #ffd180; }
        .hanamaru { position: absolute; font-size: 28px; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #ff5252; opacity: 0.9; pointer-events: none; }
        .today-circle { border: 2px solid #0078d7 !important; color: #0078d7 !important; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>メディケア・リワード</h1>
        <div class="user-info"><?= $current_user_id ?> さんの記録</div>

        <?php if ($message): ?>
            <div style="background:#fff3cd; padding:10px; border-radius:10px; margin-bottom:15px; font-size:14px;"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($is_goal_achieved): ?>
            <div class="goal-msg">💮 本日の服薬は<br>すべて完了しました！</div>
        <?php else: ?>
            <form id="recordForm" action="record_process.php" method="post" enctype="multipart/form-data">
                <label style="display:block; margin-bottom:10px; font-weight:bold;">次に飲むのは：</label>
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
    </div>
</div>
</body>
</html>