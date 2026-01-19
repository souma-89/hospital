<?php
// PHPセッションを開始
session_start();
date_default_timezone_set('Asia/Tokyo');

// DB接続設定
$host = 'localhost';
$db_name = 'medicare_db'; 
$user = 'root'; 
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage()); 
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user_id = trim($_POST['user_id']);
    $target_count = (int)$_POST['target_count'];

    // 入力チェック
    if (empty($new_user_id) || $target_count < 1 || $target_count > 3) {
        $message = '<div class="error-message">患者名と目標回数を正しく入力してください。</div>';
    } else {
        try {
            // ★修正点★ patients テーブルに新しい患者を挿入
            $stmt = $pdo->prepare("INSERT INTO patients (user_id, daily_target) VALUES (?, ?)");
            $stmt->execute([$new_user_id, $target_count]);
            
            $message = '<div class="success-message">';
            $message .= '【登録完了】新しい患者「' . htmlspecialchars($new_user_id) . '」をデータベースに登録しました！<br>';
            $message .= '目標服薬回数: 1日 ' . $target_count . ' 回。';
            $message .= '</div>';

        } catch (PDOException $e) {
            // user_id が重複した場合の処理 (PRIMARY KEY 違反)
            if ($e->getCode() == 23000) {
                $message = '<div class="error-message">エラー: その患者名（ID）は既に登録されています。</div>';
            } else {
                 $message = '<div class="error-message">登録中に予期せぬエラーが発生しました。</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新規患者登録 | メディケア・リワード</title>
    <style>
        body { font-family: "Segoe UI", "Hiragino Sans", sans-serif; background: #eef2f5; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        h1 { color: #0078d7; border-bottom: 3px solid #0078d7; padding-bottom: 10px; margin-top: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        input[type="text"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        .submit-btn { background-color: #388e3c; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: 700; }
        .submit-btn:hover { background-color: #2e7d32; }
        .back-link { display: block; margin-top: 20px; color: #0078d7; text-decoration: none; font-weight: 600; }
        .success-message { background-color: #e8f5e9; color: #388e3c; padding: 15px; border-radius: 4px; border: 1px solid #388e3c; margin-bottom: 20px; font-weight: 600;}
        .error-message { background-color: #fce4e4; color: #d32f2f; padding: 15px; border-radius: 4px; border: 1px solid #d32f2f; margin-bottom: 20px; font-weight: 600;}
    </style>
</head>
<body>

<div class="container">
    <h1>👤 新規患者登録</h1>
    <?= $message ?>
    
    <form method="POST" action="register_patient.php">
        <div class="form-group">
            <label for="user_id">患者名 (ID):</label>
            <input type="text" id="user_id" name="user_id" required placeholder="例: 吉田けんじ">
        </div>
        
        <div class="form-group">
            <label for="target_count">目標服薬回数 (1日):</label>
            <select id="target_count" name="target_count" required>
                <option value="">選択してください</option>
                <option value="1">1回</option>
                <option value="2">2回</option>
                <option value="3">3回</option>
            </select>
        </div>
        
        <button type="submit" class="submit-btn">患者を登録する</button>
    </form>
    
    <a href="index.php" class="back-link">← 介入優先リストへ戻る</a>
</div>

</body>
</html>