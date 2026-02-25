<?php
session_start();

// すでにログイン済みならリスト画面へ
if (isset($_SESSION['yakuzaishi_login'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    // 仮のIDとパスワード（現場に合わせて変えてください）
    if ($user === 'admin' && $pass === 'password') {
        $_SESSION['yakuzaishi_login'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'IDまたはパスワードが違います。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>中村病院 | 薬剤師ログイン</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 350px; text-align: center; }
        .login-card h2 { color: #0078d7; margin-bottom: 25px; }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #0078d7; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .error { color: red; font-size: 14px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>薬剤師ログイン</h2>
        <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="user" placeholder="ユーザーID" required>
            <input type="password" name="pass" placeholder="パスワード" required>
            <button type="submit">ログイン</button>
        </form>
    </div>
</body>
</html>