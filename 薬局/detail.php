<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// ========== データベース接続設定 ==========
$host = 'localhost';
$db_name = 'medicare_db'; 
$user = 'root'; 
$password = ''; 

try {
    // ★文字化け対策のため charset=utf8mb4 に変更
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
// 2. 家族用アプリへの送信処理（二重送信防止リダイレクト版）
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_family_app'])) {
    $stmt_send = $pdo->prepare("INSERT INTO family_messages (user_id, sender_name, message) VALUES (?, 'メディケア薬局 薬剤師', ?)");
    $stmt_send->execute([$patient_id, $_POST['report_content']]);
    
    // 送信完了メッセージをセッションに保存
    $_SESSION['success_msg'] = "✅ 家族用アプリへメッセージを送信しました！";
    
    // 送信後に自分自身へリダイレクト（POSTデータをクリア）
    header("Location: detail.php?id=" . urlencode($patient_id));
    exit;
}

// セッションから成功メッセージを取得して消去
$success_msg = '';
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// ----------------------------------------------------
// 3. 患者マスターデータ
// ----------------------------------------------------
$patient_list = [
    '山田きよえ'   => ['age' => 78, 'history' => '高血圧、糖尿病', 'allergy' => 'ペニシリン系', 'tel' => '03-3261-8841', 'address' => '東京都千代田区麹町1-1'],
    '高橋誠一郎'   => ['age' => 83, 'history' => '慢性心不全、痛風', 'allergy' => 'なし', 'tel' => '090-1145-2236', 'address' => '東京都千代田区一番町5-2'],
    '田中まさる'   => ['age' => 81, 'history' => '慢性腎臓病、骨粗鬆症', 'allergy' => 'なし', 'tel' => '03-5211-9905', 'address' => '東京都千代田区九段南2-4'],
    '鈴木いちろう' => ['age' => 76, 'history' => '脂質異常症、MCI', 'allergy' => 'なし', 'tel' => '090-2288-4411', 'address' => '東京都千代田区富士見1-3'],
    '佐藤はな'     => ['age' => 85, 'history' => '変形性膝関節症', 'allergy' => 'なし', 'tel' => '03-3230-7762', 'address' => '東京都千代田区五番町2-1'],
    '川口さなえ'   => ['age' => 79, 'history' => '高血圧、不眠症', 'allergy' => 'なし', 'tel' => '080-3399-5522', 'address' => '東京都千代田区三番町6-1']
];

if (array_key_exists($patient_id, $patient_list)) {
    $p = $patient_list[$patient_id];
} else {
    $p = ['age' => 82, 'history' => '慢性疾患', 'allergy' => 'なし', 'tel' => '090-9999-8888', 'address' => '東京都内'];
}

// データベースから「タグ」を取得
$stmt_db = $pdo->prepare("SELECT tags, daily_target FROM patients WHERE user_id = ?");
$stmt_db->execute([$patient_id]);
$db_data = $stmt_db->fetch(PDO::FETCH_ASSOC);

$tags = explode(',', $db_data['tags'] ?? '独居,足腰が不自由');
$daily_target = $db_data['daily_target'] ?? 3;

