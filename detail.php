<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

/* =====================
   DB設定
===================== */
$host = 'localhost';
$db_name = 'medicare_db';
$user = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

/* =====================
   定数定義
===================== */
define('UPLOAD_URL', '/hukuyaku/uploads/');

/* =====================
   患者ID取得
===================== */
$patient_id = isset($_GET['id']) ? urldecode($_GET['id']) : '';

$stmt_db = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt_db->execute([$patient_id]);
$p = $stmt_db->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("患者データが見つかりません。");
}

/* =====================
   家族へのメッセージ送信処理 (追加分)
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_family_app'])) {
    $report_content = $_POST['report_content'] ?? '';
    if (!empty($report_content)) {
        $stmt_send = $pdo->prepare("INSERT INTO family_messages (user_id, sender_name, message) VALUES (?, '中村病院 薬剤部', ?)");
        $stmt_send->execute([$patient_id, $report_content]);
        
        $_SESSION['success_msg'] = "✅ 家族用アプリへメッセージを送信しました！";
        header("Location: detail.php?id=" . urlencode($patient_id));
        exit;
    }
}

$success_msg = '';
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

/* =====================
   表示用データの取得
===================== */
// 1. 服薬記録
$stmt_records = $pdo->prepare(
    "SELECT time_slot, record_timestamp, photo_path
     FROM medication_records
     WHERE user_id = ?
       AND record_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY record_timestamp DESC"
);
$stmt_records->execute([$patient_id]);
$med_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

$formatted_records = [];
foreach ($med_records as $row) {
    $date = date('m/d', strtotime($row['record_timestamp']));
    $formatted_records[$date][] = [
        'slot'  => $row['time_slot'],
        'photo' => $row['photo_path'],
        'time'  => date('H:i', strtotime($row['record_timestamp']))
    ];
}

// 2. 家族への送信履歴 (追加分)
$stmt_history = $pdo->prepare("SELECT message, created_at FROM family_messages WHERE user_id = ? AND sender_name = '中村病院 薬剤部' ORDER BY created_at DESC LIMIT 3");
$stmt_history->execute([$patient_id]);
$send_logs = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>患者詳細 | <?= htmlspecialchars($p['user_id']) ?></title>

<style>
body {
    font-family: "Helvetica Neue", Arial, sans-serif;
    background: #f8f9fa;
    margin: 0;
    display: flex;
}

.sidebar {
    width: 250px;
    background: #0078d7;
    color: #fff;
    padding: 20px;
    min-height: 100vh;
    position: fixed;
}

.main-content {
    flex: 1;
    margin-left: 250px;
    padding: 40px;
}

.card {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.record-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.record-table th,
.record-table td {
    border-bottom: 1px solid #eee;
    padding: 16px;
    vertical-align: middle;
}

.record-table th {
    color: #666;
    font-size: 14px;
    text-align: left;
}

.record-table th:nth-child(1), .record-table td:nth-child(1) { width: 90px; }
.record-table th:nth-child(2), .record-table td:nth-child(2) { width: 220px; }

.evidence-img {
    width: 80px; height: 60px;
    object-fit: cover; border-radius: 6px; border: 1px solid #ddd;
    cursor: pointer; transition: 0.2s;
}

.evidence-img:hover { transform: scale(1.1); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }

.slot-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
.slot-morning { background: #e3f2fd; color: #1976d2; }
.slot-noon    { background: #fff3e0; color: #f57c00; }
.slot-evening { background: #f3e5f5; color: #7b1fa2; }

/* 追加分のスタイル */
.btn-send { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
.btn-send:hover { background: #218838; }
.history-item { border-bottom: 1px solid #eee; padding: 10px 0; font-size: 13px; }
.success-banner { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
</style>
</head>

<body>

<div class="sidebar">
    <h2>中村病院</h2>
    <p>患者ID: <?= htmlspecialchars($p['user_id']) ?></p>
    <hr>
    <strong>病歴</strong><br>
    <?= nl2br(htmlspecialchars($p['history'] ?? 'なし')) ?>
</div>

<div class="main-content">

    <?php if($success_msg): ?>
        <div class="success-banner"><?= $success_msg ?></div>
    <?php endif; ?>

    <div class="card">
        <h1><?= htmlspecialchars($p['user_id']) ?> 様</h1>
        <p>生年月日: <?= htmlspecialchars($p['dob']) ?>（<?= (int)$p['age'] ?>歳）</p>
    </div>

    <div class="card" style="border-left: 5px solid #28a745;">
        <h3 style="color: #28a745; margin-top: 0;">👨‍👩‍👧 家族用アプリへの連絡</h3>
        <form action="" method="POST">
            <textarea name="report_content" placeholder="家族へ伝えるメッセージを入力してください..." 
                      style="width: 100%; height: 80px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box;"></textarea>
            <div style="text-align: right; margin-top: 10px;">
                <button type="submit" name="send_family_app" class="btn-send">家族アプリへ送信</button>
            </div>
        </form>

        <div style="margin-top: 20px; background: #fdfdfd; padding: 15px; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #666;">最近の送信履歴</h4>
            <?php if ($send_logs): foreach($send_logs as $log): ?>
                <div class="history-item">
                    <small style="color:#999;"><?= date('m/d H:i', strtotime($log['created_at'])) ?></small><br>
                    <?= nl2br(htmlspecialchars($log['message'])) ?>
                </div>
            <?php endforeach; else: ?>
                <p style="font-size: 12px; color: #ccc;">履歴はありません</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3>💊 服薬状況（証拠写真）</h3>
        <table class="record-table">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>区分</th>
                    <th>証拠写真</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < 7; $i++): $d = date('m/d', strtotime("-$i days")); ?>
                <tr>
                    <td><?= $d ?></td>
                    <td>
                        <?php if (!empty($formatted_records[$d])): ?>
                            <?php foreach ($formatted_records[$d] as $rec):
                                $slot_class = '';
                                if (str_contains($rec['slot'], '朝')) $slot_class = 'slot-morning';
                                elseif (str_contains($rec['slot'], '昼')) $slot_class = 'slot-noon';
                                elseif (str_contains($rec['slot'], '夜')) $slot_class = 'slot-evening';
                            ?>
                            <div style="margin-bottom: 5px;">
                                <span class="slot-tag <?= $slot_class ?>"><?= htmlspecialchars($rec['slot']) ?></span>
                                <small><?= $rec['time'] ?></small>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:#ccc;">記録なし</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php if (!empty($formatted_records[$d])): ?>
                            <?php foreach ($formatted_records[$d] as $rec): ?>
                                <?php if (!empty($rec['photo'])): ?>
                                <img src="<?= UPLOAD_URL . rawurlencode($rec['photo']) ?>?v=<?= time() ?>"
                                     class="evidence-img" onclick="window.open(this.src)" onerror="this.style.display='none';">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>