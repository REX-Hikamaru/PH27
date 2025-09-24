<?php
// ============================================
// 7. enhanced_delete.php - 拡張削除処理
// ============================================

require_once __DIR__ . '/enhanced_config.php';
checkAuth();

if (!isset($_GET['id'])) {
    $_SESSION['message'] = '削除する商品が指定されていません。';
    header('Location: enhanced_dashboard.php');
    exit();
}

$productId = (int)$_GET['id'];

try {
    if (EnhancedDatabase::deleteProduct($productId)) {
        $_SESSION['message'] = '商品を削除しました。';
    } else {
        $_SESSION['message'] = '商品の削除に失敗しました。';
    }
} catch (Exception $e) {
    $_SESSION['message'] = 'エラー: ' . $e->getMessage();
}

header('Location: enhanced_dashboard.php');
exit();

?>