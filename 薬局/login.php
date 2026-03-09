<?php
session_start();

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

// 既にログイン済みならトップへ
if (isset($_SESSION['yakuzaishi_login'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// ログインボタンが押された時
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_id   = $_POST['user_id'] ?? '';
    $input_pass = $_POST['password'] ?? '';

    // 1. データベースから入力されたIDの薬剤師を検索
    $stmt = $pdo->prepare("SELECT * FROM pharmacists WHERE staff_id = ?");
    $stmt->execute([$input_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. 該当者がいて、かつパスワードが一致するか確認
    if ($staff && $input_pass === $staff['password']) {
        
        // セッションにIDと名前を保存
        $_SESSION['yakuzaishi_login'] = $staff['staff_id']; 
        $_SESSION['staff_name']       = $staff['staff_name']; 
        
        header('Location: index.php');
        exit;
    } else {
        $error = 'ユーザーIDまたはパスワードが正しくありません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>薬剤師ログイン | 中村病院</title>
    <style>
        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        h1 {
            color: #0078d7;
            margin-bottom: 30px;
            font-size: 24px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            margin-top: 5px;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #0078d7;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }
        button:hover {
            background-color: #005a9e;
        }
        .error-msg {
            color: #d93025;
            background: #fce8e6;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h1>薬剤師ログイン</h1>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <input type="text" name="user_id" placeholder="ユーザーID" required 
                   value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
        </div>
        <div class="form-group">
            <input type="password" name="password" placeholder="パスワード" required>
        </div>
        <button type="submit">ログイン</button>
    </form>
    
    <p style="margin-top: 25px; font-size: 12px; color: #888;">
        &copy; 2026 中村病院 薬剤部 管理システム
    </p>
</div>

</body>
</html>