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

$patient_id = isset($_GET['id']) ? urldecode($_GET['id']) : '田中まさる';

// --- データ更新処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    $stmt_update = $pdo->prepare("UPDATE patients SET dob = ?, age = ?, tel = ?, tags = ?, history = ?, staff_memo = ? WHERE user_id = ?");
    $stmt_update->execute([
        $_POST['dob'], $_POST['age'], $_POST['tel'], $_POST['tags'], $_POST['history'], $_POST['staff_memo'], $patient_id
    ]);
    $_SESSION['success_msg'] = "✅ 患者情報を更新しました！";
    header("Location: detail.php?id=" . urlencode($patient_id));
    exit;
}

// --- 家族へのメッセージ送信処理 ---
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

// --- 患者基本情報の取得 ---
$stmt_db = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt_db->execute([$patient_id]);
$p = $stmt_db->fetch(PDO::FETCH_ASSOC);

if (!$p) { die("患者データが見つかりません。"); }

// --- 直近7日間の服薬記録を取得（photo_pathも取得するように修正） ---
$stmt_records = $pdo->prepare("SELECT time_slot, record_timestamp, photo_path FROM medication_records WHERE user_id = ? AND record_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY record_timestamp DESC");
$stmt_records->execute([$patient_id]);
$med_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

// カレンダー表示用に整理
$formatted_records = [];
foreach ($med_records as $row) {
    $date = date('m/d', strtotime($row['record_timestamp']));
    // スロット名と写真パスをセットで保存
    $formatted_records[$date][] = [
        'slot' => $row['time_slot'],
        'photo' => $row['photo_path'],
        'time' => date('H:i', strtotime($row['record_timestamp']))
    ];
}

$tags = explode(',', $p['tags'] ?? '');
$edit_mode = isset($_GET['edit']); 

