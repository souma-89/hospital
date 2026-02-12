<?php
session_start();
if (!isset($_SESSION['patient_user_id'])) {
    header("Location: login_qr.php");
    exit;
}
$user_id = $_SESSION['patient_user_id'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>本人確認</title>
    <style>
        body { font-family: sans-serif; background: #e3f2fd; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; text-align: center; }
        .card { background: white; width: 90%; max-width: 500px; padding: 40px 20px; border-radius: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .name { font-size: 40px; color: #0078d7; font-weight: bold; border-bottom: 4px solid #0078d7; display: inline-block; margin: 20px 0; }
        /* はいボタン：緑色 */
        .btn-yes { display: block; width: 90%; margin: 20px auto; padding: 30px 0; font-size: 32px; font-weight: bold; color: white; background: #28a745; border-radius: 20px; text-decoration: none; box-shadow: 0 8px 0 #1e7e34; }
        /* ちがうボタン：グレー */
        .btn-no { display: block; width: 60%; margin: 10px auto; padding: 15px 0; font-size: 20px; color: #888; background: #e0e0e0; border-radius: 15px; text-decoration: none; }
        .btn-yes:active { transform: translateY(4px); box-shadow: none; }
    </style>
</head>
<body>
    <div class="card">
        <div style="font-size: 24px;">あなたは</div>
        <div class="name"><?= htmlspecialchars($user_id) ?> さん</div>
        <div style="font-size: 24px; margin-bottom: 30px;">ですか？</div>
        
        <a href="index.php" class="btn-yes">✅ はい</a>
        
        <a href="login_qr.php" class="btn-no">❌ ちがう</a>
    </div>
</body>
</html>