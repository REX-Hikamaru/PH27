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
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 商品情報の取得
if ($product_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $_SESSION['message'] = '指定された商品が見つかりません。';
            header('Location: enhanced_dashboard.php');
            exit();
        }
    } catch(PDOException $e) {
        $errors[] = '商品情報の取得に失敗しました: ' . $e->getMessage();
    }
} else {
    $_SESSION['message'] = '商品IDが指定されていません。';
    header('Location: enhanced_dashboard.php');
    exit();
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $minimum_stock = intval($_POST['minimum_stock'] ?? 5);
    $description = trim($_POST['description'] ?? '');
    
    // バリデーション
    if (empty($name)) {
        $errors[] = '商品名を入力してください。';
    }
    if (empty($category)) {
        $errors[] = 'カテゴリを選択してください。';
    }
    if ($price <= 0) {
        $errors[] = '価格は0より大きい値を入力してください。';
    }
    if ($stock_quantity < 0) {
        $errors[] = '在庫数は0以上の値を入力してください。';
    }
    if ($minimum_stock < 0) {
        $errors[] = '最小在庫数は0以上の値を入力してください。';
    }
    
    // 画像アップロード処理
    $image_path = $product['image']; // 既存の画像パスを保持
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = '画像はJPEG、PNG、GIF、WebP形式のみ対応しています。';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = '画像サイズは5MB以下にしてください。';
        } else {
            // アップロードディレクトリの作成
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // ファイル名の生成（重複を避ける）
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'product_' . $product_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // 古い画像ファイルの削除
                if ($product['image'] && file_exists($product['image'])) {
                    unlink($product['image']);
                }
                $image_path = $upload_path;
            } else {
                $errors[] = '画像のアップロードに失敗しました。';
            }
        }
    }
    
    // エラーがなければ更新処理
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, category = ?, price = ?, stock_quantity = ?, 
                    minimum_stock = ?, description = ?, image = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $name, $category, $price, $stock_quantity, 
                $minimum_stock, $description, $image_path, $product_id
            ])) {
                $_SESSION['message'] = '商品情報を更新しました。';
                header('Location: enhanced_dashboard.php');
                exit();
            } else {
                $errors[] = '商品情報の更新に失敗しました。';
            }
            
        } catch(PDOException $e) {
            $errors[] = 'データベースエラー: ' . $e->getMessage();
        }
    }
    
    // エラーがある場合は入力値を保持
    if (!empty($errors)) {
        $product['name'] = $name;
        $product['category'] = $category;
        $product['price'] = $price;
        $product['stock_quantity'] = $stock_quantity;
        $product['minimum_stock'] = $minimum_stock;
        $product['description'] = $description;
    }
}

