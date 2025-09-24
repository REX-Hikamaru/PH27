<?php
require_once 'enhanced_config.php';
checkAuth();

// ページング設定
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// 検索機能
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';

try {
    // 総件数を取得
    $countSql = "SELECT COUNT(*) FROM products WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $countSql .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    if (!empty($category)) {
        $countSql .= " AND category = ?";
        $params[] = $category;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn();
    $totalPages = ceil($totalCount / $limit);
    
    // 商品データを取得（ページング適用）
    $sql = "SELECT * FROM products WHERE 1=1";
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
    }
    
    if (!empty($category)) {
        $sql .= " AND category = ?";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT " . $limit . " OFFSET " . $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // カテゴリ一覧を取得（フィルタ用）
    $categoryStmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $error = "データベースエラー: " . $e->getMessage();
    if (DEBUG_MODE) {
        error_log($error);
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品一覧 - 在庫管理システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fffc32ff 0%, #59cce8ff 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 2.5em;
            text-align: center;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .btn {
            background: linear-gradient(135deg, #000000ff 0%, #000000ff 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .btn-danger {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
        }

        .search-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .products-grid {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .products-table th {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .products-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .products-table tbody tr:hover {
            background-color: #f8f9ff;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-low {
            background-color: #fee;
            color: #c53030;
        }

        .status-normal {
            background-color: #efe;
            color: #38a169;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 15px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 12px 18px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            text-decoration: none;
            color: #2c3e50;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }

        .pagination .current {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background: linear-gradient(45deg, #fee, #fed7d7);
            color: #c53030;
            border-left: 4px solid #e53e3e;
        }

        .alert-success {
            background: linear-gradient(45deg, #efe, #c6f6d5);
            color: #38a169;
            border-left: 4px solid #48bb78;
        }

        .no-data {
            text-align: center;
            padding: 50px;
            color: #666;
            font-size: 1.1em;
        }

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .products-table {
                font-size: 14px;
            }
            
            .products-table th,
            .products-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-boxes"></i> 商品一覧</h1>
            
            <div class="nav-buttons">
                <a href="enhanced_dashboard.php" class="btn">
                    <i class="fas fa-dashboard"></i> ダッシュボード
                </a>
                <a href="add_product.php" class="btn">
                    <i class="fas fa-plus"></i> 商品追加
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> ログアウト
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php echo displayMessages(); ?>

        <!-- 検索セクション -->
        <div class="search-section">
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search">商品名・説明で検索</label>
                    <input type="text" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="検索キーワードを入力">
                </div>
                
                <div class="form-group">
                    <label for="category">カテゴリフィルタ</label>
                    <select id="category" name="category">
                        <option value="">すべてのカテゴリ</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> 検索
                </button>
            </form>
        </div>

        <!-- 商品リスト -->
        <div class="products-grid">
            <?php if (!empty($products)): ?>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>画像</th>
                            <th>商品名</th>
                            <th>カテゴリ</th>
                            <th>価格</th>
                            <th>在庫数</th>
                            <th>状態</th>
                            <th>作成日</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             class="product-image">
                                    <?php else: ?>
                                        <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: #ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    <?php if (!empty($product['description'])): ?>
                                        <br><small style="color: #666;">
                                            <?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>
                                            <?php echo strlen($product['description']) > 50 ? '...' : ''; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td>¥<?php echo number_format($product['price']); ?></td>
                                <td><?php echo number_format($product['stock_quantity']); ?></td>
                                <td>
                                    <?php if ($product['stock_quantity'] <= ($product['minimum_stock'] ?? 5)): ?>
                                        <span class="status-badge status-low">在庫少</span>
                                    <?php else: ?>
                                        <span class="status-badge status-normal">正常</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y/m/d', strtotime($product['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                           class="btn-small btn-edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_product.php?id=<?php echo $product['id']; ?>" 
                                           class="btn-small btn-delete" 
                                           onclick="return confirm('本当に削除しますか？')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- ページネーション -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">
                                <i class="fas fa-chevron-left"></i> 前へ
                            </a>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">
                                次へ <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-box-open" style="font-size: 3em; margin-bottom: 20px; color: #ccc;"></i>
                    <p>商品が見つかりませんでした。</p>
                    <?php if (!empty($search) || !empty($category)): ?>
                        <a href="products_list.php" class="btn" style="margin-top: 15px;">
                            <i class="fas fa-refresh"></i> 検索条件をクリア
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (isset($totalCount)): ?>
            <div style="text-align: center; color: rgba(255,255,255,0.8); margin-top: 20px;">
                総件数: <?php echo number_format($totalCount); ?>件 
                (<?php echo $page; ?> / <?php echo $totalPages; ?> ページ)
            </div>
        <?php endif; ?>
    </div>
</body>
</html>