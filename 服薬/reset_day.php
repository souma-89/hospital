<?php
// PHPセッションを開始
session_start();

// 記録済みスロットをクリア
$_SESSION['recorded_slots_today'] = [];
// 最後の記録時間（不正防止タイマー）もクリア
$_SESSION['last_record_time'] = 0; 

// 記録画面に戻る
header('Location: index.php?msg=' . urlencode('【開発用】本日の記録をリセットしました。'));
exit;
?>