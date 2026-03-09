<?php
session_start();
// 1. DB接続設定
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("接続エラー");
}

// 2. QR読み取り後のAjax判定処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qr_data = $_POST['qr_id'] ?? '';
    
    // まず patient_ids テーブルの display_id (8桁のランダムID) を探す
    $stmt = $pdo->prepare("SELECT patient_id FROM patient_ids WHERE display_id = ?");
    $stmt->execute([$qr_data]);
    $id_map = $stmt->fetch();

    if ($id_map) {
        // 表示用IDと一致した場合：本来の患者IDをセッションにセット
        $_SESSION['patient_user_id'] = $id_map['patient_id'];
        echo "success";
        exit;
    } else {
        // 直接 patients テーブルの user_id と一致するか確認（予備のチェック）
        $stmt_p = $pdo->prepare("SELECT user_id FROM patients WHERE user_id = ?");
        $stmt_p->execute([$qr_data]);
        $patient = $stmt_p->fetch();

        if ($patient) {
            $_SESSION['patient_user_id'] = $patient['user_id'];
            echo "success";
            exit;
        } else {
            echo "fail";
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ログイン | QRスキャン</title>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        body { font-family: "Hiragino Sans", sans-serif; background: #111; color: white; margin: 0; text-align: center; overflow: hidden; }
        .header { background: #0078d7; padding: 20px; font-size: 20px; font-weight: bold; position: relative; z-index: 10; box-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        
        #preview-container { 
            position: relative; 
            width: 100%; 
            max-width: 500px; 
            margin: 0 auto; 
            aspect-ratio: 1/1; 
            background: #000;
            overflow: hidden;
        }
        video { width: 100%; height: 100%; object-fit: cover; }

        .scan-guide {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 200px; height: 200px;
            border: 4px solid #00d4ff;
            border-radius: 20px;
            box-shadow: 0 0 0 400px rgba(0,0,0,0.6);
            z-index: 5;
        }
        .scan-guide::after {
            content: "QRコードを枠に合わせてください";
            position: absolute;
            bottom: -50px; left: -50px; width: 300px;
            color: #00d4ff; font-size: 14px; font-weight: bold;
        }

        .info { padding: 30px; font-size: 15px; color: #aaa; line-height: 1.6; }
        
        .success-flash {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(40, 167, 69, 0.5);
            z-index: 20;
            display: none;
        }
    </style>
</head>
<body>
    <div id="flash" class="success-flash"></div>
    <div class="header">メディケア・リワード ログイン</div>
    
    <div id="preview-container">
        <video id="video" autoplay playsinline></video>
        <div class="scan-guide"></div>
    </div>

    <div class="info">
        薬局でもらったQRコードを<br>カメラにかざしてください
    </div>
    <canvas id="canvas" style="display:none;"></canvas>

<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const flash = document.getElementById('flash');

    const constraints = {
        video: {
            facingMode: "environment",
            width: { ideal: 1280 },
            height: { ideal: 720 }
        }
    };

    navigator.mediaDevices.getUserMedia(constraints)
        .then(stream => {
            video.srcObject = stream;
            requestAnimationFrame(scan);
        })
        .catch(err => {
            alert("カメラが起動できません。ブラウザのカメラ権限を許可してください。");
        });

    function scan() {
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.height = video.videoHeight;
            canvas.width = video.videoWidth;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert",
            });

            if (code) {
                flash.style.display = 'block';
                document.querySelector('.scan-guide').style.borderColor = "#28a745";
                
                authenticate(code.data);
                return;
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
            if (result.trim() === 'success') {
                // 【修正箇所】patient_confirm.php へ飛ばすように変更
                location.href = 'patient_confirm.php';
            } else {
                alert("エラー：登録されていないQRコードです。");
                location.reload();
            }
        })
        .catch(() => {
            alert("通信エラーが発生しました");
            location.reload();
        });
    }
</script>
</body>
</html>