<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// ========== データベース接続設定 ==========
$host = 'localhost';
$db_name = 'medicare_db'; 
$user = 'root'; 
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage()); 
}

// ----------------------------------------------------
// 1. 患者IDの取得
// ----------------------------------------------------
$patient_id = isset($_GET['id']) ? urldecode($_GET['id']) : '山田きよえ';

// ----------------------------------------------------
// 2. 家族用アプリへの送信処理
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_family_app'])) {
    $stmt_send = $pdo->prepare("INSERT INTO family_messages (user_id, sender_name, message) VALUES (?, 'メディケア薬局 薬剤師', ?)");
    $stmt_send->execute([$patient_id, $_POST['report_content']]);
    
    $_SESSION['success_msg'] = "✅ 家族用アプリへメッセージを送信しました！";
    header("Location: detail.php?id=" . urlencode($patient_id));
    exit;
}

$success_msg = '';
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// ----------------------------------------------------
// 3. 患者データをデータベースから取得（★ここを修正★）
// ----------------------------------------------------
// 古い $patient_list (手書きリスト) は完全に削除し、DBのみを参照します
$stmt_db = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt_db->execute([$patient_id]);
$p = $stmt_db->fetch(PDO::FETCH_ASSOC);

// 万が一DBにデータがない場合の予備処理
if (!$p) {
    $p = [
        'user_id' => $patient_id,
        'dob' => '1944/01/01',
        'age' => 80,
        'history' => '未登録',
        'tel' => '不明',
        'tags' => '未設定',
        'daily_target' => 3
    ];
}

// HTML側で使われている変数名（birth）に DBの値（dob）を合わせる
$p['birth'] = $p['dob'];

// タグを配列にする
$tags = explode(',', $p['tags'] ?? '独居,足腰が不自由');

// ----------------------------------------------------
// 4. 家族からの返信履歴を取得
// ----------------------------------------------------
$stmt_replies = $pdo->prepare("SELECT reply_stamp, family_memo, created_at FROM family_messages WHERE user_id = ? AND (reply_stamp IS NOT NULL OR family_memo IS NOT NULL) ORDER BY created_at DESC LIMIT 5");
$stmt_replies->execute([$patient_id]);
$family_replies = $stmt_replies->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>患者詳細 | <?= htmlspecialchars($p['user_id']) ?></title>
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f8f9fa; margin: 0; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #0078d7; color: white; padding: 25px; box-sizing: border-box; flex-shrink: 0; }
        .sidebar-section { margin-bottom: 30px; }
        .sidebar-section h3 { font-size: 15px; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 5px; }
        .tag-badge { background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px; font-size: 11px; margin-right: 4px; display: inline-block; margin-bottom: 4px; }
        .sidebar-info { font-size: 13px; line-height: 1.6; }

        .main-content { flex: 1; padding: 30px 40px; box-sizing: border-box; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e1e4e8; }
        
        .alert-card { border: 2px solid #ffcc00; background: #fffdf0; }
        .memo-text { font-size: 16px; color: #d44917; font-weight: bold; background: #fff; padding: 10px; border-radius: 5px; border: 1px solid #ffeeba; margin-top: 5px; }

        .patient-name { font-size: 26px; font-weight: bold; margin: 0; }
        .patient-birth { font-size: 18px; color: #666; font-weight: normal; margin-left: 10px; }
        .patient-meta { color: #444; font-size: 14px; margin-top: 10px; line-height: 1.6; }
        .section-title { font-size: 17px; color: #0078d7; margin-bottom: 15px; border-left: 4px solid #0078d7; padding-left: 10px; }
        
        .report-card { border: 2px dashed #0078d7; background: #f0f7ff; }
        textarea { width: 100%; height: 120px; border: 1px solid #ddd; border-radius: 6px; padding: 12px; font-size: 14px; margin-top: 10px; box-sizing: border-box; }
        .btn-send { background: #0078d7; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; cursor: pointer; float: right; margin-top: 10px; }
        
        .reply-item { background: #fff; border: 1px solid #eee; padding: 12px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; }
        .reply-stamp { display: inline-block; background: #fff3cd; color: #856404; padding: 2px 10px; border-radius: 10px; font-weight: bold; font-size: 12px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-section">
            <h3>属性タグ</h3>
            <div class="tag-container">
                <?php foreach($tags as $t): if(trim($t) !== ''): ?>
                    <span class="tag-badge"><?= htmlspecialchars(trim($t)) ?></span>
                <?php endif; endforeach; ?>
            </div>
        </div>
        <div class="sidebar-section">
            <h3>病歴・処方内容</h3>
            <div class="sidebar-info"><?= nl2br(htmlspecialchars($p['history'])) ?></div>
        </div>
    </div>

    <div class="main-content">
        <?php if($success_msg): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px;"><?= $success_msg ?></div>
        <?php endif; ?>

        <div class="card">
            <h1 class="patient-name">
                <?= htmlspecialchars($p['user_id']) ?> 
                <span class="patient-birth">(<?= htmlspecialchars($p['birth']) ?>生 / <?= $p['age'] ?> 歳)</span>
            </h1>
            <div class="patient-meta">
                <strong>連絡先:</strong> <?= htmlspecialchars($p['tel']) ?>
            </div>
        </div>

        <div class="card alert-card">
            <h3 class="section-title" style="color: #856404; border-left-color: #ffcc00;">⚠️ 家族からの気になる報告（最新）</h3>
            <?php 
            $has_memo = false;
            foreach($family_replies as $r): 
                if(!empty($r['family_memo'])): 
                    $has_memo = true;
            ?>
                <div class="reply-item" style="border-left: 4px solid #ffcc00;">
                    <span style="color: #666; font-size: 12px;"><?= date('m/d H:i', strtotime($r['created_at'])) ?> の相談：</span>
                    <div class="memo-text">「<?= htmlspecialchars($r['family_memo']) ?>」</div>
                </div>
            <?php 
                break; 
                endif; 
            endforeach; 
            if(!$has_memo): echo '<p style="color:#999;">特記事項はありません。</p>'; endif;
            ?>
        </div>

        <div class="card">
            <h3 class="section-title">💬 家族の反応履歴</h3>
            <?php if (empty($family_replies)): ?>
                <p style="color: #999;">まだ反応はありません。</p>
            <?php else: ?>
                <?php foreach($family_replies as $r): ?>
                    <div class="reply-item">
                        <span style="color: #666; font-size: 11px;"><?= date('m/d H:i', strtotime($r['created_at'])) ?></span>
                        <span class="reply-stamp"><?= htmlspecialchars($r['reply_stamp'] ?? '未回答') ?></span>
                        <?php if($r['family_memo']): ?>
                            <div style="margin-top:5px; font-size:13px; color:#555;">メモ：<?= htmlspecialchars($r['family_memo']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card report-card">
            <h3 class="section-title">📝 家族用アプリへ報告</h3>
            <form method="POST">
                <?php
                $report_text = "【服薬状況報告】\n対象者：{$p['user_id']} 様\n達成率：0%\n\n＜薬剤師コメント＞\n最近、記録が滞っているようです。";
                if (strpos($p['tags'], '独居') !== false) $report_text .= "\n独居のため、ご家族からもお電話等で確認をお願いします。";
                ?>
                <textarea name="report_content"><?= htmlspecialchars($report_text) ?></textarea>
                <button type="submit" name="send_family_app" class="btn-send">📲 送信</button>
                <div style="clear: both;"></div>
            </form>
        </div>
        
        <p style="text-align:center;"><a href="index.php" style="color:#999; text-decoration:none;">← 患者一覧に戻る</a></p>
    </div>
</body>
</html>