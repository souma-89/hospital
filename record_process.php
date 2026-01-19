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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['med_photo'])) {
    $user_id = '山田きよえ'; 
    $time_slot = $_POST['time'] ?? '朝';
    
    // 二重登録チェック（同じ区分の連打防止）
    if (!isset($_SESSION['recorded_slots_today'])) {
        $_SESSION['recorded_slots_today'] = [];
    }

    // AI判定スキップ（デモ用）
    $is_medicine = true; 

    if ($is_medicine) {
        // uploadsフォルダがない場合は作成
        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }

        $file_name = time() . "_" . $_FILES['med_photo']['name'];
        move_uploaded_file($_FILES['med_photo']['tmp_name'], "uploads/" . $file_name);

        // DBに保存
        $stmt = $pdo->prepare("INSERT INTO medication_records (user_id, time_slot, record_timestamp, photo_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $time_slot, date('Y-m-d H:i:s'), $file_name]);

        // セッションに記録を追加
        if (!in_array($time_slot, $_SESSION['recorded_slots_today'])) {
            $_SESSION['recorded_slots_today'][] = $time_slot;
        }

        header("Location: index.php?msg=" . urlencode("【記録完了】{$time_slot}の服薬を記録しました！"));
    } else {
        header("Location: index.php?msg=" . urlencode("【エラー】服薬記録に失敗しました。"));
    }
    exit;
}