// ----------------------------------------------------
// 4. 家族からの返信（スタンプ）を取得
// ----------------------------------------------------
$stmt_replies = $pdo->prepare("SELECT message, reply_stamp, created_at FROM family_messages WHERE user_id = ? AND reply_stamp IS NOT NULL ORDER BY created_at DESC LIMIT 3");
$stmt_replies->execute([$patient_id]);
$family_replies = $stmt_replies->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>患者詳細 | <?= htmlspecialchars($patient_id) ?></title>
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f8f9fa; margin: 0; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #0078d7; color: white; padding: 25px; box-sizing: border-box; flex-shrink: 0; }
        .sidebar-section { margin-bottom: 30px; }
        .sidebar-section h3 { font-size: 15px; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 5px; }
        .tag-badge { background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px; font-size: 11px; margin-right: 4px; display: inline-block; margin-bottom: 4px; }
        .sidebar-info { font-size: 13px; line-height: 1.6; }

        .main-content { flex: 1; padding: 30px 40px; box-sizing: border-box; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e1e4e8; }
        .patient-name { font-size: 26px; font-weight: bold; margin: 0; }
        .patient-meta { color: #444; font-size: 14px; margin-top: 10px; line-height: 1.6; }
        .allergy-box { background: #fff5f5; color: #e53e3e; padding: 10px 15px; border-radius: 6px; border: 1px solid #feb2b2; font-weight: bold; margin-top: 15px; font-size: 14px; }
        .section-title { font-size: 17px; color: #0078d7; margin-bottom: 15px; border-left: 4px solid #0078d7; padding-left: 10px; }
        
        .report-card { border: 2px dashed #0078d7; background: #f0f7ff; }
        textarea { width: 100%; height: 160px; border: 1px solid #ddd; border-radius: 6px; padding: 12px; font-size: 14px; line-height: 1.6; margin-top: 10px; box-sizing: border-box; }
        .btn-send { background: #0078d7; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; cursor: pointer; float: right; margin-top: 10px; }
        
        .reply-item { background: #fff; border: 1px solid #eee; padding: 12px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; }
        .reply-stamp { display: inline-block; background: #fff3cd; color: #856404; padding: 2px 10px; border-radius: 10px; font-weight: bold; font-size: 12px; margin-left: 10px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-section">
            <h3>属性タグ</h3>
            <div class="tag-container">
                <?php foreach($tags as $t): ?>
                    <span class="tag-badge"><?= htmlspecialchars(trim($t)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sidebar-section">
            <h3>受診履歴</h3>
            <div class="sidebar-info">最終来局日: 2025/11/15</div>
        </div>
        <div class="sidebar-section">
            <h3>病歴</h3>
            <div class="sidebar-info"><?= htmlspecialchars($p['history']) ?></div>
        </div>
    </div>

    <div class="main-content">
        <?php if($success_msg): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px;"><?= $success_msg ?></div>
        <?php endif; ?>

        <div class="card">
            <h1 class="patient-name"><?= htmlspecialchars($patient_id) ?> (<?= $p['age'] ?> 歳)</h1>
            <div class="patient-meta">
                <strong>現住所:</strong> <?= htmlspecialchars($p['address']) ?><br>
                <strong>連絡先:</strong> <?= htmlspecialchars($p['tel']) ?>
            </div>
            
            <?php if($p['allergy'] !== 'なし'): ?>
                <div class="allergy-box">🚨 注意: <?= htmlspecialchars($p['allergy']) ?> のアレルギーがあります。</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="section-title">💬 家族からのフィードバック</h3>
            <?php if (empty($family_replies)): ?>
                <p style="color: #999; font-size: 14px;">まだ家族からの反応はありません。</p>
            <?php else: ?>
                <?php foreach($family_replies as $r): ?>
                    <div class="reply-item">
                        <span style="color: #666; font-size: 12px;"><?= date('m/d H:i', strtotime($r['created_at'])) ?> の報告に対して:</span>
                        <div style="margin-top: 5px;">
                            <strong>反応:</strong> <span class="reply-stamp"><?= htmlspecialchars($r['reply_stamp']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="section-title">💊 処方内容</h3>
            <ul style="line-height: 1.8; font-size: 15px;">
                <li>アムロジピン (降圧剤)</li>
                <li>メトホルミン (血糖降下剤)</li>
                <li>アスピリン (血栓予防)</li>
            </ul>
        </div>

        <div class="card report-card">
            <h3 class="section-title">📝 家族用アプリへ報告</h3>
            <form method="POST">
                <?php
                $report_text = "【服薬状況報告】\n対象者：{$patient_id} 様\n達成率：0%\n\n＜薬剤師コメント＞\n最近、記録が滞っているようです。";
                if (in_array('独居', $tags)) $report_text .= "\n独居のため、ご家族からもお電話等で確認をお願いします。";
                ?>
                <textarea name="report_content"><?= htmlspecialchars($report_text) ?></textarea>
                <button type="submit" name="send_family_app" class="btn-send">📲 家族用アプリへ送信</button>
                <div style="clear: both;"></div>
            </form>
        </div>
        
        <p style="text-align:center;"><a href="index.php" style="color:#999; text-decoration:none;">← 患者一覧に戻る</a></p>
    </div>

</body>
</html>