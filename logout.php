<?php
session_start();

// セッション情報をすべて消去する
$_SESSION = array();

// クッキーも削除
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// セッションを破棄
session_destroy();

// ログイン画面へ戻す
header('Location: login.php');
exit;