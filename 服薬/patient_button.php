<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>中村病院 - 服薬報告</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #eef2f5; margin: 0; padding: 0; text-align: center; color: #333; }
        .header { background: white; padding: 15px; border-bottom: 3px solid #0078d7; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .hospital-logo { height: 50px; vertical-align: middle; }
        .container { padding: 20px; }
        
        .instruction { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #0078d7; }

        /* カメラエリア */
        #camera-wrapper { 
            position: relative; 
            width: 100%; 
            max-width: 350px; 
            margin: 0 auto; 
            border: 5px solid #fff; 
            border-radius: 20px; 
            overflow: hidden; 
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            background: #000;
        }
        video { width: 100%; display: block; transform: scaleX(1); } /* 外カメラ想定なので反転なし */
        
        /* ガイド枠 */
        .camera-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 70%;
            height: 50%;
            border: 2px dashed rgba(255,255,255,0.7);
            border-radius: 10px;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .camera-guide::after {
            content: "ここに薬を映す";
            color: white;
            font-size: 12px;
            background: rgba(0,0,0,0.5);
            padding: 2px 8px;
            border-radius: 4px;
        }

        /* アクションボタン */
        .btn-shutter {
            display: block;
            width: 100%;
            max-width: 350px;
            margin: 20px auto;
            padding: 20px;
            background: #ff4b2b; /* 警告色に近い目立つ赤系 */
            background: linear-gradient(to right, #ff416c, #ff4b2b);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 20px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(255, 75, 43, 0.4);
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-shutter:active { transform: scale(0.98); box-shadow: none; }

        .footer-text { font-size: 12px; color: #777; margin-top: 10px; line-height: 1.5; }
    </style>
</head>
<body>

<div class="header">
    <img src="309e7d17-08e7-40b6-a548-bac5b95d99c5.png" alt="Logo" class="hospital-logo">
    <span style="font-weight: bold; color: #0078d7; margin-left: 10px;">中村病院 服薬確認</span>
</div>

<div class="container">
    <div class="instruction">薬の準備をして<br>ボタンを押してください</div>

    <div id="camera-wrapper">
        <video id="video" autoplay playsinline></video>
        <div class="camera-guide"></div>
    </div>

    <button id="shutter" class="btn-shutter">📸 薬を撮って報告する</button>

    <div class="footer-text">
        ※写真は中村病院の薬剤師へ送信されます。<br>
        間違いがないか、プロが確認するので安心です。
    </div>
</div>

<script>
    const video = document.getElementById('video');
    const shutter = document.getElementById('shutter');

    // カメラ起動（facingMode: environment で背面カメラを優先）
    async function initCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "environment" }, 
                audio: false 
            });
            video.srcObject = stream;
        } catch (err) {
            console.error("カメラ起動エラー:", err);
            alert("カメラの使用を許可してください。");
        }
    }

    shutter.addEventListener('click', () => {
        // シャッター音の代わりの演出
        shutter.style.background = "#4CAF50";
        shutter.innerHTML = "✅ 送信完了！";
        
        // 実際にはここでCanvasに描画してデータ送信するが、デモなのでアラートで終了
        setTimeout(() => {
            alert("服薬データを送信しました。\n今日も一日お大事に！");
            // indexに戻るか、完了画面へ
        }, 800);
    });

    initCamera();
</script>

</body>
</html>