<?php
require_once __DIR__ . '/enhanced_config.php';

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success = '';
$errors = [];

// 注文ステータスの更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = (int)$_POST['order_id'];
    
    if ($action === 'update_status') {
        $new_status = $_POST['status'];
        $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (in_array($new_status, $valid_statuses)) {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $order_id]);
                $success = '注文ステータスを更新しました。';
            } catch(PDOException $e) {
                $errors[] = 'ステータスの更新に失敗しました: ' . $e->getMessage();
            }
        } else {
            $errors[] = '無効なステータスです。';
        }
    } elseif ($action === 'delete_order') {
        try {
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $success = '注文を削除しました。';
        } catch(PDOException $e) {
            $errors[] = '注文の削除に失敗しました: ' . $e->getMessage();
        }
    }
}

try {
    // 注文統計の取得
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT SUM(total_price) FROM orders");
    $total_revenue = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $pending_orders = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'");
    $shipped_orders = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT AVG(total_price) FROM orders");
    $average_order_value = $stmt->fetchColumn() ?: 0;
    
    // 注文一覧の取得（ユーザー名と商品名を結合）
    $stmt = $pdo->query("
        SELECT 
            o.*,
            u.username,
            u.name as user_name,
            p.name as product_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN products p ON o.product_id = p.id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll();
    
    // ステータス別統計
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status 
        ORDER BY count DESC
    ");
    $status_stats = $stmt->fetchAll();
    
    // 今月の注文推移（デモデータ）
    $daily_orders = [];
    for ($i = 30; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $daily_orders[] = [
            'date' => date('m/d', strtotime("-{$i} days")),
            'count' => rand(0, 10),
            'revenue' => rand(10000, 100000)
        ];
    }
    
} catch(PDOException $e) {
    $errors[] = 'データの取得に失敗しました: ' . $e->getMessage();
}

// ステータスの日本語名とアイコン
$status_info = [
    'pending' => ['name' => '待機中', 'icon' => 'clock', 'color' => '#f39c12'],
    'processing' => ['name' => '処理中', 'icon' => 'cog', 'color' => '#3498db'],
    'shipped' => ['name' => '発送済み', 'icon' => 'truck', 'color' => '#9b59b6'],
    'delivered' => ['name' => '配達完了', 'icon' => 'check-circle', 'color' => '#2ecc71'],
    'cancelled' => ['name' => 'キャンセル', 'icon' => 'times-circle', 'color' => '#e74c3c']
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文管理 - 在庫管理システム</title>
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
            max-width: 1400px;
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
        
        .orders .stat-icon { color: #3498db; }
        .revenue .stat-icon { color: #2ecc71; }
        .pending .stat-icon { color: #f39c12; }
        .shipped .stat-icon { color: #9b59b6; }
        .average .stat-icon { color: #e67e22; }
        
        .filter-controls {
            background: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            padding: 8px 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 0.9em;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .card {
            background: rgba(255,255,255,0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
            margin-bottom: 20px;
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
            margin-top: 10px;
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
            position: sticky;
            top: 0;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-shipped {
            background: #e2d9f3;
            color: #6f42c1;
        }
        
        .status-delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
            font-size: 0.9em;
            margin: 2px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8em;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #a7f3d0);
            color: #155724;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #fecaca);
            color: #721c24;
            border: 1px solid #fecaca;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-left: 40px;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .stats-overview {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .table {
                font-size: 0.9em;
            }
            
            .table th,
            .table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-clipboard-list"></i> 注文管理</h1>
                    <p>注文の追跡と管理</p>
                </div>
                <a href="enhanced_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> ダッシュボードに戻る
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

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
            <div class="stat-card orders">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-number"><?php echo number_format($total_orders ?? 0); ?></div>
                <div class="stat-label">総注文数</div>
            </div>
            
            <div class="stat-card revenue">
                <div class="stat-icon"><i class="fas fa-yen-sign"></i></div>
                <div class="stat-number">¥<?php echo number_format($total_revenue ?? 0); ?></div>
                <div class="stat-label">総売上</div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($pending_orders ?? 0); ?></div>
                <div class="stat-label">待機中注文</div>
            </div>
            
            <div class="stat-card shipped">
                <div class="stat-icon"><i class="fas fa-truck"></i></div>
                <div class="stat-number"><?php echo number_format($shipped_orders ?? 0); ?></div>
                <div class="stat-label">発送済み</div>
            </div>
            
            <div class="stat-card average">
                <div class="stat-icon"><i class="fas fa-calculator"></i></div>
                <div class="stat-number">¥<?php echo number_format($average_order_value ?? 0); ?></div>
                <div class="stat-label">平均注文額</div>
            </div>
        </div>

        <!-- フィルター機能 -->
        <div class="filter-controls">
            <div class="filter-group search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="注文を検索...">
            </div>
            
            <div class="filter-group">
                <label for="statusFilter">ステータス:</label>
                <select id="statusFilter" class="form-control">
                    <option value="">すべて</option>
                    <?php foreach ($status_info as $status => $info): ?>
                        <option value="<?php echo $status; ?>"><?php echo $info['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="dateFilter">期間:</label>
                <select id="dateFilter" class="form-control">
                    <option value="">すべて</option>
                    <option value="today">今日</option>
                    <option value="week">今週</option>
                    <option value="month">今月</option>
                </select>
            </div>
            
            <div class="filter-group">
                <button class="btn btn-info" onclick="exportOrders()">
                    <i class="fas fa-download"></i> エクスポート
                </button>
            </div>
        </div>

        <!-- 注文一覧 -->
        <div class="card">
            <h3><i class="fas fa-list"></i> 注文一覧</h3>
            <?php if (!empty($orders)): ?>
                <div class="table-container">
                    <table class="table" id="ordersTable">
                        <thead>
                            <tr>
                                <th>注文ID</th>
                                <th>顧客</th>
                                <th>商品</th>
                                <th>数量</th>
                                <th>金額</th>
                                <th>ステータス</th>
                                <th>注文日</th>
                                <th>更新日</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr data-status="<?php echo $order['status']; ?>" data-date="<?php echo $order['created_at']; ?>">
                                    <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['user_name'] ?? 'N/A'); ?></strong><br>
                                        <small>@<?php echo htmlspecialchars($order['username'] ?? 'unknown'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['product_name'] ?? '削除された商品'); ?></td>
                                    <td><?php echo number_format($order['quantity']); ?>個</td>
                                    <td>¥<?php echo number_format($order['total_price']); ?></td>
                                    <td>
                                        <?php 
                                        $status = $order['status'];
                                        $info = $status_info[$status] ?? ['name' => $status, 'icon' => 'question', 'color' => '#999'];
                                        ?>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <i class="fas fa-<?php echo $info['icon']; ?>"></i>
                                            <?php echo $info['name']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y/m/d H:i', strtotime($order['created_at'])); ?></td>
                                    <td><?php echo date('Y/m/d H:i', strtotime($order['updated_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-primary btn-sm" onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                                <i class="fas fa-edit"></i> ステータス変更
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-eye"></i> 詳細
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteOrder(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-trash"></i> 削除
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-shopping-cart" style="font-size: 3em; margin-bottom: 15px; opacity: 0.3;"></i>
                    <h4>注文がありません</h4>
                    <p>まだ注文が登録されていません。</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ステータス更新モーダル -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> ステータス更新</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="modalOrderId">
                
                <div class="form-group">
                    <label for="modalStatus">新しいステータス:</label>
                    <select name="status" id="modalStatus" class="form-control" required>
                        <?php foreach ($status_info as $status => $info): ?>
                            <option value="<?php echo $status; ?>">
                                <?php echo $info['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: right; gap: 10px; display: flex; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> キャンセル
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 更新
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // モーダル関連
        function openStatusModal(orderId, currentStatus) {
            document.getElementById('modalOrderId').value = orderId;
            document.getElementById('modalStatus').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        // 注文詳細表示
        function viewOrderDetails(orderId) {
            alert(`注文 #${String(orderId).padStart(6, '0')} の詳細を表示します（デモ機能）`);
        }
        
        // 注文削除
        function deleteOrder(orderId) {
            if (confirm('この注文を削除しますか？この操作は取り消せません。')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_order">
                    <input type="hidden" name="order_id" value="${orderId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // エクスポート機能
        function exportOrders() {
            alert('注文データをCSVでエクスポートします（デモ機能）');
        }
        
        // 検索・フィルタ機能
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        document.getElementById('dateFilter').addEventListener('change', filterTable);
        
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const rows = document.querySelectorAll('#ordersTable tbody tr');
            
            rows.forEach(row => {
                let show = true;
                
                // テキスト検索
                if (searchTerm) {
                    const text = row.textContent.toLowerCase();
                    if (!text.includes(searchTerm)) {
                        show = false;
                    }
                }
                
                // ステータスフィルタ
                if (statusFilter && row.dataset.status !== statusFilter) {
                    show = false;
                }
                
                // 日付フィルタ
                if (dateFilter) {
                    const orderDate = new Date(row.dataset.date);
                    const now = new Date();
                    let showDate = false;
                    
                    switch (dateFilter) {
                        case 'today':
                            showDate = orderDate.toDateString() === now.toDateString();
                            break;
                        case 'week':
                            const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                            showDate = orderDate >= weekAgo;
                            break;
                        case 'month':
                            const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                            showDate = orderDate >= monthAgo;
                            break;
                    }
                    
                    if (!showDate) {
                        show = false;
                    }
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                closeModal();
            }
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
        });
        
        // リアルタイム時刻更新
        setInterval(() => {
            const time = new Date().toLocaleTimeString('ja-JP');
            document.title = `注文管理 - ${time}`;
        }, 1000);
    </script>
</body>
</html>