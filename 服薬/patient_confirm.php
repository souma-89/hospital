<?php
session_start();
// 1. ログインチェック（QRスキャンが終わっていない場合は戻す）
if (!isset($_SESSION['patient_user_id'])) {
    header("Location: login_qr.php");
    exit;
}
$user_id = $_SESSION['patient_user_id'];

// 2. DB接続してお名前を取得
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare("SELECT name FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch();
    $display_name = $patient ? $patient['name'] : $user_id; // 名前がなければIDを表示
} catch (PDOException $e) {
    $display_name = $user_id; // エラー時はIDを表示
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>本人確認</title>
    <style>
        body { font-family: "Hiragino Sans", sans-serif; background: #e3f2fd; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; text-align: center; }
        .card { background: white; width: 85%; max-width: 400px; padding: 40px 20px; border-radius: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .name-label { font-size: 20px; color: #666; }
        .name { font-size: 36px; color: #0078d7; font-weight: bold; border-bottom: 4px solid #0078d7; display: inline-block; margin: 15px 0; }
        .btn-yes { display: block; width: 90%; margin: 30px auto 10px; padding: 25px 0; font-size: 28px; font-weight: bold; color: white; background: #28a745; border-radius: 20px; text-decoration: none; box-shadow: 0 6px 0 #1e7e34; }
        .btn-no { display: block; width: 50%; margin: 20px auto 0; padding: 12px 0; font-size: 16px; color: #888; background: #eee; border-radius: 10px; text-decoration: none; }
        .btn-yes:active { transform: translateY(4px); box-shadow: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="name-label">あなたは</div>
        <div class="name"><?= htmlspecialchars($display_name) ?> さん</div>
        <div class="name-label" style="margin-bottom: 20px;">ですか？</div>
        
        <a href="index.php" class="btn-yes">✅ はい、そうです</a>
        
        <a href="login_qr.php" class="btn-no">❌ ちがいます</a>
    </div>
</body>
</html>