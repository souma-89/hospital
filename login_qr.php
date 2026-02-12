<?php
session_start();
// DB接続設定（フォルダ名に合わせて微調整が必要な場合はここを確認）
$host = 'localhost';
$db_name = 'medicare_db';
$user = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $password);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}

// QR読み取り後の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qr_data = $_POST['qr_id'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$qr_data]);
    $patient = $stmt->fetch();

    if ($patient) {
        $_SESSION['patient_user_id'] = $patient['user_id'];
        echo "success";
        exit;
    } else {
        echo "fail";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRログイン</title>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        body { font-family: sans-serif; background: #333; color: white; margin: 0; text-align: center; }
        .header { background: #0078d7; padding: 25px; font-size: 28px; font-weight: bold; }
        #preview { width: 90%; max-width: 500px; margin: 20px auto; border: 5px solid #fff; border-radius: 20px; overflow: hidden; position: relative; }
        video { width: 100%; display: block; }
        .info { font-size: 20px; margin-top: 20px; padding: 0 20px; }
    </style>
</head>
<body>

<div class="header">お薬 アプリ</div>
<div class="info">QRコードを<br>カメラに写して下さい</div>

<div id="preview">
    <video id="video" autoplay playsinline></video>
</div>

<canvas id="canvas" style="display:none;"></canvas>

<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');

    // カメラ起動
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
        .then(stream => {
            video.srcObject = stream;
            requestAnimationFrame(scan);
        })
        .catch(err => {
            console.error(err);
            alert("カメラが見つかりません。設定で許可してください。");
        });

    function scan() {
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.height = video.videoHeight;
            canvas.width = video.videoWidth;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);

            if (code) {
                // 成功したら本人確認画面へ飛ばす
                authenticate(code.data);
                return; // スキャン停止
            }
        }
        requestAnimationFrame(scan);
    }

    function authenticate(qrId) {
        const formData = new FormData();
        formData.append('qr_id', qrId);
        fetch('login_qr.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(result => {
            if (result === 'success') {
                // ここを「本人確認画面」のファイル名にする
                location.href = 'patient_confirm.php'; 
            } else {
                alert("登録されていないQRです");
                location.reload();
            }
        });
    }
</script>
</body>
</html>