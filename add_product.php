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

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stock_quantity = trim($_POST['stock_quantity'] ?? '');
    $minimum_stock = trim($_POST['minimum_stock'] ?? '5');
    $description = trim($_POST['description'] ?? '');
    
    // バリデーション
    if (empty($name)) {
        $errors[] = '商品名を入力してください。';
    }
    if (empty($category)) {
        $errors[] = 'カテゴリを選択してください。';
    }
    if (empty($price) || !is_numeric($price) || floatval($price) < 0) {
        $errors[] = '正しい価格を入力してください。';
    }
    if (empty($stock_quantity) || !is_numeric($stock_quantity) || intval($stock_quantity) < 0) {
        $errors[] = '正しい在庫数を入力してください。';
    }
    if (!empty($minimum_stock) && (!is_numeric($minimum_stock) || intval($minimum_stock) < 0)) {
        $errors[] = '正しい最小在庫数を入力してください。';
    }
    
    // 画像アップロード処理
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_dir = 'uploads/';
        
        // アップロードディレクトリが存在しない場合は作成
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_info = getimagesize($_FILES['image']['tmp_name']);
            
            if ($file_info === false) {
                $errors[] = 'アップロードされたファイルは画像ファイルではありません。';
            } elseif (!in_array($file_info['mime'], $allowed_types)) {
                $errors[] = '対応していない画像形式です。JPEG、PNG、GIF、WebPファイルをアップロードしてください。';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB制限
                $errors[] = '画像ファイルのサイズが大きすぎます。5MB以下のファイルをアップロードしてください。';
            } else {
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $image_path = $upload_dir . $unique_filename;
                
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                    $errors[] = '画像のアップロードに失敗しました。';
                    $image_path = null;
                }
            }
        } else {
            $errors[] = '画像アップロード中にエラーが発生しました。';
        }
    }
    
    // エラーがない場合はデータベースに挿入
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, category, price, stock_quantity, minimum_stock, description, image, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $result = $stmt->execute([
                $name,
                $category,
                floatval($price),
                intval($stock_quantity),
                intval($minimum_stock),
                $description,
                $image_path,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $success = '商品が正常に追加されました。';
                // フォームをリセット
                $_POST = [];
            } else {
                $errors[] = '商品の追加に失敗しました。';
            }
            
        } catch (PDOException $e) {
            $errors[] = 'データベースエラー: ' . $e->getMessage();
            
            // アップロードされた画像を削除
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
        }
    }
}

// カテゴリ一覧を取得
$categories = [
    '電子機器',
    '家具・インテリア',
    '衣類・ファッション',
    '食品・飲料',
    '書籍・文具',
    'スポーツ・アウトドア',
    'ヘルス・ビューティー',
    'ホビー・エンタメ',
    'その他'
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品追加 - 在庫管理システム</title>
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
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        
        .form-control[type="file"] {
            padding: 8px 15px;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
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
            margin: 5px;
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
        
        .image-preview {
            margin-top: 10px;
            text-align: center;
        }
        
        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .image-preview-placeholder {
            width: 200px;
            height: 200px;
            border: 2px dashed #ccc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            margin: 10px auto;
            flex-direction: column;
            gap: 10px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .help-text {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-plus-circle"></i> 商品追加</h1>
                    <p>新しい商品を在庫に追加します</p>
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
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">商品名 <span class="required">*</span></label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">カテゴリ <span class="required">*</span></label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">カテゴリを選択してください</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : ''; ?>>
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
                               min="0" 
                               step="0.01"
                               value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_quantity">在庫数 <span class="required">*</span></label>
                        <input type="number" 
                               id="stock_quantity" 
                               name="stock_quantity" 
                               class="form-control" 
                               min="0"
                               value="<?php echo htmlspecialchars($_POST['stock_quantity'] ?? ''); ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="minimum_stock">最小在庫数</label>
                        <input type="number" 
                               id="minimum_stock" 
                               name="minimum_stock" 
                               class="form-control" 
                               min="0"
                               value="<?php echo htmlspecialchars($_POST['minimum_stock'] ?? '5'); ?>">
                        <div class="help-text">在庫がこの数値を下回ると警告が表示されます</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">商品画像</label>
                        <input type="file" 
                               id="image" 
                               name="image" 
                               class="form-control"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="help-text">JPEG、PNG、GIF、WebP形式（最大5MB）</div>
                        <div class="image-preview" id="imagePreview">
                            <div class="image-preview-placeholder">
                                <i class="fas fa-image" style="font-size: 2em;"></i>
                                <span>画像プレビュー</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">商品説明</label>
                        <textarea id="description" 
                                  name="description" 
                                  class="form-control"
                                  placeholder="商品の詳細説明を入力してください..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 商品を追加
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> リセット
                        </button>
                    </div>
                    <div>
                        <small class="help-text"><span class="required">*</span> は必須項目です</small>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 画像プレビュー機能
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            
            if (file) {
                // ファイルサイズチェック（5MB）
                if (file.size > 5 * 1024 * 1024) {
                    alert('ファイルサイズが大きすぎます。5MB以下のファイルを選択してください。');
                    this.value = '';
                    return;
                }
                
                // ファイル形式チェック
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('対応していないファイル形式です。JPEG、PNG、GIF、WebPファイルを選択してください。');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="プレビュー">`;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = `
                    <div class="image-preview-placeholder">
                        <i class="fas fa-image" style="font-size: 2em;"></i>
                        <span>画像プレビュー</span>
                    </div>
                `;
            }
        });
        
        // フォームバリデーション
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const category = document.getElementById('category').value;
            const price = document.getElementById('price').value;
            const stockQuantity = document.getElementById('stock_quantity').value;
            
            if (!name) {
                alert('商品名を入力してください。');
                e.preventDefault();
                return;
            }
            
            if (!category) {
                alert('カテゴリを選択してください。');
                e.preventDefault();
                return;
            }
            
            if (!price || parseFloat(price) < 0) {
                alert('正しい価格を入力してください。');
                e.preventDefault();
                return;
            }
            
            if (!stockQuantity || parseInt(stockQuantity) < 0) {
                alert('正しい在庫数を入力してください。');
                e.preventDefault();
                return;
            }
        });
        
        // リセットボタンの処理
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            if (confirm('入力内容をリセットしますか？')) {
                document.getElementById('imagePreview').innerHTML = `
                    <div class="image-preview-placeholder">
                        <i class="fas fa-image" style="font-size: 2em;"></i>
                        <span>画像プレビュー</span>
                    </div>
                `;
            } else {
                return false;
            }
        });
        
        // 数値入力の制限
        document.getElementById('price').addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });
        
        document.getElementById('stock_quantity').addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });
        
        document.getElementById('minimum_stock').addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });
        
        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.form-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                container.style.transition = 'opacity 0.5s, transform 0.5s';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>