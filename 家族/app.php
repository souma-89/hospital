<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

/* ================== データベース接続 ================== */
$host = 'localhost';
$db_name = 'medicare_db';
$user = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー");
}

/* ================== アクセスログ保存 ================== */
function saveAccessLog($pdo, $user_id) {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, user_agent) VALUES (?, ?)");
    $stmt->execute([$user_id, $ua]);
}

/* ================== ログアウト ================== */
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: app.php');
    exit;
}

$message = '';

/* ================== 認証処理 ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'authenticate') {

    $input_display_id = trim($_POST['patient_id'] ?? '');
    $input_name       = trim($_POST['patient_name'] ?? '');
    $input_dob        = trim($_POST['dob'] ?? '');

    $stmt = $pdo->prepare("
        SELECT p.user_id
        FROM patient_ids pid
        INNER JOIN patients p
            ON pid.patient_id = p.user_id
        WHERE pid.display_id = ?
          AND p.user_id = ?
          AND p.dob = ?
        LIMIT 1
    ");

    $stmt->execute([$input_display_id, $input_name, $input_dob]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['patient_id'] = $user['user_id'];
        saveAccessLog($pdo, $user['user_id']);
        header("Location: app.php");
        exit;
    } else {
        $message = "❌ 入力内容に誤りがあります。";
    }
}

/* ================== 返信送信処理 ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {

    if (isset($_SESSION['patient_id'])) {

        $user_id = $_SESSION['patient_id'];
        $reply = trim($_POST['reply_message'] ?? '');

        if ($reply !== '') {
            $stmt = $pdo->prepare("
                INSERT INTO patient_replies (user_id, reply_message)
                VALUES (?, ?)
            ");
            $stmt->execute([$user_id, $reply]);
        }

        header("Location: app.php");
        exit;
    }
}

/* ================== 認証後データ取得 ================== */
$is_authenticated = false;
$pharmacy_messages = [];
$daily_target = 0;
$today_count = 0;

