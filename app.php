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

    // 病院からのメッセージ
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

.content { padding:25px; flex-grow:1; }

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

.summary-card {
    background:var(--main-blue);
    color:white;
    padding:20px;
    border-radius:15px;
    text-align:center;
    margin-bottom:20px;
}

.msg-box {
    background:white;
    border:1px solid #eee;
    border-radius:10px;
    padding:15px;
    margin-bottom:15px;
}

textarea {
    width:100%;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
    margin-top:10px;
}

.reply-btn {
    margin-top:8px;
    padding:8px 15px;
    background:var(--main-green);
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

.logout-link {
    display:block;
    text-align:center;
    margin-top:20px;
    color:#999;
    text-decoration:none;
}

footer {
    text-align:center;
    padding:20px;
    font-size:12px;
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
<?= date('n月j日') ?> 本日 <?= $today_count ?> / <?= $daily_target ?> 回の服用を確認
</div>

<?php foreach ($pharmacy_messages as $m): ?>
<div class="msg-box">
<strong><?= htmlspecialchars($m['sender_name']) ?> 薬剤師</strong><br>
<?= htmlspecialchars($m['message']) ?><br>
<small><?= date('H:i', strtotime($m['created_at'])) ?></small>

<form method="POST">
<input type="hidden" name="action" value="reply">
<textarea name="reply_message" placeholder="病院へ返信する..." required></textarea>
<button type="submit" class="reply-btn">返信する</button>
</form>

</div>
<?php endforeach; ?>

<a href="?logout=true" class="logout-link">ログアウト</a>

<?php else: ?>

<div class="login-card">
<h2>見守りログイン</h2>
<form method="POST">
<input type="hidden" name="action" value="authenticate">
<input type="text" name="patient_id" placeholder="診察券番号" required>
<input type="text" name="patient_name" placeholder="患者さまのお名前" required>
<input type="date" name="dob" required>
<button type="submit" class="btn-auth">認証して開始</button>

<?php if($message): ?>
<p style="color:red; margin-top:15px;"><?= $message ?></p>
<?php endif; ?>

</form>
</div>

<?php endif; ?>

</div>

<footer>
© Nakamura Hospital All Rights Reserved.
</footer>

</div>
</body>
</html>
