<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// DB接続
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("接続失敗: " . $e->getMessage());
}

// POSTデータの確認
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = '山田きよえ'; 
    $time_slot = $_POST['time'] ?? '朝';
    $image_data = $_POST['image_data'] ?? ''; // JSから飛んでくるBase64データ

    if (!empty($image_data)) {
        // --- Base64デコード処理 ---
        // data:image/jpeg;base64, の部分を削る
        $canvas_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        $canvas_data = str_replace(' ', '+', $canvas_data);
        $image_binary = base64_decode($canvas_data);

        // uploadsフォルダの準備
        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }

        // ファイル名の生成
        $file_name = time() . "_webcam.jpg";
        $file_path = "uploads/" . $file_name;

        // バイナリデータを保存
        if (file_put_contents($file_path, $image_binary)) {
            
            // --- DBに保存 ---
            $stmt = $pdo->prepare("INSERT INTO medication_records (user_id, time_slot, record_timestamp, photo_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $time_slot, date('Y-m-d H:i:s'), $file_name]);

            // --- セッションに記録を追加（カレンダー表示用） ---
            if (!isset($_SESSION['recorded_slots_today'])) {
                $_SESSION['recorded_slots_today'] = [];
            }
            if (!in_array($time_slot, $_SESSION['recorded_slots_today'])) {
                $_SESSION['recorded_slots_today'][] = $time_slot;
            }

            header("Location: index.php?msg=" . urlencode("【記録完了】カメラで{$time_slot}の服薬を確認しました！"));
            exit;
        }
    }
    
    // データが空だったり失敗した場合
    header("Location: index.php?msg=" . urlencode("【エラー】画像データを受信できませんでした。"));
    exit;
}