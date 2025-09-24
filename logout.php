<?php
require_once __DIR__ . '/enhanced_config.php';

// ログアウトアクションを記録
$userId = $_SESSION['user_account'] ?? $_SESSION['username'] ?? 'unknown';
logAuthAction($userId, 'logout', true);

// セッションを破棄
session_unset();
session_destroy();

// 新しいセッションを開始してメッセージを設定
session_start();
$_SESSION['success'] = 'ログアウトしました。';

// ログインページにリダイレクト
header('Location: login.php');
exit();
?>