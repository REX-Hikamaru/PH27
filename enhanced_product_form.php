<?php
// ============================================
// 6. enhanced_product_form.php - 拡張商品フォーム 
// ============================================

require_once 'enhanced_config.php';
checkAuth();

$isEdit = isset($_GET['id']);
$product = null;
$error = '';

if ($isEdit) {
    $pdo = EnhancedDatabase::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['message'] = '指定された商品が見つかりません。';
        header('Location: enhanced_dashboard.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = '不正なリクエストです。';
    } else {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || empty($category) || $price <= 0 || $stock < 0) {
            $error = '必須項目を正しく入力してください。';
        } else {
            try {
                $imagePath = $product['image'] ?? null;
                
                if (!empty($_FILES['image']['name'])) {
                    // 新しい画像がアップロードされた場合
                    $newImagePath = handleImageUpload($_FILES['image']);
                    if ($newImagePath) {
                        // 古い画像を削除
                        if ($imagePath && file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                        $imagePath = $newImagePath;
                    }
                }
                
                $productData = [
                    'name' => $name,
                    'category' => $category,
                    'price' => $price,
                    'stock' => $stock,
                    'description' => $description,
                    'image' => $imagePath
                ];
                
                if ($isEdit) {
                    EnhancedDatabase::updateProduct($product['id'], $productData);
                    $successMessage = '商品情報を更新しました。';
                } else {
                    EnhancedDatabase::addProduct($productData);
                    $successMessage = '新しい商品を追加しました。';
                }
                
                $_SESSION['message'] = $successMessage;
                header('Location: product_list.php');
                exit();
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? '商品編集' : '商品追加'; ?> - 商品管理システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            margin: 20px auto;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 800px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
        }
        .image-preview {
            max-width: 200px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="product_list.php">
                <i class="bi bi-shop me-2"></i>商品管理システム
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username'] ?? 'ユーザー'); ?>さん
                </span>
                <a class="btn btn-outline-danger btn-sm" href="logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i>ログアウト
                </a>
            </div>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <div class="container">
        <div class="form-container">
            <!-- ヘッダー -->
            <div class="d-flex align-items-center mb-4">
                <a href="product_list.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h2>
                    <i class="bi bi-<?php echo $isEdit ? 'pencil' : 'plus-circle'; ?> me-2"></i>
                    <?php echo $isEdit ? '商品編集' : '商品追加'; ?>
                </h2>
            </div>

            <!-- エラーメッセージ -->
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- 商品フォーム -->
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                商品名 <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">
                                カテゴリ <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">選択してください</option>
                                <?php 
                                $categories = ['電子機器', '書籍', '衣類', '食品', 'その他'];
                                foreach ($categories as $cat): 
                                    $selected = ($product['category'] ?? '') === $cat ? 'selected' : '';
                                ?>
                                <option value="<?php echo $cat; ?>" <?php echo $selected; ?>>
                                    <?php echo $cat; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">
                                        価格 (円) <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="<?php echo $product['price'] ?? ''; ?>" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="stock" class="form-label">
                                        在庫数 <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control" id="stock" name="stock" 
                                           value="<?php echo $product['stock'] ?? $product['stock_quantity'] ?? ''; ?>" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="image" class="form-label">商品画像</label>
                            <input type="file" class="form-control" id="image" name="image" 
                                   accept="image/*" onchange="previewImage(this)">
                            <small class="text-muted">JPEG, PNG, GIF (最大5MB)</small>
                        </div>
                        
                        <div id="imagePreview">
                            <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="現在の画像" class="img-fluid image-preview">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="form-label">商品説明</label>
                    <textarea class="form-control" id="description" name="description" rows="4"
                              placeholder="商品の詳細説明を入力してください"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="product_list.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i>キャンセル
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo $isEdit ? '更新' : '追加'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="プレビュー" class="img-fluid image-preview">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>