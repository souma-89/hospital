<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

if (!isset($_SESSION['patient_user_id'])) { die("セッションエラー"); }

// DB接続
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';
try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB接続失敗");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['patient_user_id'];
    $time_slot = $_POST['time'] ?? '朝';
    $image_data = $_POST['image_data'] ?? '';

    if (!empty($image_data)) {
        // 1. 画像を一旦保存（APIに投げるため）
        $canvas_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        $image_binary = base64_decode($canvas_data);
        $file_name = time() . "_webcam.jpg";
        $file_path = "uploads/" . $file_name;
        
        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
        file_put_contents($file_path, $image_binary);

        // 2. Everypixel API 呼び出し
        $client_id = 'hna5M9iv1zGS84enwV0yLL9r'; 
        $client_secret = 'ZA6nFSVLPXDNNwineRpwaYUEXxrGey9sQzRPuctmBf81jpD1'; 

        $apiUrl = "https://api.everypixel.com/v1/keywords?num_keywords=15";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$client_id:$client_secret"); // IDとSecretで認証
        
        // 保存したファイルをAPIに送る
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'data' => new CURLFile(realpath($file_path))
        ]);
        
        // SSL証明書エラーを回避
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // 3. 判定ロジック
        $ai_result = "⚠️ AI判定：お薬を確認できません"; // デフォルト
        
        if ($response) {
            $resultData = json_decode($response, true);
            if (isset($resultData['keywords'])) {
                $found_keywords = [];
                foreach ($resultData['keywords'] as $item) {
                    $word = strtolower($item['keyword']);
                    $found_keywords[] = $word;
                    
                    // 薬に関連する英単語（pill, capsule, tablet, medicine）を探す
                    if (in_array($word, ['pill', 'capsule', 'tablet', 'medicine', 'drug'])) {
                        $ai_result = "🔍 AI判定：お薬を確認しました";
                        break;
                    }
                }
                // デバッグ用：何も見つからなかった場合、TOP3のタグをこっそり表示させることも可能
                // if($ai_result === "⚠️ AI判定：お薬を確認できません") { $ai_result .= " (検知: " . implode(',', array_slice($found_keywords, 0, 2)) . ")"; }
            } else {
                $ai_result = "🔍 AI解析エラー(API応答不正)";
            }
        } else {
            $ai_result = "🔍 AI解析失敗(通信エラー: $err)";
        }

        // 4. DB保存
        $stmt = $pdo->prepare("INSERT INTO medication_records (user_id, time_slot, record_timestamp, photo_path, ai_analysis_result) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $time_slot, date('Y-m-d H:i:s'), $file_name, $ai_result]);

        header("Location: index.php?msg=" . urlencode("解析完了！結果を保存しました。"));
        exit;
    }
}