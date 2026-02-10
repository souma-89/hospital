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
    die("接続エラー: " . $e->getMessage());
}

// ユーザー設定 (デモ用固定)
$_SESSION['user_id'] = '山田きよえ';
$current_user_id = $_SESSION['user_id'];
$daily_dose_target = 3; 

// --- 1日3回記録がある日だけを取得（はなまる判定） ---
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

// 本日の記録済みスロットを取得
$stmt_today = $pdo->prepare("SELECT time_slot FROM medication_records WHERE user_id = ? AND DATE(record_timestamp) = CURDATE()");
$stmt_today->execute([$current_user_id]);
$slots_recorded = $stmt_today->fetchAll(PDO::FETCH_COLUMN);

$all_slots = ['朝', '昼', '夜'];
$is_goal_achieved = (count($slots_recorded) >= $daily_dose_target);
$remaining_slots = array_diff($all_slots, $slots_recorded); 

$message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>メディケア・リワード | 中村病院</title>
    <style>
        body { font-family: "Hiragino Sans", sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 400px; margin: 0 auto; }
        .card { background: white; border-radius: 25px; padding: 30px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); text-align: center; margin-bottom: 20px; }
        
        h1 { font-size: 22px; margin-bottom: 10px; color: #333; }
        .user-info { font-size: 18px; color: #0078d7; font-weight: bold; margin-bottom: 20px; }
        select { padding: 15px; border-radius: 12px; border: 2px solid #0078d7; font-size: 20px; width: 100%; margin-bottom: 20px; background: white; }
        
        /* カメラプレビュー */
        #camera-area { width: 100%; margin-bottom: 20px; position: relative; display: none; }
        video { width: 100%; border-radius: 15px; border: 3px solid #0078d7; background: #000; }
        
        .btn-main { 
            display: block; width: 100%; padding: 20px; font-size: 24px; font-weight: bold; 
            border-radius: 15px; border: none; cursor: pointer; transition: 0.2s;
        }
        .btn-orange { background: #ff9800; color: white; box-shadow: 0 5px 0 #e68a00; }
        .btn-green { background: #4CAF50; color: white; box-shadow: 0 5px 0 #2e7d32; }
        .btn-main:active { transform: translateY(3px); box-shadow: none; }

        .goal-msg { background: #e8f5e9; color: #2e7d32; padding: 25px; border-radius: 15px; font-size: 20px; font-weight: bold; border: 2px solid #2e7d32; }

        .calendar-card { background: white; border-radius: 25px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .cal-header { font-size: 18px; font-weight: bold; color: #444; margin-bottom: 15px; text-align: center; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day { aspect-ratio: 1/1; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 8px; font-size: 14px; color: #bbb; background: #fafafa; }
        .cal-day.has-record { color: #333; background: #fff; border-color: #ffd180; }
        .hanamaru { position: absolute; font-size: 28px; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #ff5252; opacity: 0.9; }
        .today-circle { border: 2px solid #0078d7 !important; color: #0078d7 !important; font-weight: bold; background: #eef7ff; }
        .target-info { text-align: center; font-size: 12px; color: #666; margin-top: 10px; }

        .reset-btn { display: inline-block; margin-top: 20px; color: #d9534f; text-decoration: none; font-size: 13px; }
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
            <form id="recordForm" action="record_process.php" method="post">
                <label style="display:block; margin-bottom:10px; font-weight:bold;">次に飲むお薬を選択：</label>
                <select name="time" id="timeSelect" required>
                    <?php foreach ($remaining_slots as $slot): ?>
                        <option value="<?= htmlspecialchars($slot) ?>"><?= htmlspecialchars($slot) ?></option>
                    <?php endforeach; ?>
                </select>

                <div id="camera-area">
                    <video id="video" autoplay playsinline></video>
                    <canvas id="canvas" style="display:none;"></canvas>
                    <input type="hidden" name="image_data" id="image_data">
                </div>

                <button type="button" id="start-btn" class="btn-main btn-orange">📸 写真を撮る</button>
                <button type="button" id="capture-btn" class="btn-main btn-green" style="display:none;">🤳 服薬を報告する</button>
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

    <div style="text-align:center;">
        <a href="reset_day.php" class="reset-btn">データをリセットしてやり直す</a>
    </div>
</div>

<script>
    const startBtn = document.getElementById('start-btn');
    const captureBtn = document.getElementById('capture-btn');
    const cameraArea = document.getElementById('camera-area');
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const imageDataInput = document.getElementById('image_data');
    const form = document.getElementById('recordForm');

    // カメラ起動
    startBtn.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "environment" }, 
                audio: false 
            });
            video.srcObject = stream;
            cameraArea.style.display = 'block';
            captureBtn.style.display = 'block';
            startBtn.style.display = 'none';
        } catch (err) {
            alert("カメラを起動できません。HTTPS環境か、ブラウザの許可を確認してください。");
        }
    });

    // 撮影して送信
    captureBtn.addEventListener('click', () => {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        
        // 画像をBase64で取得
        const dataUrl = canvas.toDataURL('image/jpeg');
        imageDataInput.value = dataUrl;

        captureBtn.innerHTML = "送信中...";
        captureBtn.disabled = true;
        form.submit();
    });
</script>

</body>
</html>