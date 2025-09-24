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
$user_id = $_SESSION['user_id'];

// ユーザー情報を取得
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: login.php');
        exit();
    }
} catch(PDOException $e) {
    $errors[] = 'ユーザー情報の取得に失敗しました: ' . $e->getMessage();
}

// フォーム処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // プロフィール更新
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // バリデーション
        if (empty($username)) {
            $errors[] = 'ユーザー名を入力してください。';
        } elseif (strlen($username) < 3) {
            $errors[] = 'ユーザー名は3文字以上で入力してください。';
        }
        
        if (empty($name)) {
            $errors[] = '名前を入力してください。';
        }
        
        if (empty($email)) {
            $errors[] = 'メールアドレスを入力してください。';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '正しいメールアドレスを入力してください。';
        }
        
        // 重複チェック（自分以外）
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $user_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'そのユーザー名またはメールアドレスは既に使用されています。';
                }
            } catch(PDOException $e) {
                $errors[] = 'データベースエラー: ' . $e->getMessage();
            }
        }
        
        // 更新処理
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$username, $name, $email, $user_id]);
                
                $_SESSION['username'] = $username;
                $user['username'] = $username;
                $user['name'] = $name;
                $user['email'] = $email;
                
                $success = 'プロフィールを更新しました。';
            } catch(PDOException $e) {
                $errors[] = 'プロフィールの更新に失敗しました: ' . $e->getMessage();
            }
        }
        
    } elseif ($action === 'change_password') {
        // パスワード変更
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // バリデーション
        if (empty($current_password)) {
            $errors[] = '現在のパスワードを入力してください。';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = '現在のパスワードが正しくありません。';
        }
        
        if (empty($new_password)) {
            $errors[] = '新しいパスワードを入力してください。';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'パスワードは6文字以上で入力してください。';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'パスワードの確認が一致しません。';
        }
        
        // 更新処理
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                $success = 'パスワードを変更しました。';
            } catch(PDOException $e) {
                $errors[] = 'パスワードの変更に失敗しました: ' . $e->getMessage();
            }
        }
    }
}

