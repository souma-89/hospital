<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// DB接続
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8';
$user = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("接続失敗: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['med_photo'])) {
    $user_id = '山田きよえ'; // index.phpのデモユーザーIDに合わせる
    $time_slot = $_POST['time'] ?? '朝';
    
    // =======================================================
    // 1. AI判定をスキップし、強制的に合格とする（デモ用）
    // =======================================================
    $is_medicine = true; 

    // 2. 合格として処理を続行
    if ($is_medicine) {
        // 画像をuploadsフォルダに保存（フォルダは作成済みのはずです）
        $file_name = time() . "_" . $_FILES['med_photo']['name'];
        move_uploaded_file($_FILES['med_photo']['tmp_name'], "uploads/" . $file_name);

        // DBに保存（photo_pathカラムも活用）
        // photo_path には、uploadsフォルダに保存されたファイル名が入ります。
        $stmt = $pdo->prepare("INSERT INTO medication_records (user_id, time_slot, record_timestamp, photo_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $time_slot, date('Y-m-d H:i:s'), $file_name]);

        // 成功メッセージをindex.phpへリダイレクト
        $_SESSION['recorded_slots_today'][] = $time_slot;
        header("Location: index.php?msg=" . urlencode("【記録完了】服薬写真を記録しました！"));
    } else {
        // スキップモードではこのブロックは通りません
        header("Location: index.php?msg=" . urlencode("【エラー】服薬記録に失敗しました。"));
    }
    exit;
}