// 家族からの返信取得
$stmt_replies = $pdo->prepare("SELECT family_memo, created_at FROM family_messages WHERE user_id = ? AND family_memo IS NOT NULL ORDER BY created_at DESC LIMIT 3");
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
        .sidebar-section h3 { font-size: 14px; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 5px; opacity: 0.9; }
        .tag-badge { background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px; font-size: 11px; margin-right: 4px; display: inline-block; margin-bottom: 4px; }
        .sidebar-info { font-size: 13px; line-height: 1.6; }
        .staff-memo-box { background: rgba(0, 0, 0, 0.2); padding: 12px; border-radius: 8px; border-left: 4px solid #ffcc00; }
        .staff-memo-title { color: #ffcc00; font-weight: bold; font-size: 13px; margin-bottom: 8px; display: block; }
        
        .main-content { flex: 1; padding: 30px 40px; box-sizing: border-box; }
        .card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e1e4e8; position: relative; }
        .patient-name { font-size: 26px; font-weight: bold; margin: 0; }
        .section-title { font-size: 17px; color: #0078d7; margin-bottom: 15px; border-left: 4px solid #0078d7; padding-left: 10px; font-weight: bold; }
        
        /* 服薬記録用スタイル */
        .record-table { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
        .record-table th, .record-table td { border: 1px solid #eee; padding: 12px; text-align: center; }
        .record-table th { background: #f9f9f9; font-size: 12px; color: #666; }
        .slot-pill { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; background: #e8f5e9; color: #2e7d32; font-weight: bold; margin-right: 5px; }
        
        /* 証拠写真プレビュー */
        .evidence-img { width: 60px; height: 45px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; cursor: pointer; transition: 0.2s; }
        .evidence-img:hover { opacity: 0.8; transform: scale(1.1); }

        .edit-btn { position: absolute; top: 20px; right: 20px; text-decoration: none; font-size: 12px; background: #eee; color: #333; padding: 5px 12px; border-radius: 4px; }
        .save-btn { background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        textarea { width: 100%; height: 80px; border: 1px solid #ddd; border-radius: 6px; padding: 10px; box-sizing: border-box; }

        /* 簡易モーダル（拡大表示用） */
        #imgModal { display: none; position: fixed; z-index: 1000; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); align-items: center; justify-content: center; }
        #imgModal img { max-width: 90%; max-height: 90%; border: 5px solid white; border-radius: 8px; }
    </style>
</head>
<body>

<div id="imgModal" onclick="this.style.display='none'">
    <img id="modalImg" src="">
</div>

<nav style="background: white; padding: 25px 0; display: flex; justify-content: center; align-items: center; border-bottom: 4px solid #0078d7;">
    <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 30px;">
        <img src="logo.png" alt="Logo" style="height: 100px; width: auto;">
        <div style="display: flex; flex-direction: column; justify-content: center;">
            <div style="font-size: 48px; color: #0078d7; font-weight: bold; line-height: 1.1; letter-spacing: 3px;">中村病院</div>
            <div style="font-size: 18px; color: #666; font-weight: bold; letter-spacing: 1.5px; margin-top: 2px;">NAKAMURA MEDICAL CENTER</div>
        </div>
    </a>
</nav>

<div class="wrapper">
    <div class="sidebar">
        <form action="" method="POST" id="mainForm" style="display:contents;">
        <div class="sidebar-section">
            <h3>属性タグ</h3>
            <?php if ($edit_mode): ?>
                <input type="text" name="tags" value="<?= htmlspecialchars($p['tags']) ?>" style="width:100%;">
            <?php else: ?>
                <?php foreach($tags as $t): if(trim($t)!=='') : ?>
                    <span class="tag-badge"><?= htmlspecialchars(trim($t)) ?></span>
                <?php endif; endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="sidebar-section">
            <h3>病歴・処方内容</h3>
            <div class="sidebar-info">
                <?php if ($edit_mode): ?>
                    <textarea name="history"><?= htmlspecialchars($p['history']) ?></textarea>
                <?php else: ?>
                    <?= nl2br(htmlspecialchars($p['history'] ?? 'なし')) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="sidebar-section">
            <div class="staff-memo-box">
                <span class="staff-memo-title">🔑 薬剤師引き継ぎメモ</span>
                <div class="sidebar-info">
                    <?php if ($edit_mode): ?>
                        <textarea name="staff_memo" style="height:120px; background:#fff;"><?= htmlspecialchars($p['staff_memo'] ?? '') ?></textarea>
                    <?php else: ?>
                        <?= !empty($p['staff_memo']) ? nl2br(htmlspecialchars($p['staff_memo'])) : '記入なし' ?>
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
                <button type="submit" name="update_patient" form="mainForm" class="save-btn">💾 保存</button>
                <a href="detail.php?id=<?= urlencode($patient_id) ?>" class="edit-btn">キャンセル</a>
            <?php else: ?>
                <a href="detail.php?id=<?= urlencode($patient_id) ?>&edit=1" class="edit-btn">✏️ 編集</a>
            <?php endif; ?>

            <h1 class="patient-name">
                <?= htmlspecialchars($p['user_id']) ?>
                <span style="font-size:18px; color:#666; font-weight:normal;">
                    (<?= htmlspecialchars($p['dob']) ?>生 / <?= $p['age'] ?> 歳)
                </span>
            </h1>
            <p style="margin: 10px 0 0; color:#444;"><strong>連絡先:</strong> <?= htmlspecialchars($p['tel']) ?></p>
        </div>

        <div class="card">
            <h3 class="section-title">💊 直近7日間の服薬記録（証拠写真）</h3>
            <table class="record-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">日付</th>
                        <th>区分 / 時間</th>
                        <th>服薬時の証拠写真</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    for ($i = 0; $i < 7; $i++): 
                        $d = date('m/d', strtotime("-$i days"));
                    ?>
                    <tr>
                        <td style="font-weight:bold; background:#fcfcfc;"><?= $d ?></td>
                        <td style="text-align:left;">
                            <?php if (isset($formatted_records[$d])): ?>
                                <?php foreach($formatted_records[$d] as $rec): ?>
                                    <div style="margin-bottom: 5px;">
                                        <span class="slot-pill"><?= htmlspecialchars($rec['slot']) ?></span>
                                        <span style="font-size: 12px; color: #666;"><?= $rec['time'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color:#ccc; font-size:11px;">記録なし</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($formatted_records[$d])): ?>
                                <?php foreach($formatted_records[$d] as $rec): ?>
                                    <?php if (!empty($rec['photo'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($rec['photo']) ?>" 
                                             class="evidence-img" 
                                             onclick="showImage(this.src)" 
                                             title="<?= htmlspecialchars($rec['slot']) ?>の記録写真">
                                    <?php else: ?>
                                        <span style="font-size: 10px; color: #999;">(写真なし)</span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="border-color:#ffcc00; background:#fffdf0;">
            <h3 class="section-title" style="color:#856404; border-left-color:#ffcc00;">⚠️ 家族からの報告（最新）</h3>
            <?php if (empty($family_replies)): ?>
                <p style="color:#999; font-size:14px;">特記事項はありません。</p>
            <?php else: foreach($family_replies as $r): ?>
                <div style="background:#fff; border:1px solid #ffeeba; padding:10px; border-radius:6px; margin-bottom:8px; color:#d44917; font-weight:bold;">
                    「<?= htmlspecialchars($r['family_memo']) ?>」
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card" style="border:2px dashed #0078d7; background:#f0f7ff;">
            <h3 class="section-title">📝 家族用アプリへ報告</h3>
            <form method="POST">
                <textarea name="report_content"><?= "【服薬状況報告】\n対象者：{$p['user_id']} 様\n本日の様子はいかがでしょうか？" ?></textarea>
                <button type="submit" name="send_family_app" style="background:#0078d7; color:#fff; border:none; padding:10px 20px; border-radius:5px; margin-top:10px; cursor:pointer; font-weight:bold; float:right;">📲 送信</button>
                <div style="clear:both;"></div>
            </form>
        </div>
        
        <p style="text-align:center;"><a href="index.php" style="color:#999; text-decoration:none;">← 患者一覧に戻る</a></p>
    </div>
</div>
</form>

<script>
    // 画像をモーダルで拡大表示する関数
    function showImage(src) {
        const modal = document.getElementById('imgModal');
        const modalImg = document.getElementById('modalImg');
        modal.style.display = "flex";
        modalImg.src = src;
    }
</script>

</body>
</html>