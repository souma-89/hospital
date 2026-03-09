<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

// 1. ログインチェック
if (!isset($_SESSION['yakuzaishi_login'])) {
    header('Location: login.php');
    exit;
}

/* =====================
    DB設定
===================== */
$host = 'localhost';
$db_name = 'medicare_db'; 
$user = 'root'; 
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ⚠️ デモデータの自動削除・挿入ロジックを完全に撤廃しました。
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage()); 
}

// 2. 未読メッセージ数をカウントして患者データを取得
$stmt = $pdo->query("
    SELECT p.*, 
    (SELECT COUNT(*) FROM patient_replies r WHERE r.user_id = p.user_id AND r.is_read = 0) as unread_count
    FROM patients p
");
$patient_raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$patient_targets = [];
$patient_tags = [];
$patient_unread = [];
foreach ($patient_raw_data as $p) {
    $patient_targets[$p['user_id']] = (int)$p['daily_target'];
    $patient_tags[$p['user_id']] = $p['tags'] ?? '';
    $patient_unread[$p['user_id']] = (int)$p['unread_count'];
}

$daily_target = isset($_GET['target']) ? (int)$_GET['target'] : 3; 
if ($daily_target < 1 || $daily_target > 3) $daily_target = 3;

$days_to_check = 7; 
$start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
$end_date = date('Y-m-d 23:59:59');

$filtered_user_ids = [];
foreach ($patient_targets as $user_id => $target) {
    if ($target === $daily_target) {
        $filtered_user_ids[] = $user_id;
    }
}

$priority_list = [];
if (!empty($filtered_user_ids)) {
    $user_id_list = "'" . implode("','", $filtered_user_ids) . "'";
    $stmt = $pdo->prepare("SELECT user_id, time_slot, record_timestamp FROM medication_records WHERE user_id IN ({$user_id_list}) AND record_timestamp BETWEEN :start_date AND :end_date ORDER BY record_timestamp DESC");
    $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $record_map = [];
    $latest_record_map = [];
    foreach ($records as $record) {
        $user = $record['user_id'];
        $date = date('Y-m-d', strtotime($record['record_timestamp']));
        $record_map[$user][$date][] = $record['time_slot'];
        if (!isset($latest_record_map[$user]) || $record['record_timestamp'] > $latest_record_map[$user]) {
            $latest_record_map[$user] = $record['record_timestamp'];
        }
    }

    function generate_status_report($data, $tags) {
        $is_single = strpos($tags, '独居') !== false;
        $is_dementia = strpos($tags, '認知症') !== false;
        if ($data['today_count'] === 0) {
            $msg = "本日の記録なし。至急状況の確認が必要。";
            if ($is_single) $msg .= "（独居のため安否確認を推奨）";
            return $msg;
        }
        if ($is_dementia && $data['missed_today']) {
            return "飲み忘れが発生中。認知機能の影響が疑われるため家族連携を検討。";
        }
        if ($data['total_missed'] >= 3) {
            return "記録の欠落が目立つ状態。次回来局時に一包化の意向を確認。";
        }
        if ($data['consecutive_miss'] >= 2) {
            return "連続未達あり。生活リズムの変化がないか聞き取りが必要。";
        }
        return "良好。通常通りのモニタリングを継続。";
    }

    foreach ($filtered_user_ids as $user) {
        $target = $patient_targets[$user];
        $total_missed = 0;
        $consecutive_miss = 0;
        $today_date = date('Y-m-d');
        for ($i = 0; $i < $days_to_check; $i++) {
            $date_check = date('Y-m-d', strtotime("-$i day"));
            $recorded_count = isset($record_map[$user][$date_check]) ? count(array_unique($record_map[$user][$date_check])) : 0;
            if ($recorded_count < $target) {
                $total_missed += ($target - $recorded_count);
                $consecutive_miss++;
            } else { if ($i > 0) break; }
        }
        $today_count = isset($record_map[$user][$today_date]) ? count(array_unique($record_map[$user][$today_date])) : 0;
        $temp_data = ['total_missed' => $total_missed, 'today_count' => $today_count, 'consecutive_miss' => $consecutive_miss, 'missed_today' => ($today_count < $target)];
        
        $priority_list[] = [
            'user_name' => $user, 
            'daily_target' => $target,
            'total_missed' => $total_missed, 
            'missed_today' => ($today_count < $target),
            'today_count' => $today_count,
            'consecutive_miss' => $consecutive_miss, 
            'last_record' => $latest_record_map[$user] ?? null,
            'tags' => $patient_tags[$user],
            'unread_count' => $patient_unread[$user],
            'status_report' => generate_status_report($temp_data, $patient_tags[$user])
        ];
    }

    usort($priority_list, function($a, $b) {
        if ($a['missed_today'] != $b['missed_today']) return $b['missed_today'] <=> $a['missed_today']; 
        return $b['total_missed'] <=> $a['total_missed']; 
    });
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>【薬局】介入優先リスト | 中村病院</title>
    <style>
        body { font-family: "Segoe UI", "Hiragino Sans", sans-serif; background: #eef2f5; color: #333; margin: 0; padding: 0 20px 20px 20px; }
        .container { max-width: 1300px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); } 
        h1 { color: #0078d7; border-bottom: 3px solid #0078d7; padding-bottom: 10px; margin-top: 0; }
        .priority-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .priority-table th, .priority-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .priority-table th { background: #f0f8ff; color: #0078d7; font-size: 14px; }
        .priority-high { background-color: #fce4e4; border-left: 5px solid #d32f2f; }
        .priority-mid { background-color: #fff9c4; border-left: 5px solid #fbc02d; }
        .priority-low { background-color: #e8f5e9; border-left: 5px solid #388e3c; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; }
        .badge-missed { background: #d32f2f; color: white; }
        .badge-ok { background: #388e3c; color: white; }
        .tag-badge { background: #e0e0e0; color: #555; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-right: 4px; display: inline-block; }
        .patient-link { color: #0078d7; text-decoration: none; font-weight: 600; font-size: 1.1em; }
        .report-text { font-size: 0.9em; line-height: 1.4; color: #444; }
        .logout-btn { background: #f44336; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .unread-badge { background: #ff4d4f; color: white; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 10px; margin-left: 8px; vertical-align: middle; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.05); } 100% { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body>

<nav style="background: white; padding: 25px 0; border-bottom: 4px solid #0078d7; margin-bottom: 25px;">
    <div style="max-width: 1300px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 30px;">
        <div style="display: flex; align-items: center; gap: 30px;">
            <div style="display: flex; flex-direction: column; justify-content: center;">
                <div style="font-size: 48px; color: #0078d7; font-weight: bold; line-height: 1.1; letter-spacing: 3px;">中村病院</div>
                <div style="font-size: 18px; color: #666; font-weight: bold; letter-spacing: 1.5px; margin-top: 2px;">NAKAMURA MEDICAL CENTER</div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">ログアウト</a>
    </div>
</nav>

<div class="container">
    <h1>🏥 薬局管理画面 - 介入優先リスト</h1>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <form method="GET" action="index.php">
            <label for="target_select" style="font-weight: 600;">目標回数で絞り込み:</label>
            <select name="target" id="target_select" onchange="this.form.submit()" style="padding: 5px;">
                <option value="3" <?= $daily_target === 3 ? 'selected' : '' ?>>1日 3回</option>
                <option value="2" <?= $daily_target === 2 ? 'selected' : '' ?>>1日 2回</option>
                <option value="1" <?= $daily_target === 1 ? 'selected' : '' ?>>1日 1回</option>
            </select>
        </form>
        <a href="register_patient.php" style="background: #0078d7; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600;">+ 新規患者を登録</a>
    </div>
    
    <table class="priority-table">
        <thead>
            <tr>
                <th>優先度</th>
                <th>患者名 (属性タグ)</th>
                <th>今日の状況</th>
                <th>総未達(7日)</th>
                <th>連続欠損</th>
                <th>最終記録</th>
                <th style="width: 350px;">現状</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($priority_list as $data): ?> 
                <?php
                    $row_class = $data['today_count'] === 0 ? 'priority-high' : ($data['missed_today'] ? 'priority-mid' : 'priority-low');
                    $tags_array = $data['tags'] ? explode(',', $data['tags']) : [];
                ?>
                <tr class="<?= $row_class ?>">
                    <td style="font-weight: bold; text-align: center;"><?= $row_class === 'priority-high' ? '高' : ($row_class === 'priority-mid' ? '中' : '低') ?></td>
                    <td>
                        <a href="detail.php?id=<?= urlencode($data['user_name']) ?>" class="patient-link"><?= htmlspecialchars($data['user_name']) ?></a>
                        <?php if ($data['unread_count'] > 0): ?>
                            <span class="unread-badge">新着 (<?= $data['unread_count'] ?>)</span>
                        <?php endif; ?>
                        <br>
                        <?php foreach($tags_array as $t): ?>
                            <span class="tag-badge"><?= htmlspecialchars(trim($t)) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <span class="status-badge <?= $data['missed_today'] ? 'badge-missed' : 'badge-ok' ?>">
                            <?= $data['today_count'] ?>/<?= $data['daily_target'] ?>
                        </span>
                    </td>
                    <td><?= $data['total_missed'] ?>回</td>
                    <td><?= $data['consecutive_miss'] ?>日</td>
                    <td style="font-size: 0.85em;"><?= $data['last_record'] ? date('m/d H:i', strtotime($data['last_record'])) : 'なし' ?></td>
                    <td class="report-text"><?= htmlspecialchars($data['status_report']) ?></td> 
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>