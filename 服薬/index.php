<?php
date_default_timezone_set('Asia/Tokyo');
session_start();

// 1. ログインチェック
if (!isset($_SESSION['patient_user_id'])) {
    header("Location: login_qr.php");
    exit;
}
$current_user_id = $_SESSION['patient_user_id'];

// 2. DB接続
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';
try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("接続エラー: " . $e->getMessage());
}

/* ===================================================
   患者ごとの目標回数（daily_target）を取得
   =================================================== */
$stmt_target = $pdo->prepare("SELECT daily_target FROM patients WHERE user_id = ?");
$stmt_target->execute([$current_user_id]);
$patient_info = $stmt_target->fetch(PDO::FETCH_ASSOC);

// 佐藤はなさんは1日1回なので、設定がない場合も「1」にする
$daily_dose_target = ($patient_info && (int)$patient_info['daily_target'] > 0) ? (int)$patient_info['daily_target'] : 1; 

// --- はなまる判定用のデータ取得（今月分） ---
$current_month = date('Y-m');
$stmt_cal = $pdo->prepare("
    SELECT DATE_FORMAT(record_timestamp, '%e') as day_num, COUNT(*) as cnt 
    FROM medication_records 
    WHERE user_id = ? AND DATE_FORMAT(record_timestamp, '%Y-%m') = ? 
    GROUP BY DATE(record_timestamp)
");
$stmt_cal->execute([$current_user_id, $current_month]);
$cal_records = $stmt_cal->fetchAll(PDO::FETCH_ASSOC);

$recorded_days = [];
foreach ($cal_records as $row) {
    // 目標回数（1回）以上なら数値としてリストに追加
    if ((int)$row['cnt'] >= $daily_dose_target) {
        $recorded_days[] = (int)$row['day_num'];
    }
}

// --- 本日の記録数と完了判定 ---
$stmt_today_count = $pdo->prepare("SELECT COUNT(*) FROM medication_records WHERE user_id = ? AND DATE(record_timestamp) = CURDATE()");
$stmt_today_count->execute([$current_user_id]);
$today_count = (int)$stmt_today_count->fetchColumn();

// 1回以上飲んでいれば達成！
$is_goal_achieved = ($today_count >= $daily_dose_target);

// 未服薬スロットの取得
$stmt_slots = $pdo->prepare("SELECT time_slot FROM medication_records WHERE user_id = ? AND DATE(record_timestamp) = CURDATE()");
$stmt_slots->execute([$current_user_id]);
$slots_recorded = $stmt_slots->fetchAll(PDO::FETCH_COLUMN);

$all_slots = ['朝', '昼', '夜'];
$remaining_slots = array_diff($all_slots, $slots_recorded); 

$message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>メディケア・リワード</title>
    <style>
        body { font-family: "Hiragino Sans", sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 400px; margin: 0 auto; }
        .card { background: white; border-radius: 25px; padding: 30px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); text-align: center; margin-bottom: 20px; }
        .user-info { font-size: 18px; color: #0078d7; font-weight: bold; margin-bottom: 20px; }
        h1 { font-size: 22px; margin-bottom: 10px; color: #333; }
        select { padding: 15px; border-radius: 12px; border: 2px solid #0078d7; font-size: 20px; width: 100%; margin-bottom: 20px; background: white; }
        #camera-area { width: 100%; margin-bottom: 20px; display: none; }
        video { width: 100%; border-radius: 15px; border: 3px solid #0078d7; background: #000; }
        .btn-main { display: block; width: 100%; padding: 20px; font-size: 24px; font-weight: bold; border-radius: 15px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-orange { background: #ff9800; color: white; box-shadow: 0 5px 0 #e68a00; }
        .btn-green { background: #4CAF50; color: white; box-shadow: 0 5px 0 #2e7d32; }
        .btn-main:active { transform: translateY(3px); box-shadow: none; }
        .goal-msg { background: #e8f5e9; color: #2e7d32; padding: 25px; border-radius: 15px; font-size: 20px; font-weight: bold; border: 2px solid #2e7d32; }
        
        .cal-header { font-size: 18px; font-weight: bold; color: #444; margin-bottom: 15px; text-align: center; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day { aspect-ratio: 1/1; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 8px; font-size: 14px; background: #fafafa; color: #bbb; }
        .has-record { background: #fff; border-color: #ffd180; color: #333; }
        .hanamaru { position: absolute; font-size: 28px; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #ff5252; opacity: 0.9; pointer-events: none; }
        .today-circle { border: 2px solid #0078d7 !important; color: #0078d7 !important; font-weight: bold; background: #eef7ff; }
        .reset-btn { display: inline-block; margin-top: 20px; color: #d9534f; text-decoration: none; font-size: 13px; border-bottom: 1px solid #d9534f; padding-bottom: 2px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>メディケア・リワード</h1>
        <div class="user-info"><?= htmlspecialchars($current_user_id) ?> さんの記録</div>

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
                    <input type="hidden" name="image_data" id="image_data">
                </div>

                <button type="button" id="start-btn" class="btn-main btn-orange">📸 写真を撮る</button>
                <button type="button" id="capture-btn" class="btn-main btn-green" style="display:none;">🤳 服薬を報告する</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="cal-header">📅 <?= date('n') ?>月の「はなまる」表</div>
        <div class="cal-grid">
            <?php
            $days_in_month = (int)date('t');
            $today_d = (int)date('j');
            for ($d = 1; $d <= $days_in_month; $d++):
                // 数値として比較することで判定ミスを防ぐ
                $is_recorded = in_array($d, $recorded_days, true);
                $is_today = ($d === $today_d);
            ?>
                <div class="cal-day <?= $is_recorded ? 'has-record' : '' ?> <?= $is_today ? 'today-circle' : '' ?>">
                    <?= $d ?>
                    <?php if ($is_recorded): ?>
                        <span class="hanamaru">💮</span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        <div style="text-align: center; font-size: 12px; color: #666; margin-top: 10px;">
            ※1日<?= $daily_dose_target ?>回の服用で💮がつきます
        </div>
    </div>

    <div style="text-align:center; margin-bottom: 40px;">
        <a href="reset_day.php" class="reset-btn">データをリセットしてやり直す</a>
    </div>
</div>

<script>
    const startBtn = document.getElementById('start-btn');
    const captureBtn = document.getElementById('capture-btn');
    const cameraArea = document.getElementById('camera-area');
    const video = document.getElementById('video');
    const imageDataInput = document.getElementById('image_data');
    const form = document.getElementById('recordForm');

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
            alert("カメラを起動できませんでした。");
        }
    });

    captureBtn.addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        
        imageDataInput.value = canvas.toDataURL('image/jpeg');
        captureBtn.innerHTML = "送信中...";
        captureBtn.disabled = true;
        form.submit();
    });
</script>
</body>
</html>