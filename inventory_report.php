<?php
require_once __DIR__ . '/enhanced_config.php';

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$errors = [];

try {
    // 在庫統計の取得
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT SUM(price * stock_quantity) FROM products");
    $total_value = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 10");
    $low_stock_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0");
    $out_of_stock_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT AVG(stock_quantity) FROM products");
    $average_stock = $stmt->fetchColumn() ?: 0;
    
    // カテゴリ別在庫統計
    $stmt = $pdo->query("
        SELECT 
            category,
            COUNT(*) as product_count,
            SUM(stock_quantity) as total_stock,
            SUM(price * stock_quantity) as category_value,
            AVG(stock_quantity) as avg_stock
        FROM products 
        GROUP BY category 
        ORDER BY category_value DESC
    ");
    $category_stats = $stmt->fetchAll();
    
    // 低在庫商品の詳細
    $stmt = $pdo->query("
        SELECT * FROM products 
        WHERE stock_quantity <= 10 
        ORDER BY stock_quantity ASC, name ASC
    ");
    $low_stock_products = $stmt->fetchAll();
    
    // 高価値商品トップ10
    $stmt = $pdo->query("
        SELECT *, (price * stock_quantity) as total_value 
        FROM products 
        ORDER BY total_value DESC 
        LIMIT 10
    ");
    $high_value_products = $stmt->fetchAll();
    
    // 月別在庫推移（デモデータ）
    $monthly_data = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-{$i} months"));
        $monthly_data[] = [
            'month' => date('Y年n月', strtotime("-{$i} months")),
            'total_products' => $total_products + rand(-10, 10),
            'total_value' => $total_value + rand(-100000, 100000),
            'low_stock' => $low_stock_count + rand(-5, 5)
        ];
    }
    
} catch(PDOException $e) {
    $errors[] = 'データの取得に失敗しました: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在庫レポート - 在庫管理システム</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
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
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .back-btn {
          background: linear-gradient(135deg, #000000ff 0%, #000000ff 100%);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 25px;
            transition: transform 0.3s;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .products .stat-icon { color: #3498db; }
        .value .stat-icon { color: #2ecc71; }
        .low-stock .stat-icon { color: #e74c3c; }
        .out-stock .stat-icon { color: #8e44ad; }
        .average .stat-icon { color: #f39c12; }
        
        .report-grid {
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
        
        .card.full-width {
            grid-column: 1 / -1;
        }
        
        .card h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .status-critical {
            background: #fee;
            color: #c53030;
        }
        
        .status-low {
            background: #fef5e7;
            color: #d69e2e;
        }
        
        .status-good {
            background: #f0fff4;
            color: #38a169;
        }
        
        .chart-container {
            height: 300px;
            margin-top: 20px;
            position: relative;
        }
        
        .chart-placeholder {
            height: 100%;
            background: linear-gradient(135deg, #f1f3f4, #e8eaed);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 1.1em;
        }
        
        .progress-bar {
            background: #e1e8ed;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
            transition: width 1s ease;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #fecaca);
            color: #721c24;
            border: 1px solid #fecaca;
        }
        
        @media (max-width: 768px) {
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }
            
            .export-buttons {
                justify-content: center;
            }
            
            .stats-overview {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-chart-bar"></i> 在庫レポート</h1>
                    <p>在庫状況の詳細分析とレポート</p>
                </div>
                <a href="enhanced_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> ダッシュボードに戻る
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <ul style="margin: 10px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- 統計概要 -->
        <div class="stats-overview">
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
                <div class="stat-number"><?php echo number_format($low_stock_count ?? 0); ?></div>
                <div class="stat-label">低在庫商品</div>
            </div>
            
            <div class="stat-card out-stock">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-number"><?php echo number_format($out_of_stock_count ?? 0); ?></div>
                <div class="stat-label">在庫切れ</div>
            </div>
            
            <div class="stat-card average">
                <div class="stat-icon"><i class="fas fa-calculator"></i></div>
                <div class="stat-number"><?php echo number_format($average_stock ?? 0, 1); ?></div>
                <div class="stat-label">平均在庫数</div>
            </div>
        </div>

        <!-- エクスポートボタン -->
        <div class="export-buttons">
            <button class="btn btn-success" onclick="exportCSV()">
                <i class="fas fa-file-csv"></i> CSVエクスポート
            </button>
            <button class="btn btn-info" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDFエクスポート
            </button>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="fas fa-print"></i> 印刷
            </button>
        </div>

        <div class="report-grid">
            <!-- カテゴリ別統計 -->
            <div class="card">
                <h3><i class="fas fa-chart-pie"></i> カテゴリ別在庫統計</h3>
                <?php if (!empty($category_stats)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>カテゴリ</th>
                                    <th>商品数</th>
                                    <th>総在庫</th>
                                    <th>価値</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($category_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['category']); ?></td>
                                        <td><?php echo number_format($stat['product_count']); ?></td>
                                        <td><?php echo number_format($stat['total_stock']); ?></td>
                                        <td>¥<?php echo number_format($stat['category_value']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>カテゴリデータがありません。</p>
                <?php endif; ?>
            </div>

            <!-- 月別推移 -->
            <div class="card">
                <h3><i class="fas fa-chart-line"></i> 月別在庫推移</h3>
                <div class="chart-container">
                    <div class="chart-placeholder">
                        <i class="fas fa-chart-line" style="font-size: 3em; opacity: 0.3;"></i>
                        <span style="margin-left: 15px;">チャート表示エリア</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 低在庫商品一覧 -->
        <div class="card full-width">
            <h3><i class="fas fa-exclamation-triangle"></i> 低在庫商品一覧</h3>
            <?php if (!empty($low_stock_products)): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>商品名</th>
                                <th>カテゴリ</th>
                                <th>現在庫</th>
                                <th>単価</th>
                                <th>状態</th>
                                <th>作成日</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><?php echo number_format($product['stock_quantity']); ?>個</td>
                                    <td>¥<?php echo number_format($product['price']); ?></td>
                                    <td>
                                        <?php if ($product['stock_quantity'] == 0): ?>
                                            <span class="status-badge status-critical">在庫切れ</span>
                                        <?php elseif ($product['stock_quantity'] <= 5): ?>
                                            <span class="status-badge status-low">緊急補充</span>
                                        <?php else: ?>
                                            <span class="status-badge status-low">要注意</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y/m/d', strtotime($product['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #2ecc71;">
                    <i class="fas fa-check-circle" style="font-size: 3em; margin-bottom: 15px;"></i>
                    <h4>すべての商品の在庫は十分です</h4>
                    <p>現在、低在庫の商品はありません。</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 高価値商品 -->
        <div class="card full-width">
            <h3><i class="fas fa-star"></i> 高価値商品トップ10</h3>
            <?php if (!empty($high_value_products)): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>順位</th>
                                <th>商品名</th>
                                <th>カテゴリ</th>
                                <th>単価</th>
                                <th>在庫数</th>
                                <th>総価値</th>
                                <th>価値比率</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($high_value_products as $index => $product): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td>¥<?php echo number_format($product['price']); ?></td>
                                    <td><?php echo number_format($product['stock_quantity']); ?>個</td>
                                    <td>¥<?php echo number_format($product['total_value']); ?></td>
                                    <td>
                                        <?php 
                                        $percentage = $total_value > 0 ? ($product['total_value'] / $total_value) * 100 : 0;
                                        echo number_format($percentage, 1) . '%';
                                        ?>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>商品データがありません。</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function exportCSV() {
            alert('CSVファイルをエクスポートします（デモ機能）');
        }
        
        function exportPDF() {
            alert('PDFファイルをエクスポートします（デモ機能）');
        }
        
        function printReport() {
            window.print();
        }
        
        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
            
            // プログレスバーのアニメーション
            setTimeout(() => {
                const progressBars = document.querySelectorAll('.progress-fill');
                progressBars.forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                });
            }, 1000);
        });
        
        // リアルタイム更新（デモ）
        setInterval(() => {
            const time = new Date().toLocaleTimeString('ja-JP');
            document.title = `在庫レポート - ${time}`;
        }, 1000);
    </script>
</body>
</html>