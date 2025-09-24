<?php
session_start();
require_once __DIR__ . '/enhanced_config.php';

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success = '';
$errors = [];
$product = null;

// POST リクエストの場合（削除実行）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($product_id > 0) {
        try {
            // 商品情報を取得（削除前に確認）
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if ($product) {
                // 関連する注文があるかチェック（オプション）
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $order_count = $stmt->fetchColumn();
                
                if ($order_count > 0) {
                    // 注文履歴がある場合は論理削除（deleted_atフィールドを使用）
                    $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $success = "商品「{$product['name']}」を削除しました（注文履歴があるため非表示にしました）。";
                } else {
                    // 注文履歴がない場合は物理削除
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $success = "商品「{$product['name']}」を完全に削除しました。";
                }
                
                // 3秒後に商品一覧にリダイレクト
                header("refresh:3;url=product_list.php");
                
            } else {
                $errors[] = '指定された商品が見つかりません。';
            }
            
        } catch(PDOException $e) {
            $errors[] = 'データベースエラー: ' . $e->getMessage();
        }
    } else {
        $errors[] = '無効な商品IDです。';
    }
}

// GET リクエストの場合（削除確認画面）
elseif (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    
    if ($product_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $errors[] = '指定された商品が見つかりません。';
            } else {
                // 関連する注文数を取得
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $order_count = $stmt->fetchColumn();
            }
            
        } catch(PDOException $e) {
            $errors[] = 'データベースエラー: ' . $e->getMessage();
        }
    } else {
        $errors[] = '無効な商品IDです。';
    }
}

// IDが指定されていない場合は商品一覧に戻る
else {
    header('Location: product_list.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品削除 - 在庫管理システム</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 600px;
            padding: 20px;
            width: 100%;
        }
        
        .delete-card {
            background: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.18);
            text-align: center;
        }
        
        .delete-icon {
            font-size: 4em;
            color: #e74c3c;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .success-icon {
            font-size: 4em;
            color: #2ecc71;
            margin-bottom: 20px;
            animation: bounceIn 0.6s;
        }
        
        @keyframes bounceIn {
            0%, 20%, 40%, 60%, 80% {
                animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
            }
            0% {
                opacity: 0;
                transform: scale3d(.3, .3, .3);
            }
            20% {
                transform: scale3d(1.1, 1.1, 1.1);
            }
            40% {
                transform: scale3d(.9, .9, .9);
            }
            60% {
                opacity: 1;
                transform: scale3d(1.03, 1.03, 1.03);
            }
            80% {
                transform: scale3d(.97, .97, .97);
            }
            100% {
                opacity: 1;
                transform: scale3d(1, 1, 1);
            }
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .product-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        
        .product-info h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            color: #856404;
        }
        
        .danger-box {
            background: linear-gradient(135deg, #f8d7da, #fab1a0);
            border: 1px solid #fab1a0;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            color: #721c24;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
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
        
        .countdown {
            font-size: 0.9em;
            color: #666;
            margin-top: 15px;
        }
        
        .redirect-link {
            display: inline-block;
            margin-top: 15px;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        
        .redirect-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .delete-card {
                padding: 30px 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="delete-card">
            <?php if ($success): ?>
                <!-- 削除成功画面 -->
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>削除完了</h1>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <p class="countdown">
                    <i class="fas fa-clock"></i> 3秒後に商品一覧に戻ります...
                </p>
                <a href="product_list.php" class="redirect-link">
                    <i class="fas fa-arrow-right"></i> すぐに商品一覧に戻る
                </a>
                
            <?php elseif (!empty($errors)): ?>
                <!-- エラー画面 -->
                <div class="delete-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>エラーが発生しました</h1>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="button-group">
                    <a href="product_list.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> 商品一覧に戻る
                    </a>
                </div>
                
            <?php elseif ($product): ?>
                <!-- 削除確認画面 -->
                <div class="delete-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h1>商品削除の確認</h1>
                <p>以下の商品を削除しようとしています。</p>
                
                <div class="product-info">
                    <h3><i class="fas fa-box"></i> 削除対象商品</h3>
                    <div class="info-item">
                        <span class="info-label">商品名:</span>
                        <span><?php echo htmlspecialchars($product['name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">カテゴリ:</span>
                        <span><?php echo htmlspecialchars($product['category'] ?? 'その他'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">価格:</span>
                        <span>¥<?php echo number_format($product['price']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">在庫数:</span>
                        <span><?php echo number_format($product['stock_quantity']); ?>個</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">在庫価値:</span>
                        <span>¥<?php echo number_format($product['price'] * $product['stock_quantity']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">登録日:</span>
                        <span><?php echo date('Y年m月d日', strtotime($product['created_at'])); ?></span>
                    </div>
                </div>
                
                <?php if (isset($order_count) && $order_count > 0): ?>
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>注意:</strong> この商品には<?php echo number_format($order_count); ?>件の注文履歴があります。
                        削除すると商品は非表示になりますが、注文履歴は保持されます。
                    </div>
                <?php else: ?>
                    <div class="danger-box">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>警告:</strong> この商品を完全に削除します。この操作は取り消せません。
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                    <div class="button-group">
                        <a href="product_list.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> キャンセル
                        </a>
                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> 編集する
                        </a>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> 削除実行
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 削除確認
        function confirmDelete() {
            const productName = "<?php echo addslashes($product['name'] ?? ''); ?>";
            const hasOrders = <?php echo isset($order_count) && $order_count > 0 ? 'true' : 'false'; ?>;
            
            let message = `商品「${productName}」を削除しますか？\n\n`;
            
            if (hasOrders) {
                message += "この商品には注文履歴があるため、商品は非表示になりますが履歴は保持されます。\n";
            } else {
                message += "この操作は取り消せません。\n";
            }
            
            message += "\n削除を実行しますか？";
            
            if (confirm(message)) {
                // 二重確認
                if (confirm("本当に削除しますか？\n\nこの確認は最後です。")) {
                    document.getElementById('deleteForm').submit();
                }
            }
        }

        // キーボードショートカット
        document.addEventListener('keydown', function(e) {
            // Escapeでキャンセル
            if (e.key === 'Escape') {
                window.location.href = 'product_list.php';
            }
            
            // Deleteキーで削除確認（商品情報がある場合のみ）
            if (e.key === 'Delete' && document.getElementById('deleteForm')) {
                e.preventDefault();
                confirmDelete();
            }
        });

        // カウントダウン機能（成功時）
        <?php if ($success): ?>
        let countdown = 3;
        const countdownElement = document.querySelector('.countdown');
        
        const timer = setInterval(function() {
            countdown--;
            if (countdown > 0) {
                countdownElement.innerHTML = `<i class="fas fa-clock"></i> ${countdown}秒後に商品一覧に戻ります...`;
            } else {
                clearInterval(timer);
                window.location.href = 'product_list.php';
            }
        }, 1000);
        <?php endif; ?>

        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.delete-card');
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9) translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                card.style.opacity = '1';
                card.style.transform = 'scale(1) translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>