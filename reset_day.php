<?php
// PHPセッションを開始
session_start();
date_default_timezone_set('Asia/Tokyo');

// --- DB接続設定 (index.phpと同じもの) ---
$dsn = 'mysql:host=localhost;dbname=medicare_db;charset=utf8mb4';
$user = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. セッションの記録をクリア（ボタンを復活させる）
    $_SESSION['recorded_slots_today'] = [];
    $_SESSION['last_record_time'] = 0; 

    // 2. データベースの今日の記録を消去（カレンダーの💮を消す）
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("DELETE FROM medication_records WHERE user_id = '山田きよえ' AND record_timestamp LIKE ?");
    $stmt->execute([$today . '%']);

    // 記録画面に戻る
    header('Location: index.php?msg=' . urlencode('【デモ用】本日の記録をリセットしました。'));
    exit;

} catch (PDOException $e) {
    // エラーが出た場合はメッセージを表示
    header('Location: index.php?msg=' . urlencode('リセット失敗: ' . $e->getMessage()));
    exit;
}
?>