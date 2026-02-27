<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// 薬剤師ログインチェック
if (!isset($_SESSION['yakuzaishi_login'])) {
    header('Location: login.php');
    exit;
}

/* =====================
    DB設定
===================== */
$host = 'localhost';
$db_name = 'medicare_db';
$user = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

/* =====================
    データ取得とログ記録
===================== */
$patient_id = isset($_GET['id']) ? $_GET['id'] : '';
// ログイン中のIDを確実に数値(INT)として扱う
$current_staff_id = (int)$_SESSION['yakuzaishi_login']; 

$stmt_db = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt_db->execute([$patient_id]);
$p = $stmt_db->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("エラー：指定された患者データが見つかりません。");
}

/* =====================
    ✨ 【バグ完全修正】閲覧ログの記録と前回確認者の特定
===================== */
// 1. 今回のアクセスを先に記録（値を確実に渡す）
try {
    $stmt_audit = $pdo->prepare("INSERT INTO audit_logs (staff_id, patient_id, action_type) VALUES (?, ?, '診察・介入')");
    $stmt_audit->execute([$current_staff_id, $patient_id]);
} catch (PDOException $e) {
    error_log("Audit Log Error: " . $e->getMessage());
}

// 2. 最新から2件取得（1番目は今の自分、2番目が「一歩前の誰か」）
$stmt_get_logs = $pdo->prepare("
    SELECT p.staff_name, a.staff_id
    FROM audit_logs a
    JOIN pharmacists p ON a.staff_id = p.staff_id
    WHERE a.patient_id = ? 
    ORDER BY a.id DESC 
    LIMIT 2
");
$stmt_get_logs->execute([$patient_id]);
$access_logs = $stmt_get_logs->fetchAll(PDO::FETCH_ASSOC);

// 3. 表示名の判定
if (count($access_logs) >= 2) {
    // 2番目のログが「前回の確認者」
    // 厳密に判定するため比較演算子を == に修正
    if ($access_logs[0]['staff_id'] == $current_staff_id) {
        $last_checker_name = $access_logs[1]['staff_name'];
    } else {
        $last_checker_name = $access_logs[0]['staff_name'];
    }
} else {
    $last_checker_name = '（未確認）';
}

/* =====================
    定数・ID生成・メッセージ送信（変更なし）
===================== */
define('UPLOAD_URL', '/hukuyaku/uploads/');

$stmt_rand = $pdo->prepare("SELECT display_id FROM patient_ids WHERE patient_id = ? LIMIT 1");
$stmt_rand->execute([$patient_id]);
$rand_data = $stmt_rand->fetch(PDO::FETCH_ASSOC);
$display_id = $rand_data['display_id'] ?? null;

if (!$display_id) {
    $display_id = strtoupper(bin2hex(random_bytes(4))); 
    $stmt_insert = $pdo->prepare("INSERT INTO patient_ids (patient_id, display_id) VALUES (?, ?)");
    $stmt_insert->execute([$patient_id, $display_id]);
}

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
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);

/* =====================
    服薬記録取得
===================== */
$stmt_records = $pdo->prepare("SELECT * FROM medication_records WHERE user_id = ? AND record_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY record_timestamp DESC");
$stmt_records->execute([$patient_id]);
$med_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

$formatted_records = [];
foreach ($med_records as $row) {
    $date = date('m/d', strtotime($row['record_timestamp']));
    $formatted_records[$date][] = [
        'slot' => $row['time_slot'], 
        'photo' => $row['photo_path'], 
        'time' => date('H:i', strtotime($row['record_timestamp'])), 
        'ai_result' => $row['ai_analysis_result']
    ];
}

