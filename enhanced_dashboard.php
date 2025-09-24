<?php
require_once __DIR__ . '/enhanced_config.php';

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// 統計データの取得
try {
    // 総商品数
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt->fetchColumn();
    
    // 総在庫価値（stock_quantity列が存在する場合は使用、そうでなければstock列を使用）
    $stmt = $pdo->query("SELECT SUM(price * COALESCE(stock_quantity, stock)) FROM products");
    $total_value = $stmt->fetchColumn() ?: 0;
    
    // 低在庫商品数（在庫が10個以下）
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE COALESCE(stock_quantity, stock) <= 10");
    $low_stock = $stmt->fetchColumn();
    
    // 最近の注文数（過去30日） - order_date列を使用
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $recent_orders = $stmt->fetchColumn();
    
    // 最近追加された商品（5件）
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5");
    $recent_products = $stmt->fetchAll();
    
    // 低在庫商品の詳細
    $stmt = $pdo->query("SELECT * FROM products WHERE COALESCE(stock_quantity, stock) <= 10 ORDER BY COALESCE(stock_quantity, stock) ASC LIMIT 10");
    $low_stock_products = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "データベースエラー: " . $e->getMessage();
}

// ファイル存在確認関数
function checkFileExists($filename) {
    return file_exists($filename);
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在庫管理システム - ダッシュボード</title>
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
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 25px;
            transition: transform 0.3s;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .products .stat-icon { color: #3498db; }
        .value .stat-icon { color: #2ecc71; }
        .low-stock .stat-icon { color: #e74c3c; }
        .orders .stat-icon { color: #9b59b6; }
        
        .quick-actions {
            background: rgba(255,255,255,0.95);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
        }
        
        .quick-actions h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
          background: linear-gradient(135deg, #000000ff 0%, #000000ff 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .action-btn.disabled {
            background: #95a5a6;
            cursor: not-allowed;
            position: relative;
        }
        
        .action-btn.disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .action-btn i {
            margin-right: 8px;
            font-size: 1.2em;
        }
        
        .file-status {
            font-size: 0.8em;
            opacity: 0.8;
            display: block;
            margin-top: 5px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: rgba(255,255,255,0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
        }
        
        .card h3 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .product-list {
            list-style: none;
        }
        
        .product-item {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-info h4 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .product-info p {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.8em;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-small:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .btn-small.disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .btn-small.disabled:hover {
            transform: none;
            opacity: 1;
        }
        
        .stock-warning {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .stock-ok {
            color: #2ecc71;
        }
        
        .error {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .file-check {
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }
        
        .file-exists {
            background: #2ecc71;
            color: white;
        }
        
        .file-missing {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="header">
            <div class="user-info">
                <div>
                    <h1><i class="fas fa-tachometer-alt"></i> 在庫管理ダッシュボード</h1>
                    <p>ようこそ、<?php echo htmlspecialchars($_SESSION['username'] ?? 'ゲスト'); ?>さん</p>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> ログアウト
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card products">
                <div class="stat-icon"><i class="fas fa-box"></i></div>
                <div class="stat-number"><?php echo number_format($total_products ?? 0); ?></div>
                <div class="stat-label">総商品数</div>
            </div>
            
            <div class="stat-card value">
                <div class="stat-icon"><i class="fas fa-yen-sign"></i></div>
                <div class="stat-number">¥<?php echo number_format($total_value ?? 0); ?></div>
                <div class="stat-label">総在庫価値</div>
            </div>
            
            <div class="stat-card low-stock">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($low_stock ?? 0); ?></div>
                <div class="stat-label">低在庫商品</div>
            </div>
            
            <div class="stat-card orders">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-number"><?php echo number_format($recent_orders ?? 0); ?></div>
                <div class="stat-label">今月の注文</div>
            </div>
        </div>

        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> クイックアクション</h2>
            <div class="action-buttons">
                <?php
              $actions = [
                    ['file' => 'add_product.php', 'icon' => 'fas fa-plus', 'text' => '商品追加'],
                    ['file' => 'product_list.php', 'icon' => 'fas fa-list', 'text' => '商品一覧'],
                    ['file' => 'user_management.php', 'icon' => 'fas fa-users', 'text' => 'ユーザー管理'],
                    ['file' => 'inventory_report.php', 'icon' => 'fas fa-chart-bar', 'text' => '在庫レポート'],
                    ['file' => 'order_management.php', 'icon' => 'fas fa-clipboard-list', 'text' => '注文管理'],
            ['file' => 'settings.php', 'icon' => 'fas fa-cog', 'text' => '設定'],
            ['file' => 'two_factor_setup.php', 'icon' => 'fas fa-shield-alt', 'text' => '二段階認証設定'],
                ];
                
                foreach ($actions as $action):
                    $fileExists = checkFileExists($action['file']);
                ?>
                    <?php if ($fileExists): ?>
                        <a href="<?php echo $action['file']; ?>" class="action-btn">
                            <i class="<?php echo $action['icon']; ?>"></i>
                            <?php echo $action['text']; ?>
                            <span class="file-check file-exists">✓</span>
                        </a>
                    <?php else: ?>
                        <span class="action-btn disabled">
                            <i class="<?php echo $action['icon']; ?>"></i>
                            <?php echo $action['text']; ?>
                            <span class="file-check file-missing">✗</span>
                            <span class="file-status">ファイルが見つかりません</span>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h3><i class="fas fa-clock"></i> 最近追加された商品</h3>
                <?php if (!empty($recent_products)): ?>
                    <ul class="product-list">
                        <?php foreach ($recent_products as $product): ?>
                            <li class="product-item">
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <p>価格: ¥<?php echo number_format($product['price']); ?> | 
                                       在庫: <?php echo ($product['stock_quantity'] ?? $product['stock']); ?>個</p>
                                </div>
                                <div class="product-actions">
                                    <?php if (checkFileExists('edit_product.php')): ?>
                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn-small btn-edit">
                                            <i class="fas fa-edit"></i> 編集
                                        </a>
                                    <?php else: ?>
                                        <span class="btn-small disabled">
                                            <i class="fas fa-edit"></i> 編集
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (checkFileExists('delete_product.php')): ?>
                                        <a href="delete_product.php?id=<?php echo $product['id']; ?>" 
                                           class="btn-small btn-delete"
                                           onclick="return confirm('この商品を削除しますか？')">
                                            <i class="fas fa-trash"></i> 削除
                                        </a>
                                    <?php else: ?>
                                        <span class="btn-small disabled">
                                            <i class="fas fa-trash"></i> 削除
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>商品がありません。</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3><i class="fas fa-exclamation-triangle"></i> 低在庫商品</h3>
                <?php if (!empty($low_stock_products)): ?>
                    <ul class="product-list">
                        <?php foreach ($low_stock_products as $product): ?>
                            <?php $current_stock = $product['stock_quantity'] ?? $product['stock']; ?>
                            <li class="product-item">
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <p>価格: ¥<?php echo number_format($product['price']); ?></p>
                                    <p class="stock-warning">
                                        在庫: <?php echo $current_stock; ?>個
                                        <?php if ($current_stock == 0): ?>
                                            <strong>（在庫切れ）</strong>
                                        <?php elseif ($current_stock <= 5): ?>
                                            <strong>（緊急補充必要）</strong>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="product-actions">
                                    <?php if (checkFileExists('edit_product.php')): ?>
                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn-small btn-edit">
                                            <i class="fas fa-edit"></i> 補充
                                        </a>
                                    <?php else: ?>
                                        <span class="btn-small disabled">
                                            <i class="fas fa-edit"></i> 補充
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="stock-ok">すべての商品の在庫は十分です。</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });

        // リアルタイム時刻表示
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('ja-JP');
            document.title = `在庫管理システム - ${timeString}`;
        }
        
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>