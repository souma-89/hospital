<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

if (!isset($_SESSION['patient_user_id'])) { 
    die("セッションエラー：ログインし直してください。"); 
}

// DB接続
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("DB接続失敗");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['patient_user_id'];
    $time_slot = $_POST['time'] ?? '朝';
    $image_data = $_POST['image_data'] ?? '';

    if (!empty($image_data)) {
        // 1. 画像保存
        $canvas_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        $image_binary = base64_decode($canvas_data);
        $file_name = time() . "_" . $user_id . "_webcam.jpg";
        $file_path = "uploads/" . $file_name;
        
        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
        file_put_contents($file_path, $image_binary);

        // 2. API解析 (Everypixel)
        $client_id = 'hna5M9iv1zGS84enwV0yLL9r'; 
        $client_secret = 'ZA6nFSVLPXDNNwineRpwaYUEXxrGey9sQzRPuctmBf81jpD1'; 
        $apiUrl = "https://api.everypixel.com/v1/keywords?num_keywords=15";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$client_id:$client_secret");
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['data' => new CURLFile(realpath($file_path))]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $ai_result = "⚠️ AI判定：お薬を確認できません";
        if ($response) {
            $resultData = json_decode($response, true);
            if (isset($resultData['keywords'])) {
                foreach ($resultData['keywords'] as $item) {
                    $word = strtolower($item['keyword']);
                    if (in_array($word, ['pill', 'capsule', 'tablet', 'medicine', 'drug'])) {
                        $ai_result = "🔍 AI判定：お薬を確認しました";
                        break;
                    }
                }
            }
        }

        // 3. 【修正の核心】idカラムを使わずに「日付・ユーザー・時間帯」で上書き判定
        $today_start = date('Y-m-d 00:00:00');
        $today_end   = date('Y-m-d 23:59:59');

        // 今日の同じ時間帯に記録があるかチェック
        $check_sql = "SELECT photo_path FROM medication_records 
                      WHERE user_id = ? AND time_slot = ? 
                      AND record_timestamp BETWEEN ? AND ?";
        $stmt_check = $pdo->prepare($check_sql);
        $stmt_check->execute([$user_id, $time_slot, $today_start, $today_end]);
        $existing = $stmt_check->fetch();

        if ($existing) {
            // すでに記録があれば「UPDATE」 (条件にidを使わず、複合条件で指定)
            if (file_exists("uploads/" . $existing['photo_path'])) {
                unlink("uploads/" . $existing['photo_path']);
            }
            $update_sql = "UPDATE medication_records 
                           SET record_timestamp = NOW(), photo_path = ?, ai_analysis_result = ? 
                           WHERE user_id = ? AND time_slot = ? 
                           AND record_timestamp BETWEEN ? AND ?";
            $stmt_update = $pdo->prepare($update_sql);
            $stmt_update->execute([$file_name, $ai_result, $user_id, $time_slot, $today_start, $today_end]);
        } else {
            // なければ「INSERT」
            $insert_sql = "INSERT INTO medication_records 
                           (user_id, time_slot, record_timestamp, photo_path, ai_analysis_result) 
                           VALUES (?, ?, NOW(), ?, ?)";
            $stmt_insert = $pdo->prepare($insert_sql);
            $stmt_insert->execute([$user_id, $time_slot, $file_name, $ai_result]);
        }

        header("Location: index.php?msg=" . urlencode("記録を保存しました。"));
        exit;
    }
}