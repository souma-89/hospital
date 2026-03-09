<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// DB接続
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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user_id = trim($_POST['user_id']);
    $dob = trim($_POST['dob']);
    $age = (int)$_POST['age'];
    $tel = trim($_POST['tel']);
    $target_count = (int)$_POST['target_count'];
    $history = trim($_POST['history']); // ★追加：病歴データの受け取り

    if (empty($new_user_id) || empty($dob) || $target_count < 1) {
        $message = '<div class="error-message">必須項目を入力してください。</div>';
    } else {
        try {
            // ★修正点：SQLの項目に history を追加
            $stmt = $pdo->prepare("INSERT INTO patients (user_id, dob, age, tel, daily_target, history) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$new_user_id, $dob, $age, $tel, $target_count, $history]);
            
            $message = '<div class="success-message">患者「' . htmlspecialchars($new_user_id) . '」を登録しました！</div>';
        } catch (PDOException $e) {
            $message = '<div class="error-message">登録エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新規患者登録</title>
    <style>
        body { font-family: sans-serif; background: #eef2f5; padding: 20px; }
        .container { max-width: 550px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 15px; }
        textarea { resize: vertical; }
        .flex-row { display: flex; gap: 10px; }
        .submit-btn { width: 100%; background: #388e3c; color: white; padding: 12px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .submit-btn:hover { background: #2e7d32; }
        .success-message { color: #388e3c; background: #e8f5e9; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #388e3c; }
        .error-message { color: #d32f2f; background: #fce4e4; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #d32f2f; }
    </style>
</head>
<body>

<div class="container">
    <h1>👤 新規患者登録</h1>
    <?= $message ?>
    
    <form method="POST">
        <div class="form-group">
            <label>患者名 (ID):</label>
            <input type="text" name="user_id" required placeholder="例: 川口さなえ">
        </div>

        <div class="flex-row">
            <div class="form-group" style="flex: 2;">
                <label>生年月日:</label>
                <input type="text" id="dob" name="dob" required placeholder="1946/07/21">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>年齢:</label>
                <input type="number" id="age" name="age" required placeholder="79">
            </div>
        </div>

        <div class="form-group">
            <label>連絡先電話番号:</label>
            <input type="tel" name="tel" placeholder="080-3399-5522">
        </div>

        <div class="form-group">
            <label>病歴・処方薬情報:</label>
            <textarea name="history" rows="4" placeholder="例：高血圧、不眠症 / アムロジピン、ゾルピデム等"></textarea>
        </div>
        
        <div class="form-group">
            <label>目標服薬回数 (1日):</label>
            <select name="target_count" required>
                <option value="3">3回</option>
                <option value="2">2回</option>
                <option value="1">1回</option>
            </select>
        </div>
        
        <button type="submit" class="submit-btn">この内容で登録する</button>
    </form>
    <p style="text-align:center;"><a href="index.php" style="text-decoration:none; color:#0078d7;">← 戻る</a></p>
</div>

<script>
document.getElementById('dob').addEventListener('blur', function() {
    const dobValue = this.value.trim();
    const dobDate = new Date(dobValue.replace(/\//g, '-'));

    if (!isNaN(dobDate.getTime())) {
        const today = new Date();
        let age = today.getFullYear() - dobDate.getFullYear();
        const m = today.getMonth() - dobDate.getMonth();

        if (m < 0 || (m === 0 && today.getDate() < dobDate.getDate())) {
            age--;
        }

        if (age >= 0) {
            document.getElementById('age').value = age;
        }
    }
});
</script>

</body>
</html>