if (isset($_SESSION['patient_id'])) {

    $is_authenticated = true;
    $user_id = $_SESSION['patient_id'];

    // 目標回数
    $stmt = $pdo->prepare("SELECT daily_target FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $daily_target = $stmt->fetchColumn() ?? 0;

    // 今日の服薬回数
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM medication_records
        WHERE user_id = ?
        AND DATE(record_timestamp) = ?
    ");
    $stmt->execute([$user_id, $today]);
    $today_count = $stmt->fetchColumn();

    // 病院からのメッセージ（最新5件）
    $stmt = $pdo->prepare("
        SELECT sender_name, message, created_at
        FROM family_messages
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $pharmacy_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>服薬見守り | 中村病院</title>

<style>
:root { --main-blue:#0056a3; --main-green:#4db33d; --bg-gray:#f8f9fa; }
* { box-sizing: border-box; }

body {
    font-family:"Hiragino Sans","Meiryo",sans-serif;
    background:var(--bg-gray);
    margin:0;
    color:#333;
}

.app-container {
    max-width:500px;
    margin:0 auto;
    background:white;
    min-height:100vh;
    box-shadow:0 0 20px rgba(0,0,0,0.05);
    display:flex;
    flex-direction:column;
}

header {
    padding:20px;
    text-align:center;
    border-bottom:4px solid var(--main-green);
}

.logo-img {
    width:100%;
    max-width:320px;
}

.app-subtitle {
    font-size:16px;
    color:var(--main-blue);
    font-weight:bold;
}

.content { padding:20px; flex-grow:1; }

/* ログインカード */
.login-card h2 { color:var(--main-blue); font-size:20px; margin-bottom:20px; text-align:center; }
.login-card input {
    width:100%;
    padding:15px;
    margin-bottom:15px;
    border-radius:10px;
    border:1px solid #ccc;
    font-size:16px;
}

.btn-auth {
    width:100%;
    padding:15px;
    background:var(--main-blue);
    color:white;
    border:none;
    border-radius:10px;
    font-size:18px;
    font-weight:bold;
    cursor:pointer;
}

/* メイン情報カード */
.summary-card {
    background:var(--main-blue);
    color:white;
    padding:20px;
    border-radius:15px;
    text-align:center;
    margin-bottom:25px;
    font-weight:bold;
}

/* メッセージボックス */
.msg-box {
    background:white;
    border:1px solid #eee;
    border-left:5px solid var(--main-blue);
    border-radius:10px;
    padding:15px;
    margin-bottom:20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.msg-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
}

.msg-sender { color:var(--main-blue); font-weight:bold; font-size:14px; }
.msg-date { font-size:11px; color:#888; background:#f0f2f5; padding:2px 8px; border-radius:10px; }
.msg-text { font-size:15px; line-height:1.6; color:#333; margin-bottom:15px; white-space: pre-wrap; }

/* 返信フォーム */
.reply-area { border-top:1px dashed #ddd; padding-top:10px; }
textarea {
    width:100%;
    padding:12px;
    border-radius:8px;
    border:1px solid #ddd;
    background:#fafafa;
    font-size:14px;
    resize: none;
}

.reply-btn {
    margin-top:8px;
    padding:10px 20px;
    background:var(--main-green);
    color:white;
    border:none;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
    width:100%;
}

.logout-link {
    display:block;
    text-align:center;
    margin:30px 0;
    color:#999;
    text-decoration:none;
    font-size:13px;
}

footer {
    text-align:center;
    padding:20px;
    font-size:11px;
    color:#aaa;
}
</style>
</head>

<body>
<div class="app-container">

<header>
<img src="logo.png" alt="中村病院" class="logo-img">
<div class="app-subtitle">家族用 服薬見守りサービス</div>
</header>

<div class="content">

<?php if ($is_authenticated): ?>

    <div class="summary-card">
        📅 <?= date('n月j日') ?><br>
        本日 <?= $today_count ?> / <?= $daily_target ?> 回の服用を確認済み
    </div>

    <h3 style="font-size:16px; color:var(--main-blue); margin-bottom:15px;">🏥 病院からのメッセージ</h3>

    <?php if (empty($pharmacy_messages)): ?>
        <p style="text-align:center; color:#999; font-size:14px; padding:20px;">現在、新しいメッセージはありません。</p>
    <?php else: ?>
        <?php foreach ($pharmacy_messages as $m): ?>
        <div class="msg-box">
            <div class="msg-header">
                <span class="msg-sender">👨‍⚕️ <?= htmlspecialchars($m['sender_name']) ?></span>
                <span class="msg-date">📅 <?= date('n/j H:i', strtotime($m['created_at'])) ?></span>
            </div>

            <div class="msg-text"><?= htmlspecialchars($m['message']) ?></div>

            <div class="reply-area">
                <form method="POST">
                    <input type="hidden" name="action" value="reply">
                    <textarea name="reply_message" rows="2" placeholder="先生へメッセージを返信する..." required></textarea>
                    <button type="submit" class="reply-btn">返信を送る</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="?logout=true" class="logout-link">ログアウトして認証画面へ戻る</a>

<?php else: ?>

    <div class="login-card">
        <h2>見守りログイン</h2>
        <form method="POST">
            <input type="hidden" name="action" value="authenticate">
            <input type="text" name="patient_id" placeholder="診察券番号" required>
            <input type="text" name="patient_name" placeholder="患者さまのお名前" required>
            <input type="date" name="dob" required title="患者さまの生年月日">
            <button type="submit" class="btn-auth">認証して開始</button>

            <?php if($message): ?>
                <p style="color:red; margin-top:15px; text-align:center; font-weight:bold;"><?= $message ?></p>
            <?php endif; ?>
        </form>
        <p style="margin-top:20px; font-size:12px; color:#666; line-height:1.5;">
            ※病院から発行された「家族用アプリQRコード」からアクセスしてください。
        </p>
    </div>

<?php endif; ?>

</div>

<footer>
© Nakamura Hospital All Rights Reserved.
</footer>

</div>
</body>
</html>