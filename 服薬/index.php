<?php
// ★【修正】タイムゾーンを日本時間(JST)に設定
date_default_timezone_set('Asia/Tokyo');

// PHPセッションを開始
session_start();

// 1. ログインチェックを省略し、初期設定を強制的に行う
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in'] = true;
    // 内部処理に必要なデータは維持
    $_SESSION['daily_dose_target'] = 3; 
    $_SESSION['streak_goal_days'] = 7;
    $_SESSION['streak_days'] = 0;
    $_SESSION['recorded_slots_today'] = [];
    $_SESSION['last_record_time'] = 0;
    $_SESSION['last_completion_date'] = date('Y-m-d', strtotime('-10 days')); 
}
$_SESSION['user_id'] = '山田きよえ';

$current_user_id = htmlspecialchars($_SESSION['user_id']);
$daily_dose_target = $_SESSION['daily_dose_target'];

// 2. 服薬区分の判定ロジック
$all_slots = [];
if ($daily_dose_target >= 1) $all_slots[] = '朝';
if ($daily_dose_target >= 2) $all_slots[] = '昼';
if ($daily_dose_target >= 3) $all_slots[] = '夜';

$slots_recorded = $_SESSION['recorded_slots_today'];
$is_goal_achieved = (count($slots_recorded) >= $daily_dose_target);
$remaining_slots = array_diff($all_slots, $slots_recorded); 

// メッセージ処理
$message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$is_error = strpos($message, '失敗') !== false || strpos($message, '過ぎ') !== false || strpos($message, '既に') !== false;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>メディケア・リワード</title>
    <style>
        /* CSSは高齢者向けの視認性と操作性を最優先 */
        body { font-family: "Segoe UI", "Hiragino Sans", sans-serif; background: #f9fafb; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); text-align: center; width: 400px; }
        
        h1 { font-size: 28px; color: #333; margin-bottom: 10px; }
        .user-info { font-size: 20px; color: #0078d7; font-weight: 700; margin-bottom: 25px; }
        
        /* 服薬区分選択（未記録分のみ） */
        label { display: block; margin-bottom: 10px; color: #555; font-weight: 600; font-size: 20px; }
        select { padding: 12px 18px; border-radius: 10px; border: 2px solid #ccc; font-size: 22px; width: 80%; margin-bottom: 30px;}
        
        /* 4. カメラ起動ボタン（<label>と<input>の組み合わせ） */
        .camera-label {
            /* ラベルをボタンとして装飾 */
            display: inline-block;
            background: #e6a500; 
            color: white; 
            cursor: pointer; 
            transition: 0.3s; 
            width: 95%; 
            padding: 30px 20px; 
            font-size: 30px; 
            border-radius: 15px;
            font-weight: 800;
        }
        .camera-label:hover { background: #cc9400; transform: scale(1.02); }
        
        /* 隠しインプットフィールド */
        #camera-input {
            display: none; /* 画面に表示しない */
        }

        /* 記録完了時 */
        .goal-achieved { background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; font-size: 20px; font-weight: 700; margin-top: 30px; }
        .disabled-button { background: #ccc !important; cursor: not-allowed !important; color: #666 !important; font-size: 24px; padding: 25px 20px;}
        
        /* メッセージ表示 */
        .msg-box { padding: 15px; border-radius: 8px; font-size: 16px; font-weight: 600; margin-top: 20px; }
        .msg-success { background: #e6f4ea; color: #006644; }
        .msg-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<div class="card">
    <h1>メディケア・リワード</h1>

    <div class="user-info"><?= $current_user_id ?>さんの記録</div>
    
    <?php if ($message): ?>
        <div class="msg-box <?= $is_error ? 'msg-error' : 'msg-success' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <?php if ($is_goal_achieved): ?>

        <p class="goal-achieved">✅ 本日の服薬記録はすべて完了しました！</p>
        <button type="button" disabled class="disabled-button">本日の記録は完了</button>

    <?php else: ?>

      <form id="recordForm" action="record_process.php" method="post" enctype="multipart/form-data">
        
        <label for="time_slot_select">次に記録する区分：</label>
        
        <select name="time" id="time_slot_select" required>
          <?php foreach ($remaining_slots as $slot): ?>
            <option value="<?= htmlspecialchars($slot) ?>"><?= htmlspecialchars($slot) ?></option>
          <?php endforeach; ?>
        </select>

        <input 
            type="file" 
            accept="image/*" 
            capture="camera" 
            name="med_photo" 
            id="camera-input" 
            onchange="document.getElementById('recordForm').submit()"
        >
        
        <label for="camera-input" class="camera-label">📸 写真を撮る</label>

        </form>

    <?php endif; ?>
    
</div>
</body>
<div class="card"> /*本番では消す*/
    <h1>メディケア・リワード</h1>

    <?php if ($is_goal_achieved): ?>

        <p class="goal-achieved">✅ 本日の服薬記録はすべて完了しました！</p>
        <button type="button" disabled class="disabled-button">本日の記録は完了</button>

    <?php else: ?>

      <form id="recordForm" action="record_process.php" method="post" enctype="multipart/form-data">
        
        <label for="time_slot_select">次に記録する区分：</label>
        
        <select name="time" id="time_slot_select" required>
          <?php foreach ($remaining_slots as $slot): ?>
            <option value="<?= htmlspecialchars($slot) ?>"><?= htmlspecialchars($slot) ?></option>
          <?php endforeach; ?>
        </select>

        <input type="file" accept="image/*" capture="camera" name="med_photo" id="camera-input" onchange="document.getElementById('recordForm').submit()">
        <label for="camera-input" class="camera-label">📸 写真を撮る</label>

      </form>

    <?php endif; ?>

    <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
        <p style="font-size: 14px; color: #888;">【開発・テスト用】</p>
        <a href="reset_day.php" style="color: #d9534f; text-decoration: none; font-weight: 600; display: inline-block; padding: 10px 15px; border: 1px solid #d9534f; border-radius: 5px;">
            本日分の記録をリセット
        </a>
    </div>
    
</div>
</html>