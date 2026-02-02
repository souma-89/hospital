<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

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

$patient_id = isset($_GET['id']) ? urldecode($_GET['id']) : '山田きよえ';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    $stmt_update = $pdo->prepare("UPDATE patients SET dob = ?, age = ?, tel = ?, tags = ?, history = ?, staff_memo = ? WHERE user_id = ?");
    $stmt_update->execute([
        $_POST['dob'], $_POST['age'], $_POST['tel'], $_POST['tags'], $_POST['history'], $_POST['staff_memo'], $patient_id
    ]);
    $_SESSION['success_msg'] = "✅ 患者情報と引き継ぎメモを更新しました！";
    header("Location: detail.php?id=" . urlencode($patient_id));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_family_app'])) {
    $stmt_send = $pdo->prepare("INSERT INTO family_messages (user_id, sender_name, message) VALUES (?, '中村病院 薬剤部', ?)");
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

$stmt_db = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt_db->execute([$patient_id]);
$p = $stmt_db->fetch(PDO::FETCH_ASSOC);

if (!$p) { die("患者データが見つかりません。"); }

$tags = explode(',', $p['tags'] ?? '');
$edit_mode = isset($_GET['edit']); 

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
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f8f9fa; margin: 0; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: #0078d7; color: white; padding: 25px; box-sizing: border-box; flex-shrink: 0; }
        .sidebar-section { margin-bottom: 25px; }
        .sidebar-section h3 { font-size: 15px; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 5px; }
        .tag-badge { background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px; font-size: 11px; margin-right: 4px; display: inline-block; margin-bottom: 4px; }
        .sidebar-info { font-size: 13px; line-height: 1.6; }
        .staff-memo-box { background: rgba(0, 0, 0, 0.2); padding: 12px; border-radius: 8px; border-left: 4px solid #ffcc00; }
        .staff-memo-title { color: #ffcc00; font-weight: bold; font-size: 13px; margin-bottom: 8px; display: block; }
        .main-content { flex: 1; padding: 30px 40px; box-sizing: border-box; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e1e4e8; position: relative; }
        .edit-btn { position: absolute; top: 20px; right: 20px; text-decoration: none; font-size: 12px; background: #eee; color: #333; padding: 5px 10px; border-radius: 4px; }
        .edit-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 10px; font-size: 14px; font-family: inherit; }
        .save-btn { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .patient-name { font-size: 26px; font-weight: bold; margin: 0; }
        .patient-birth { font-size: 18px; color: #666; font-weight: normal; margin-left: 10px; }
        .patient-meta { color: #444; font-size: 14px; margin-top: 10px; line-height: 1.6; }
        .section-title { font-size: 17px; color: #0078d7; margin-bottom: 15px; border-left: 4px solid #0078d7; padding-left: 10px; }
        .alert-card { border: 2px solid #ffcc00; background: #fffdf0; }
        .memo-text { font-size: 16px; color: #d44917; font-weight: bold; background: #fff; padding: 10px; border-radius: 5px; border: 1px solid #ffeeba; margin-top: 5px; }
        .report-card { border: 2px dashed #0078d7; background: #f0f7ff; }
        textarea { width: 100%; height: 100px; border: 1px solid #ddd; border-radius: 6px; padding: 12px; font-size: 14px; margin-top: 10px; box-sizing: border-box; }
        .btn-send { background: #0078d7; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; cursor: pointer; float: right; margin-top: 10px; }
    </style>
</head>
<body>

<nav style="background: white; padding: 15px 0; display: flex; justify-content: center; align-items: center; border-bottom: 3px solid #0078d7;">
    <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 20px;">
        <img src="logo.png" alt="Logo" style="height: 65px; width: auto;">
        <div style="display: flex; flex-direction: column;">
            <div style="font-size: 28px; color: #0078d7; font-weight: bold; line-height: 1.1; letter-spacing: 2px;">中村病院</div>
            <div style="font-size: 14px; color: #666; font-weight: bold; letter-spacing: 1px;">NAKAMURA MEDICAL CENTER</div>
        </div>
    </a>
</nav>

<div class="wrapper">
    <div class="sidebar">
        <form action="" method="POST" id="mainForm" style="display:contents;">
        <div class="sidebar-section">
            <h3>属性タグ</h3>
            <div class="tag-container">
                <?php if ($edit_mode): ?>
                    <input type="text" name="tags" class="edit-input" value="<?= htmlspecialchars($p['tags']) ?>">
                <?php else: ?>
                    <?php foreach($tags as $t): if(trim($t)!=='') : ?>
                        <span class="tag-badge"><?= htmlspecialchars(trim($t)) ?></span>
                    <?php endif; endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="sidebar-section">
            <h3>病歴・処方内容</h3>
            <div class="sidebar-info">
                <?php if ($edit_mode): ?>
                    <textarea name="history" class="edit-input" style="height: 100px;"><?= htmlspecialchars($p['history']) ?></textarea>
                <?php else: ?>
                    <?= nl2br(htmlspecialchars($p['history'])) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="sidebar-section">
            <div class="staff-memo-box">
                <span class="staff-memo-title">🔑 薬剤師引き継ぎメモ</span>
                <div class="sidebar-info">
                    <?php if ($edit_mode): ?>
                        <textarea name="staff_memo" class="edit-input" style="height: 150px; background: #fff; color: #333;"><?= htmlspecialchars($p['staff_memo'] ?? '') ?></textarea>
                    <?php else: ?>
                        <?= !empty($p['staff_memo']) ? nl2br(htmlspecialchars($p['staff_memo'])) : '<span style="opacity:0.6;">(未記入)</span>' ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if($success_msg): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px;"><?= $success_msg ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if ($edit_mode): ?>
                <button type="submit" name="update_patient" form="mainForm" class="save-btn">💾 保存する</button>
                <a href="detail.php?id=<?= urlencode($patient_id) ?>" class="edit-btn">キャンセル</a>
            <?php else: ?>
                <a href="detail.php?id=<?= urlencode($patient_id) ?>&edit=1" class="edit-btn">✏️ 編集</a>
            <?php endif; ?>

            <h1 class="patient-name">
                <?= htmlspecialchars($p['user_id']) ?> 
                <?php if ($edit_mode): ?>
                    <div style="margin-top:10px; font-size: 14px;">
                        生年月日: <input type="text" name="dob" value="<?= htmlspecialchars($p['dob']) ?>" class="edit-input" style="width:150px; display:inline;">
                        年齢: <input type="number" name="age" value="<?= htmlspecialchars($p['age']) ?>" class="edit-input" style="width:80px; display:inline;">
                    </div>
                <?php else: ?>
                    <span class="patient-birth">(<?= htmlspecialchars($p['dob']) ?>生 / <?= $p['age'] ?> 歳)</span>
                <?php endif; ?>
            </h1>
            <div class="patient-meta">
                <strong>連絡先:</strong> 
                <?php if ($edit_mode): ?>
                    <input type="text" name="tel" value="<?= htmlspecialchars($p['tel']) ?>" class="edit-input" style="width:200px; display:inline;">
                <?php else: ?>
                    <?= htmlspecialchars($p['tel']) ?>
                <?php endif; ?>
            </div>
        </div>
        </form>

        <div class="card alert-card">
            <h3 class="section-title" style="color: #856404; border-left-color: #ffcc00;">⚠️ 家族からの気になる報告（最新）</h3>
            <?php 
            $has_memo = false;
            foreach($family_replies as $r): 
                if(!empty($r['family_memo'])): 
                    $has_memo = true;
            ?>
                <div class="reply-item" style="border: 1px solid #eee; padding:12px; background:#fff; margin-bottom:10px; border-radius: 8px;">
                    <div class="memo-text">「<?= htmlspecialchars($r['family_memo']) ?>」</div>
                </div>
            <?php break; endif; endforeach; 
            if(!$has_memo): echo '<p style="color:#999;">特記事項はありません。</p>'; endif;
            ?>
        </div>

        <div class="card report-card">
            <h3 class="section-title">📝 家族用アプリへ報告</h3>
            <form method="POST">
                <textarea name="report_content"><?= "【服薬状況報告】\n対象者：{$p['user_id']} 様\n" ?></textarea>
                <button type="submit" name="send_family_app" class="btn-send">📲 送信</button>
                <div style="clear: both;"></div>
            </form>
        </div>
        
        <p style="text-align:center;"><a href="index.php" style="color:#999; text-decoration:none;">← 患者一覧に戻る</a></p>
    </div>
</div>
</body>
</html>