// カテゴリ一覧
$categories = ['電子機器', '文房具', '食品', '衣類', '書籍', 'その他'];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品編集 - 在庫管理システム</title>
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
            max-width: 800px;
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
        
        .form-container {
            background: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 15px;
            border: 2px dashed #e1e8ed;
            border-radius: 10px;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        
        .file-upload:hover .file-upload-label {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .current-image {
            margin-top: 10px;
            text-align: center;
        }
        
        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .image-preview {
            margin-top: 10px;
            text-align: center;
            display: none;
        }
        
        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 12px 25px;
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
            margin-right: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
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
        
        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .stock-low {
            color: #e74c3c;
        }
        
        .stock-ok {
            color: #27ae60;
        }
        
        .stock-out {
            color: #c0392b;
            font-weight: bold;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-btn {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid #667eea;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s;
        }
        
        .quick-btn:hover {
            background: #667eea;
            color: white;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }
            
            .quick-actions {
                justify-content: center;
            }
        }
        
        .required {
            color: #e74c3c;
        }
        
        .help-text {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-edit"></i> 商品編集</h1>
                    <p>商品情報を更新してください</p>
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

        <div class="form-container">
            <div class="quick-actions">
                <button type="button" class="quick-btn" onclick="setStock(0)">
                    <i class="fas fa-ban"></i> 在庫切れに設定
                </button>
                <button type="button" class="quick-btn" onclick="setStock(10)">
                    <i class="fas fa-plus"></i> 在庫10個補充
                </button>
                <button type="button" class="quick-btn" onclick="setStock(50)">
                    <i class="fas fa-plus-circle"></i> 在庫50個補充
                </button>
                <button type="button" class="quick-btn" onclick="setStock(100)">
                    <i class="fas fa-warehouse"></i> 在庫100個補充
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">商品名 <span class="required">*</span></label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($product['name']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">カテゴリ <span class="required">*</span></label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">カテゴリを選択</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo ($product['category'] === $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">価格（円） <span class="required">*</span></label>
                        <input type="number" 
                               id="price" 
                               name="price" 
                               class="form-control" 
                               step="0.01" 
                               min="0.01" 
                               value="<?php echo htmlspecialchars($product['price']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_quantity">在庫数 <span class="required">*</span></label>
                        <input type="number" 
                               id="stock_quantity" 
                               name="stock_quantity" 
                               class="form-control" 
                               min="0" 
                               value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" 
                               required>
                        <div class="stock-status" id="stock-status">
                            <!-- JavaScriptで動的に更新 -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="minimum_stock">最小在庫数</label>
                        <input type="number" 
                               id="minimum_stock" 
                               name="minimum_stock" 
                               class="form-control" 
                               min="0" 
                               value="<?php echo htmlspecialchars($product['minimum_stock']); ?>">
                        <div class="help-text">この数を下回ると低在庫として警告されます</div>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="description">商品説明</label>
                    <textarea id="description" 
                              name="description" 
                              class="form-control" 
                              placeholder="商品の詳細説明を入力してください"><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label for="image">商品画像</label>
                    <div class="file-upload">
                        <input type="file" 
                               id="image" 
                               name="image" 
                               accept="image/*" 
                               onchange="previewImage(this)">
                        <div class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            クリックして画像を選択（JPEG、PNG、GIF、WebP）
                        </div>
                    </div>
                    
                    <?php if ($product['image'] && file_exists($product['image'])): ?>
                        <div class="current-image">
                            <p><strong>現在の画像:</strong></p>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="現在の商品画像">
                        </div>
                    <?php endif; ?>
                    
                    <div class="image-preview" id="image-preview">
                        <p><strong>プレビュー:</strong></p>
                        <img id="preview-img" alt="プレビュー画像">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 更新する
                    </button>
                    <a href="enhanced_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> キャンセル
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 在庫数のクイック設定
        function setStock(amount) {
            const stockInput = document.getElementById('stock_quantity');
            const currentStock = parseInt(stockInput.value) || 0;
            
            if (amount === 0) {
                stockInput.value = 0;
            } else {
                stockInput.value = currentStock + amount;
            }
            
            updateStockStatus();
        }

        // 在庫状況の表示更新
        function updateStockStatus() {
            const stockInput = document.getElementById('stock_quantity');
            const minimumInput = document.getElementById('minimum_stock');
            const statusDiv = document.getElementById('stock-status');
            
            const stock = parseInt(stockInput.value) || 0;
            const minimum = parseInt(minimumInput.value) || 5;
            
            let statusText = '';
            let statusClass = '';
            
            if (stock === 0) {
                statusText = '<i class="fas fa-times-circle"></i> 在庫切れ';
                statusClass = 'stock-out';
            } else if (stock <= minimum) {
                statusText = '<i class="fas fa-exclamation-triangle"></i> 低在庫（補充推奨）';
                statusClass = 'stock-low';
            } else {
                statusText = '<i class="fas fa-check-circle"></i> 在庫十分';
                statusClass = 'stock-ok';
            }
            
            statusDiv.innerHTML = statusText;
            statusDiv.className = 'stock-status ' + statusClass;
        }

        // 画像プレビュー
        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }

        // リアルタイム在庫状況更新
        document.addEventListener('DOMContentLoaded', function() {
            const stockInput = document.getElementById('stock_quantity');
            const minimumInput = document.getElementById('minimum_stock');
            
            stockInput.addEventListener('input', updateStockStatus);
            minimumInput.addEventListener('input', updateStockStatus);
            
            // 初期状態の表示
            updateStockStatus();
            
            // フォームのアニメーション
            const formContainer = document.querySelector('.form-container');
            formContainer.style.opacity = '0';
            formContainer.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                formContainer.style.transition = 'opacity 0.5s, transform 0.5s';
                formContainer.style.opacity = '1';
                formContainer.style.transform = 'translateY(0)';
            }, 100);
        });

        // フォーム送信前の確認
        document.querySelector('form').addEventListener('submit', function(e) {
            const stockInput = document.getElementById('stock_quantity');
            const stock = parseInt(stockInput.value) || 0;
            
            if (stock === 0) {
                if (!confirm('在庫数が0になっています。このまま更新しますか？')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // 送信ボタンを無効化（重複送信防止）
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 更新中...';
        });
    </script>
</body>
</html>