// システム統計を取得
try {
    $stats = [];
    
    // 総商品数
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $stats['total_products'] = $stmt->fetchColumn();
    
    // ユーザーが作成した商品数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE created_by = ?");
    $stmt->execute([$user_id]);
    $stats['user_products'] = $stmt->fetchColumn();
    
    // 最後のログイン
    $stmt = $pdo->prepare("SELECT created_at FROM auth_logs WHERE user_id = ? AND action = 'login' AND success = 1 ORDER BY created_at DESC LIMIT 1 OFFSET 1");
    $stmt->execute([$user['user_id']]);
    $last_login = $stmt->fetchColumn();
    $stats['last_login'] = $last_login;
    
} catch(PDOException $e) {
    // エラーがあっても統計は表示しない
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定 - 在庫管理システム</title>
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
            max-width: 1000px;
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
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .card {
            background: rgba(255,255,255,0.95);
            padding: 30px;
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
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            margin: 5px 5px 5px 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
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
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .info-value {
            color: #7f8c8d;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
        }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .help-text {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .security-section {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #ffeaa7;
        }
        
        .security-section h4 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-cog"></i> 設定</h1>
                    <p>アカウント情報とシステム設定を管理</p>
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

        <div class="content-grid">
            <!-- プロフィール設定 -->
            <div class="card">
                <h3><i class="fas fa-user"></i> プロフィール設定</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="username">ユーザー名 <span class="required">*</span></label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['username']); ?>"
                               required>
                        <div class="help-text">3文字以上で入力してください</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">名前 <span class="required">*</span></label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['name']); ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">メールアドレス <span class="required">*</span></label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> プロフィールを更新
                    </button>
                </form>
            </div>

            <!-- パスワード変更 -->
            <div class="card">
                <h3><i class="fas fa-lock"></i> パスワード変更</h3>
                
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">現在のパスワード <span class="required">*</span></label>
                        <input type="password" 
                               id="current_password" 
                               name="current_password" 
                               class="form-control" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">新しいパスワード <span class="required">*</span></label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-control" 
                               minlength="6"
                               required>
                        <div class="help-text">6文字以上で入力してください</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">パスワードの確認 <span class="required">*</span></label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control" 
                               required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> パスワードを変更
                    </button>
                </form>
            </div>

            <!-- アカウント情報 -->
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> アカウント情報</h3>
                
                <div class="info-item">
                    <span class="info-label">ユーザーID</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['user_id']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">登録日</span>
                    <span class="info-value"><?php echo date('Y年m月d日 H:i', strtotime($user['created_at'])); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">最終更新</span>
                    <span class="info-value"><?php echo date('Y年m月d日 H:i', strtotime($user['updated_at'])); ?></span>
                </div>
                
                <?php if (isset($stats['last_login']) && $stats['last_login']): ?>
                <div class="info-item">
                    <span class="info-label">前回ログイン</span>
                    <span class="info-value"><?php echo date('Y年m月d日 H:i', strtotime($stats['last_login'])); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <span class="info-label">二段階認証</span>
                    <span class="info-value">
                        <?php if ($user['two_factor_enabled']): ?>
                            <span style="color: #27ae60;"><i class="fas fa-check-circle"></i> 有効</span>
                        <?php else: ?>
                            <span style="color: #e74c3c;"><i class="fas fa-times-circle"></i> 無効</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- セキュリティ設定 -->
            <div class="card">
                <h3><i class="fas fa-shield-alt"></i> セキュリティ設定</h3>
                
                <div class="security-section">
                    <h4><i class="fas fa-exclamation-triangle"></i> セキュリティ強化</h4>
                    <p>アカウントのセキュリティを向上させるために、二段階認証の設定を強く推奨します。</p>
                    
                    <a href="two_factor_setup.php" class="btn btn-success">
                        <i class="fas fa-shield-alt"></i> 二段階認証設定
                    </a>
                </div>
                
                <div style="margin-top: 20px;">
                    <h4>推奨事項</h4>
                    <ul style="margin: 10px 0 0 20px; color: #6c757d;">
                        <li>定期的にパスワードを変更する</li>
                        <li>他のサイトとは異なるパスワードを使用する</li>
                        <li>二段階認証を有効にする</li>
                        <li>不審なアクティビティを発見した場合は即座に報告する</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- システム統計 -->
        <?php if (isset($stats)): ?>
        <div class="card full-width" style="margin-top: 30px;">
            <h3><i class="fas fa-chart-bar"></i> システム統計</h3>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['total_products'] ?? 0); ?></div>
                    <div class="stat-label">総商品数</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['user_products'] ?? 0); ?></div>
                    <div class="stat-label">あなたが追加した商品</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number">
                        <?php 
                        if ($stats['user_products'] > 0 && $stats['total_products'] > 0) {
                            echo number_format(($stats['user_products'] / $stats['total_products']) * 100, 1) . '%';
                        } else {
                            echo '0%';
                        }
                        ?>
                    </div>
                    <div class="stat-label">貢献度</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number">
                        <?php echo $user['two_factor_enabled'] ? '高' : '中'; ?>
                    </div>
                    <div class="stat-label">セキュリティレベル</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // パスワード確認のバリデーション
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('パスワードの確認が一致しません。');
                e.preventDefault();
                return false;
            }
            
            if (newPassword.length < 6) {
                alert('パスワードは6文字以上で入力してください。');
                e.preventDefault();
                return false;
            }
        });
        
        // リアルタイムパスワード確認
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#e1e8ed';
            }
        });
        
        // ユーザー名の重複チェック（リアルタイム）
        let usernameTimeout;
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value.trim();
            const originalUsername = '<?php echo addslashes($user['username']); ?>';
            
            if (username === originalUsername || username.length < 3) {
                return;
            }
            
            clearTimeout(usernameTimeout);
            usernameTimeout = setTimeout(() => {
                // ここで実際のチェックを行う場合は、AJAXリクエストを送信
                // 今回はシンプルな実装のため省略
            }, 500);
        });
        
        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });
        
        // フォーム送信確認
        document.querySelector('form[action*="update_profile"]')?.addEventListener('submit', function(e) {
            if (!confirm('プロフィール情報を更新しますか？')) {
                e.preventDefault();
            }
        });
        
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            if (!confirm('パスワードを変更しますか？変更後は新しいパスワードでログインが必要になります。')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>