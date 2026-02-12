<?php
date_default_timezone_set('Asia/Tokyo');
session_start();

// ログインチェック
if (!isset($_SESSION['patient_user_id'])) {
    header("Location: login_qr.php");
    exit;
}
$current_user_id = $_SESSION['patient_user_id'];

// DB接続
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';
try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("接続エラー: " . $e->getMessage());
}

$daily_dose_target = 3; 

// --- はなまる判定 ---
$current_month = date('Y-m');
$stmt_cal = $pdo->prepare("SELECT DATE_FORMAT(record_timestamp, '%e') as day, COUNT(*) as cnt FROM medication_records WHERE user_id = ? AND DATE_FORMAT(record_timestamp, '%Y-%m') = ? GROUP BY DATE_FORMAT(record_timestamp, '%Y-%m-%d')");
$stmt_cal->execute([$current_user_id, $current_month]);
$cal_records = $stmt_cal->fetchAll(PDO::FETCH_ASSOC);

$recorded_days = [];
foreach ($cal_records as $row) {
    if ($row['cnt'] >= $daily_dose_target) $recorded_days[] = $row['day'];
}

// 本日の記録スロット
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メディケア・リワード</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 400px; margin: 0 auto; }
        .card { background: white; border-radius: 25px; padding: 30px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); text-align: center; margin-bottom: 20px; }
        .user-info { font-size: 18px; color: #0078d7; font-weight: bold; margin-bottom: 20px; }
        select { padding: 15px; border-radius: 12px; border: 2px solid #0078d7; font-size: 20px; width: 100%; margin-bottom: 20px; }
        #camera-area { width: 100%; margin-bottom: 20px; display: none; }
        video { width: 100%; border-radius: 15px; border: 3px solid #0078d7; background: #000; }
        .btn-main { display: block; width: 100%; padding: 20px; font-size: 24px; font-weight: bold; border-radius: 15px; border: none; cursor: pointer; }
        .btn-orange { background: #ff9800; color: white; box-shadow: 0 5px 0 #e68a00; }
        .btn-green { background: #4CAF50; color: white; box-shadow: 0 5px 0 #2e7d32; }
        .goal-msg { background: #e8f5e9; color: #2e7d32; padding: 25px; border-radius: 15px; font-size: 20px; font-weight: bold; border: 2px solid #2e7d32; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day { aspect-ratio: 1/1; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 8px; font-size: 14px; background: #fafafa; }
        .hanamaru { position: absolute; font-size: 28px; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #ff5252; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>おくすり記録</h1>
        <div class="user-info"><?= htmlspecialchars($current_user_id) ?> さんの記録</div>
        <?php if ($is_goal_achieved): ?>
            <div class="goal-msg">💮 本日は完了です！</div>
        <?php else: ?>
            <form id="recordForm" action="record_process.php" method="post">
                <select name="time" id="timeSelect" required>
                    <?php foreach ($remaining_slots as $slot): ?>
                        <option value="<?= $slot ?>"><?= $slot ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="camera-area"><video id="video" autoplay playsinline></video></div>
                <input type="hidden" name="image_data" id="image_data">
                <button type="button" id="start-btn" class="btn-main btn-orange">📸 写真を撮る</button>
                <button type="button" id="capture-btn" class="btn-main btn-green" style="display:none;">🤳 報告する</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="cal-grid">
            <?php for ($d = 1; $d <= date('t'); $d++): 
                $is_recorded = in_array((string)$d, $recorded_days); ?>
                <div class="cal-day"><?= $d ?><?php if ($is_recorded): ?><span class="hanamaru">💮</span><?php endif; ?></div>
            <?php endfor; ?>
        </div>
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
        const s = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
        video.srcObject = s; cameraArea.style.display = 'block'; captureBtn.style.display = 'block'; startBtn.style.display = 'none';
    });
    captureBtn.addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        imageDataInput.value = canvas.toDataURL('image/jpeg');
        form.submit();
    });
</script>
</body>
</html>