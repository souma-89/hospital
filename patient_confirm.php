<?php
session_start();
// ログイン（QR読み取り）していない場合は戻す
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
    <title>確認 | 中村病院</title>
    <style>
        body { 
            font-family: "Hiragino Sans", "Meiryo", sans-serif; 
            background: #e3f2fd; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            text-align: center; 
        }
        .confirm-card { 
            background: white; 
            width: 90%; 
            max-width: 500px; 
            padding: 50px 20px; 
            border-radius: 40px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
        }
        .msg-top { font-size: 28px; color: #666; margin-bottom: 10px; }
        .user-name { 
            font-size: 48px; 
            color: #0078d7; 
            font-weight: bold; 
            margin: 20px 0; 
            display: block;
            border-bottom: 5px solid #0078d7;
            display: inline-block;
            padding: 0 10px;
        }
        .msg-bottom { font-size: 28px; color: #666; margin-top: 10px; margin-bottom: 40px; }
        
        .btn-group { display: flex; flex-direction: column; gap: 20px; align-items: center; }
        
        /* 「はい」ボタン：めちゃくちゃ大きく */
        .btn-yes { 
            display: block; width: 90%; padding: 30px 0; font-size: 36px; font-weight: bold; 
            color: white; background: #28a745; border: none; border-radius: 20px; text-decoration: none;
            box-shadow: 0 8px 0 #1e7e34; 
        }
        /* 「ちがう」ボタン：控えめに */
        .btn-no { 
            display: block; width: 60%; padding: 15px 0; font-size: 20px; font-weight: bold; 
            color: #888; background: #e0e0e0; border: none; border-radius: 15px; text-decoration: none;
            box-shadow: 0 5px 0 #bcbcbc;
        }
        
        .btn-yes:active, .btn-no:active { transform: translateY(5px); box-shadow: none; }
    </style>
</head>
<body>

<div class="confirm-card">
    <div class="msg-top">あなたは</div>
    
    <div class="user-name">
        <?= htmlspecialchars($user_id) ?> さん
    </div>
    
    <div class="msg-bottom">ですか？</div>

    <div class="btn-group">
        <a href="patient_button.php" class="btn-yes">✅ はい、そうです</a>
        
        <a href="login_qr.php" class="btn-no">❌ ちがいます</a>
    </div>
</div>

</body>
</html>