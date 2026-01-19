<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// ========== データベース接続設定 ==========
$host = 'localhost';
$db_name = 'medicare_db'; 
$user = 'root'; 
$password = ''; 

try {
    // ★文字化け対策（絵文字対応）のため utf8mb4 に変更
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage()); 
}

// ----------------------------------------------------
// 【デモ用】患者情報リスト
// ----------------------------------------------------
$patient_info = [
    '山田きよえ' => ['dob' => '1947/05/20', 'daily_target' => 3],
    '高橋誠一郎' => ['dob' => '1943/01/15', 'daily_target' => 3],
    '田中まさる' => ['dob' => '1943/01/15', 'daily_target' => 3],
    '鈴木いちろう' => ['dob' => '1960/10/01', 'daily_target' => 2],
    '佐藤はな' => ['dob' => '1955/08/25', 'daily_target' => 1],
    '高橋ゆうこ' => ['dob' => '1970/04/10', 'daily_target' => 2],
    '小林たろう' => ['dob' => '1980/09/01', 'daily_target' => 2],
    '木村はるか' => ['dob' => '1963/05/18', 'daily_target' => 3],
    '西村じゅん' => ['dob' => '1951/12/25', 'daily_target' => 1],
    '松田あきら' => ['dob' => '1967/02/03', 'daily_target' => 2],
    '川口さなえ' => ['dob' => '1957/10/10', 'daily_target' => 3],
    '山中けんた' => ['dob' => '1975/01/15', 'daily_target' => 2]
];

$message = '';
$is_authenticated = false;

// 1. ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['patient_id']);
    header('Location: app.php');
    exit;
}

// 2. 認証処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'authenticate') {
    $input_name = trim($_POST['patient_name'] ?? '');
    $input_dob = trim($_POST['dob'] ?? '');
    if (isset($patient_info[$input_name]) && $patient_info[$input_name]['dob'] === $input_dob) {
        $_SESSION['patient_id'] = $input_name;
        header('Location: app.php'); // 認証後もリダイレクト
        exit;
    } else {
        $message = "❌ 患者名または生年月日が一致しません。";
    }
}

// ★追加：スタンプ送信処理（二重送信防止リダイレクト版）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stamp_action'])) {
    $msg_id = $_POST['msg_id'];
    $stamp = $_POST['stamp_value'];
    $stmt_stamp = $pdo->prepare("UPDATE family_messages SET reply_stamp = ? WHERE id = ?");
    $stmt_stamp->execute([$stamp, $msg_id]);
    
    // 送信後にリダイレクトしてPOSTをクリア
    header('Location: app.php');
    exit;
}

// 3. 認証状態の確認
if (isset($_SESSION['patient_id'])) {
    $demo_user_id = $_SESSION['patient_id'];
    $is_authenticated = true;
    $daily_target = $patient_info[$demo_user_id]['daily_target'] ?? 0;
}

// 4. データ取得
$pharmacy_messages = [];
$recent_records = [];
if ($is_authenticated) {
    $today_date = date('Y-m-d');
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM medication_records WHERE user_id = ? AND DATE(record_timestamp) = ?");
    $stmt_count->execute([$demo_user_id, $today_date]);
    $today_count = $stmt_count->fetchColumn();
    
    // 薬局からのメッセージを取得
    $stmt_msgs = $pdo->prepare("SELECT id, sender_name, message, created_at, reply_stamp FROM family_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_msgs->execute([$demo_user_id]);
    $pharmacy_messages = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);

    // 直近履歴
    $stmt_recent = $pdo->prepare("SELECT record_timestamp, time_slot FROM medication_records WHERE user_id = ? ORDER BY record_timestamp DESC LIMIT 5");
    $stmt_recent->execute([$demo_user_id]);
    $recent_records = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
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
        .stamp-area { border-top: 1px solid #eee; padding-top: 10px; display: flex; gap: 10px; }
        .stamp-btn { flex: 1; padding: 10px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: 0.2s; }
        .stamp-btn:active { background: #eee; transform: scale(0.95); }
        .replied-badge { background: #fff3cd; color: #856404; padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: bold; border: 1px solid #ffeeba; }
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
        <?php if (empty($pharmacy_messages)): ?>
            <p style="text-align:center; color:#999; font-size:14px;">メッセージはありません。</p>
        <?php endif; ?>

        <?php foreach ($pharmacy_messages as $m): ?>
            <div class="pharmacy-msg-box">
                <div class="msg-header">
                    <span>👤 <?= htmlspecialchars($m['sender_name']) ?></span>
                    <span><?= date('m/d H:i', strtotime($m['created_at'])) ?></span>
                </div>
                <div class="msg-body"><?= htmlspecialchars($m['message']) ?></div>
                
                <div class="stamp-area">
                    <?php if ($m['reply_stamp']): ?>
                        <span class="replied-badge">既読：<?= htmlspecialchars($m['reply_stamp']) ?></span>
                    <?php else: ?>
                        <form method="POST" style="display: flex; width: 100%; gap: 10px;">
                            <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="stamp_action" value="1">
                            <button type="submit" name="stamp_value" value="👍 了解！" class="stamp-btn">👍 了解！</button>
                            <button type="submit" name="stamp_value" value="💊 飲みました" class="stamp-btn">💊 飲みました</button>
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