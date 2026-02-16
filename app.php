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
    die("データベース接続エラー"); 
}

// 1. ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['patient_id']);
    header('Location: app.php');
    exit;
}

$message = '';

// 2. 認証処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'authenticate') {
    $input_name = trim($_POST['patient_name'] ?? '');
    $input_dob  = trim($_POST['dob'] ?? '');
    $stmt_auth = $pdo->prepare("SELECT user_id FROM patients WHERE user_id = ? AND dob = ?");
    $stmt_auth->execute([$input_name, $input_dob]);
    $auth_patient = $stmt_auth->fetch(PDO::FETCH_ASSOC);

    if ($auth_patient) {
        $_SESSION['patient_id'] = $auth_patient['user_id'];
        header('Location: app.php');
        exit;
    } else {
        $message = "❌ 患者名または生年月日が一致しません。";
    }
}

// 3. 認証後のデータ取得
$is_authenticated = false;
$pharmacy_messages = [];
$daily_target = 0;

if (isset($_SESSION['patient_id'])) {
    $demo_user_id = $_SESSION['patient_id'];
    $is_authenticated = true;

    $stmt_p = $pdo->prepare("SELECT daily_target FROM patients WHERE user_id = ?");
    $stmt_p->execute([$demo_user_id]);
    $p_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
    $daily_target = $p_data['daily_target'] ?? 0;

    $today_date = date('Y-m-d');
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM medication_records WHERE user_id = ? AND DATE(record_timestamp) = ?");
    $stmt_count->execute([$demo_user_id, $today_date]);
    $today_count = $stmt_count->fetchColumn();
    
    $stmt_msgs = $pdo->prepare("SELECT id, sender_name, message, created_at, reply_stamp, family_memo FROM family_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_msgs->execute([$demo_user_id]);
    $pharmacy_messages = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);
}

// スタンプ送信
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
    <title>服薬見守り | 中村病院</title>
    <style>
        :root { --main-blue: #0056a3; --main-green: #4db33d; --bg-gray: #f8f9fa; }
        body { font-family: "Hiragino Sans", "Meiryo", sans-serif; background: var(--bg-gray); margin: 0; color: #333; }
        .app-container { max-width: 500px; margin: 0 auto; background: white; min-height: 100vh; box-shadow: 0 0 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; }
        
        header { 
            background: #fff; 
            padding: 0 20px 15px; 
            text-align: center; 
            border-bottom: 4px solid var(--main-green); 
            overflow: hidden;
        }
        .logo-container { margin-top: -40px; margin-bottom: -55px; }
        .logo-img { width: 100%; max-width: 380px; height: auto; }
        .app-subtitle { 
            font-size: 17px; color: var(--main-blue); font-weight: bold; letter-spacing: 1.5px;
            position: relative; z-index: 5; background: white; display: inline-block; padding: 5px 15px;
        }

        .content { padding: 25px; flex-grow: 1; }

        /* ログインフォーム */
        .login-card { padding: 10px 0 20px; text-align: center; }
        .login-card h2 { color: var(--main-blue); margin-bottom: 25px; font-size: 24px; }
        .login-card input { 
            width: 100%; padding: 20px; margin-bottom: 15px; 
            border-radius: 15px; border: 2px solid #ddd; 
            box-sizing: border-box; font-size: 18px; 
        }
        .btn-auth { 
            width: 100%; padding: 20px; background: var(--main-blue); 
            color: white; border: none; border-radius: 15px; 
            font-size: 20px; font-weight: bold; cursor: pointer; 
            box-shadow: 0 6px 0 #003a6e; margin-bottom: 25px;
        }
        .contact-note { font-size: 13px; color: #777; line-height: 1.6; margin-top: 10px; }

        /* 認証後の表示 */
        .summary-card { background: linear-gradient(135deg, var(--main-blue), #0078d7); color: white; padding: 30px 20px; border-radius: 20px; text-align: center; margin-bottom: 30px; }
        .summary-card strong { font-size: 28px; }
        .section-title { font-size: 18px; color: var(--main-blue); font-weight: bold; margin: 30px 0 15px; border-left: 5px solid var(--main-green); padding-left: 12px; }
        .msg-box { background: white; border: 1px solid #eee; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .logout-link { display: block; text-align: center; margin-top: 50px; color: #bbb; font-size: 14px; text-decoration: none; padding-bottom: 20px; }
    </style>
</head>
<body>
<div class="app-container">
    <header>
        <div class="logo-container">
            <img src="logo.png" alt="中村病院" class="logo-img">
        </div>
        <div class="app-subtitle">家族用 服薬見守りサービス</div>
    </header>

    <div class="content">
        <?php if ($is_authenticated): ?>
            <div class="summary-card">
                <span><?= date('n月j日') ?> 患者さまの様子</span>
                <strong>本日 <?= $today_count ?> / <?= $daily_target ?> 回の服用を確認</strong>
            </div>

            <div class="section-title">薬剤師からのメッセージ</div>
            <?php foreach ($pharmacy_messages as $m): ?>
                <div class="msg-box">
                    <div class="msg-meta" style="display:flex; justify-content:space-between; font-size:12px; color:#888; margin-bottom:10px;">
                        <span>👤 <?= htmlspecialchars($m['sender_name']) ?> 薬剤師</span>
                        <span><?= date('H:i', strtotime($m['created_at'])) ?></span>
                    </div>
                    <div class="msg-text"><?= htmlspecialchars($m['message']) ?></div>
                    
                    <?php if ($m['reply_stamp']): ?>
                        <div class="badge-done" style="margin-top:15px; background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:12px; font-size:14px;">
                            <strong>✅ 既読：<?= htmlspecialchars($m['reply_stamp']) ?></strong>
                            <?php if($m['family_memo']): ?>
                                <div style="margin-top:8px; border-top:1px solid #c8e6c9; padding-top:8px;">相談：<?= htmlspecialchars($m['family_memo']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" style="margin-top:15px;">
                            <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="stamp_action" value="1">
                            <textarea name="family_memo" style="width:100%; border:1px solid #ddd; border-radius:12px; padding:12px; font-size:14px; margin-bottom:10px; box-sizing:border-box;" placeholder="病院への伝言があれば入力してください"></textarea>
                            <div style="display:flex; gap:10px;">
                                <button type="submit" name="stamp_value" value="了解しました" style="flex:1; padding:15px; border:none; background:#f0f4f8; color:var(--main-blue); border-radius:12px; font-weight:bold; cursor:pointer; border-bottom:3px solid #cedae4;">👍 了解</button>
                                <button type="submit" name="stamp_value" value="確認しました" style="flex:1; padding:15px; border:none; background:#f0f4f8; color:var(--main-blue); border-radius:12px; font-weight:bold; cursor:pointer; border-bottom:3px solid #cedae4;">💊 確認</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <p class="contact-note" style="text-align: center;">※ご不明な点は中村病院にお問い合わせください</p>
            <a href="?logout=true" class="logout-link">ログアウト</a>

        <?php else: ?>
            <div class="login-card">
                <h2>見守りログイン</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="authenticate">
                    <input type="text" name="patient_name" placeholder="患者さまのお名前" required>
                    <input type="text" name="dob" placeholder="生年月日 (例: 1947/05/20)" required>
                    <button type="submit" class="btn-auth">認証して開始</button>
                    <p class="contact-note">※ご不明な点は中村病院にお問い合わせください</p>
                    <?php if($message): ?><p style="color:red; margin-top:20px; font-weight:bold;"><?= $message ?></p><?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>