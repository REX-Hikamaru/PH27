<?php
// ============================================
// get_product.php - 商品情報取得API
// ============================================

require_once __DIR__ . '/enhanced_config.php';

// 認証チェック
checkAuth();

// JSONレスポンス用のヘッダー設定
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('無効なリクエストメソッドです。');
    }

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('無効な商品IDです。');
    }

    // データベースから商品情報を取得
    $pdo = EnhancedDatabase::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        echo json_encode([
            'success' => true,
            'product' => $product
        ]);
    } else {
        throw new Exception('商品が見つかりません。');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>