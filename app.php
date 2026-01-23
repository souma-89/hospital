<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// ========== データベース接続設定 ==========
$host = 'localhost';
$db_name = 'medicare_db'; 
$user = 'root'; 
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage()); 
}

// ----------------------------------------------------
// 1. ログアウト処理
// ----------------------------------------------------
if (isset($_GET['logout'])) {
    unset($_SESSION['patient_id']);
    header('Location: app.php');
    exit;
}

$message = '';

// ----------------------------------------------------
// 2. 認証処理（★ここをDB参照に修正★）
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'authenticate') {
    $input_name = trim($_POST['patient_name'] ?? '');
    $input_dob  = trim($_POST['dob'] ?? '');

    // データベースから患者名と生年月日が一致する人を検索
    $stmt_auth = $pdo->prepare("SELECT user_id FROM patients WHERE user_id = ? AND dob = ?");
    $stmt_auth->execute([$input_name, $input_dob]);
    $auth_patient = $stmt_auth->fetch(PDO::FETCH_ASSOC);

    if ($auth_patient) {
        // 一致すればセッションに保存してログイン
        $_SESSION['patient_id'] = $auth_patient['user_id'];
        header('Location: app.php');
        exit;
    } else {
        $message = "❌ 患者名または生年月日が一致しません。";
    }
}

// ----------------------------------------------------
// 3. 認証後のデータ取得
// ----------------------------------------------------
$is_authenticated = false;
$pharmacy_messages = [];
$recent_records = [];
$daily_target = 0;

if (isset($_SESSION['patient_id'])) {
    $demo_user_id = $_SESSION['patient_id'];
    $is_authenticated = true;

    // 患者の目標回数などを取得（★ここもDBから取得★）
    $stmt_p = $pdo->prepare("SELECT daily_target FROM patients WHERE user_id = ?");
    $stmt_p->execute([$demo_user_id]);
    $p_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
    $daily_target = $p_data['daily_target'] ?? 0;

    // 本日の服薬数カウント
    $today_date = date('Y-m-d');
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM medication_records WHERE user_id = ? AND DATE(record_timestamp) = ?");
    $stmt_count->execute([$demo_user_id, $today_date]);
    $today_count = $stmt_count->fetchColumn();
    
    // 薬局からのメッセージ取得
    $stmt_msgs = $pdo->prepare("SELECT id, sender_name, message, created_at, reply_stamp, family_memo FROM family_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_msgs->execute([$demo_user_id]);
    $pharmacy_messages = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);

    // 直近の履歴取得
    $stmt_recent = $pdo->prepare("SELECT record_timestamp, time_slot FROM medication_records WHERE user_id = ? ORDER BY record_timestamp DESC LIMIT 5");
    $stmt_recent->execute([$demo_user_id]);
    $recent_records = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
}

// （以下、スタンプ送信処理やHTML部分は以前のままでOKです）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stamp_action'])) {
    $msg_id = $_POST['msg_id'];
    $stamp = $_POST['stamp_value'];
    $memo = trim($_POST['family_memo'] ?? '');
    $stmt_stamp = $pdo->prepare("UPDATE family_messages SET reply_stamp = ?, family_memo = ? WHERE id = ?");
    $stmt_stamp->execute([$stamp, $memo, $msg_id]);
    header('Location: app.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服薬見守り</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; margin: 0; }
        .app-container { max-width: 500px; margin: 0 auto; background: white; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        header { background: #0078d7; color: white; padding: 15px; text-align: center; margin: -20px -20px 20px -20px; }
        .summary-box { padding: 15px; border-radius: 8px; background: #e8f5e9; border: 1px solid #4caf50; text-align: center; margin-bottom: 20px; }
        .pharmacy-msg-box { background: #ffffff; border-left: 5px solid #0078d7; padding: 15px; margin-bottom: 15px; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .msg-header { display: flex; justify-content: space-between; font-size: 11px; color: #666; margin-bottom: 5px; }
        .msg-body { font-size: 14px; line-height: 1.5; white-space: pre-wrap; margin-bottom: 10px; }
        .memo-input { width: 100%; border: 1px solid #ddd; border-radius: 6px; padding: 8px; font-size: 13px; margin-bottom: 10px; box-sizing: border-box; resize: vertical; }
        .stamp-area { border-top: 1px solid #eee; padding-top: 10px; }
        .stamp-btns { display: flex; gap: 10px; }
        .stamp-btn { flex: 1; padding: 10px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: 0.2s; }
        .stamp-btn:active { background: #eee; transform: scale(0.95); }
        .replied-badge { background: #fff3cd; color: #856404; padding: 10px; border-radius: 10px; font-size: 13px; display: block; border: 1px solid #ffeeba; }
        .family-memo-view { font-size: 12px; color: #666; margin-top: 5px; border-top: 1px dashed #ddd; padding-top: 5px; }
        .auth-form input { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        .auth-btn { width: 100%; padding: 15px; background: #0078d7; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
<div class="app-container">
    <header><h1>💊 服薬見守りアプリ</h1></header>

    <?php if ($is_authenticated): ?>
        <div class="summary-box">
            <strong>本日 (<?= date('m/d') ?>) : <?= $today_count ?> / <?= $daily_target ?> 回</strong>
        </div>

        <h2 style="font-size: 16px; color: #0078d7;">✉️ 薬局からのアドバイス</h2>
        <?php foreach ($pharmacy_messages as $m): ?>
            <div class="pharmacy-msg-box">
                <div class="msg-header">
                    <span>👤 <?= htmlspecialchars($m['sender_name']) ?></span>
                    <span><?= date('m/d H:i', strtotime($m['created_at'])) ?></span>
                </div>
                <div class="msg-body"><?= htmlspecialchars($m['message']) ?></div>
                
                <div class="stamp-area">
                    <?php if ($m['reply_stamp']): ?>
                        <div class="replied-badge">
                            <strong>既読：<?= htmlspecialchars($m['reply_stamp']) ?></strong>
                            <?php if($m['family_memo']): ?>
                                <div class="family-memo-view">相談：<?= htmlspecialchars($m['family_memo']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="stamp_action" value="1">
                            <textarea name="family_memo" class="memo-input" placeholder="薬剤師へ伝えたい変化があれば記入してください"></textarea>
                            <div class="stamp-btns">
                                <button type="submit" name="stamp_value" value="👍 了解！" class="stamp-btn">👍 了解！</button>
                                <button type="submit" name="stamp_value" value="💊 飲みました" class="stamp-btn">💊 飲みました</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <h2 style="font-size: 16px; margin-top: 30px;">📋 直近の履歴</h2>
        <ul style="padding: 0; font-size: 13px; list-style: none;">
            <?php foreach ($recent_records as $r): ?>
                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <?= date('m/d H:i', strtotime($r['record_timestamp'])) ?> - <?= htmlspecialchars($r['time_slot']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p style="text-align:center; margin-top:30px;"><a href="?logout=true" style="color:#999; font-size:12px;">ログアウト</a></p>

    <?php else: ?>
        <form method="POST" class="auth-form" style="margin-top: 50px;">
            <input type="hidden" name="action" value="authenticate">
            <h2 style="text-align:center;">見守りログイン</h2>
            <input type="text" name="patient_name" placeholder="患者名（例：山田きよえ）" required>
            <input type="text" name="dob" placeholder="生年月日（例：1947/05/20）" required>
            <button type="submit" class="auth-btn">認証して開始</button>
            <?php if($message): ?><p style="color:red; font-size:13px; text-align:center;"><?= $message ?></p><?php endif; ?>
        </form>
    <?php endif; ?>
</div>
</body>
</html>