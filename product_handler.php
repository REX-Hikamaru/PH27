<?php
// ============================================
// product_handler.php - 商品管理API
// ============================================

require_once __DIR__ . '/enhanced_config.php';

// 認証チェック
checkAuth();

// JSONレスポンス用のヘッダー設定
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('無効なリクエストメソッドです。');
    }

    // CSRF トークン検証
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        throw new Exception('不正なリクエストです。');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = intval($_POST['price'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            // バリデーション
            if (empty($name)) {
                throw new Exception('商品名を入力してください。');
            }
            if (empty($category)) {
                throw new Exception('カテゴリを選択してください。');
            }
            if ($price <= 0) {
                throw new Exception('正しい価格を入力してください。');
            }
            if ($stock < 0) {
                throw new Exception('在庫数は0以上で入力してください。');
            }

            // データベースに追加
            $pdo = EnhancedDatabase::getConnection();
            $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock, stock_quantity, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $result = $stmt->execute([$name, $category, $price, $stock, $stock, $description, $_SESSION['user_db_id'] ?? null]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => '商品を追加しました。']);
            } else {
                throw new Exception('商品の追加に失敗しました。');
            }
            break;

        case 'edit':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = intval($_POST['price'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            // バリデーション
            if ($id <= 0) {
                throw new Exception('無効な商品IDです。');
            }
            if (empty($name)) {
                throw new Exception('商品名を入力してください。');
            }
            if (empty($category)) {
                throw new Exception('カテゴリを選択してください。');
            }
            if ($price <= 0) {
                throw new Exception('正しい価格を入力してください。');
            }
            if ($stock < 0) {
                throw new Exception('在庫数は0以上で入力してください。');
            }

            // データベースを更新
            $pdo = EnhancedDatabase::getConnection();
            $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, stock = ?, stock_quantity = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$name, $category, $price, $stock, $stock, $description, $id]);

            if ($result && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => '商品を更新しました。']);
            } else {
                throw new Exception('商品の更新に失敗しました。商品が見つからない可能性があります。');
            }
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            // バリデーション
            if ($id <= 0) {
                throw new Exception('無効な商品IDです。');
            }

            // 商品の画像パスを取得
            $pdo = EnhancedDatabase::getConnection();
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            // データベースから削除
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $result = $stmt->execute([$id]);

            if ($result && $stmt->rowCount() > 0) {
                // 画像ファイルも削除
                if ($product && !empty($product['image']) && file_exists($product['image'])) {
                    unlink($product['image']);
                }
                
                // 削除成功の場合はリダイレクト
                $_SESSION['message'] = '商品を削除しました。';
                header('Location: product_list.php');
                exit();
            } else {
                throw new Exception('商品の削除に失敗しました。商品が見つからない可能性があります。');
            }
            break;

        default:
            throw new Exception('無効なアクションです。');
    }

} catch (Exception $e) {
    // 削除の場合はページにリダイレクト
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $_SESSION['error'] = $e->getMessage();
        header('Location: product_list.php');
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>