$stmt_history = $pdo->prepare("SELECT sender_name, message, created_at FROM family_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt_history->execute([$patient_id]);
$chat_logs = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>患者詳細 | <?= htmlspecialchars($p['user_id']) ?></title>
    <style>
        body { font-family: sans-serif; background:#f8f9fa; margin:0; display:flex; }
        .sidebar { width:260px; background:#0078d7; color:#fff; padding:20px; min-height:100vh; position:fixed; box-sizing: border-box; }
        .main-content { flex:1; margin-left:260px; padding:40px; }
        .card { background:#fff; border-radius:12px; padding:25px; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:25px; }
        .qr-box { background: white; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0; color: #333; }
        .display-id { font-size: 22px; font-weight: bold; color: #0078d7; display: block; margin-top: 5px; }
        .record-table { width:100%; border-collapse:collapse; }
        .record-table th, .record-table td { border-bottom:1px solid #eee; padding:12px; text-align:left; }
        .slot-tag { display:inline-block; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:bold; margin-right:5px; background:#eee; }
        .evidence-img { width:70px; height:50px; object-fit:cover; border-radius:4px; cursor:pointer; border:1px solid #ddd; }
        .btn-send { background:#28a745; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold; }
        .back-btn { display: inline-block; color: white; text-decoration: none; margin-bottom: 20px; font-size: 14px; }
        .ai-badge { display: inline-block; background: #e3f2fd; color: #0d47a1; padding: 3px 10px; border-radius: 20px; font-size: 11px; border: 1px solid #bbdefb; font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="index.php" class="back-btn">← 介入リストに戻る</a>
    <h2>中村病院</h2>
    
    <div style="background:rgba(255,255,255,0.2); padding:10px; border-radius:5px; font-size:14px;">
        👤 最終確認者:<br>
        <strong><?= htmlspecialchars($last_checker_name) ?></strong>
    </div>

    <div class="qr-box">
        <small style="font-weight:bold;">家族アプリ用QR</small><br>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($display_id) ?>" alt="QR" style="margin-top:10px;">
        <span class="display-id"><?= htmlspecialchars($display_id) ?></span>
    </div>
    
    <div style="font-size:13px; opacity:0.8; line-height: 1.6;">
        <strong>患者属性・病歴:</strong><br>
        <?= nl2br(htmlspecialchars(($p['tags'] ?? '') . "\n" . ($p['history'] ?? ''))) ?>
    </div>
</div>

<div class="main-content">
    <?php if($success_msg): ?>
        <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px;"><?= $success_msg ?></div>
    <?php endif; ?>

    <div class="card">
        <h1 style="margin:0;"><?= htmlspecialchars($p['name'] ?? $p['user_id']) ?> 様</h1>
        <p style="color:#666; margin-top:5px;">
            <?= htmlspecialchars($p['dob'] ?? '-----') ?>生 （
            <?php 
                if (!empty($p['dob']) && $p['dob'] !== '0000-00-00') {
                    $birthday = new DateTime($p['dob']);
                    $today = new DateTime('now');
                    echo $birthday->diff($today)->y; 
                } else {
                    echo "年齢不明";
                }
            ?>歳）
        </p>
    </div>

    <div class="card">
        <h3>💊 直近7日間の服薬記録</h3>
        <table class="record-table">
            <thead>
                <tr><th>日付</th><th>区分</th><th>証拠写真</th><th>AI解析</th></tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < 7; $i++): $d = date('m/d', strtotime("-$i days")); ?>
                <tr>
                    <td><?= $d ?></td>
                    <td>
                        <?php if (!empty($formatted_records[$d])): foreach ($formatted_records[$d] as $rec): ?>
                            <div style="margin-bottom:8px;">
                                <span class="slot-tag"><?= htmlspecialchars($rec['slot']) ?></span>
                                <small><?= $rec['time'] ?></small>
                            </div>
                        <?php endforeach; else: ?><span style="color:#ccc;">なし</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($formatted_records[$d])): foreach ($formatted_records[$d] as $rec): if($rec['photo']): ?>
                            <img src="<?= UPLOAD_URL . htmlspecialchars($rec['photo']) ?>" class="evidence-img" onclick="window.open(this.src)">
                        <?php endif; endforeach; endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($formatted_records[$d])): foreach ($formatted_records[$d] as $rec): ?>
                            <div style="margin-bottom:8px;">
                                <?php if ($rec['photo']): ?>
                                    <span class="ai-badge"><?= htmlspecialchars($rec['ai_result'] ?? '解析中...') ?></span>
                                <?php else: ?>
                                    <span style="color:#ccc; font-size:11px;">---</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>家族への連絡</h3>
        <form method="POST">
            <textarea name="report_content" placeholder="診察結果やアドバイスを入力してください" style="width:100%;height:80px;border-radius:8px;border:1px solid #ddd;padding:10px;box-sizing:border-box;"></textarea>
            <div style="text-align:right;margin-top:10px;">
                <button type="submit" name="send_family_app" class="btn-send">内容を確認して送信</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>