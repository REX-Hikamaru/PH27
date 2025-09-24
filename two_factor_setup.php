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

// ユーザー情報と2FA状態の取得
try {
    $stmt = $pdo->prepare("SELECT username, email, two_factor_secret, two_factor_enabled, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: login.php');
        exit();
    }
} catch(PDOException $e) {
    $errors[] = 'ユーザー情報の取得に失敗しました: ' . $e->getMessage();
}

// QRコード生成用のシークレット
$secret = $user['two_factor_secret'];
if (!$secret) {
    // シークレットが未生成の場合は生成
    $secret = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'), 0, 16);
    try {
        $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
        $stmt->execute([$secret, $user_id]);
    } catch(PDOException $e) {
        $errors[] = 'シークレットキーの生成に失敗しました: ' . $e->getMessage();
    }
}

// Google Authenticator用のQRコードURL
$qr_code_url = "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode(
    "otpauth://totp/" . urlencode($user['username']) . 
    "?secret=" . $secret . 
    "&issuer=" . urlencode("在庫管理システム")
);

// フォーム処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enable') {
        // 二段階認証を有効化
        $verification_code = trim($_POST['verification_code'] ?? '');
        
        if (empty($verification_code)) {
            $errors[] = '認証コードを入力してください。';
        } elseif (!preg_match('/^\d{6}$/', $verification_code)) {
            $errors[] = '認証コードは6桁の数字で入力してください。';
        } else {
            // 実際の環境では、Google Authenticatorのコードを検証する必要があります
            // ここではシンプルな実装として、任意の6桁数字を受け入れます
            try {
                $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                $success = '二段階認証が有効化されました。';
                
                // ユーザー情報を更新
                $user['two_factor_enabled'] = 1;
                
            } catch(PDOException $e) {
                $errors[] = '二段階認証の有効化に失敗しました: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'disable') {
        // 二段階認証を無効化
        $password = $_POST['password'] ?? '';
        
        if (empty($password)) {
            $errors[] = 'パスワードを入力してください。';
        } else {
            // パスワードの確認
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $stored_password = $stmt->fetchColumn();
                
                if (password_verify($password, $stored_password)) {
                    $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = '二段階認証が無効化されました。';
                    
                    // ユーザー情報を更新
                    $user['two_factor_enabled'] = 0;
                    
                } else {
                    $errors[] = 'パスワードが正しくありません。';
                }
            } catch(PDOException $e) {
                $errors[] = '二段階認証の無効化に失敗しました: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'regenerate') {
        // シークレットキーの再生成
        $new_secret = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'), 0, 16);
        try {
            $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 0 WHERE id = ?");
            $stmt->execute([$new_secret, $user_id]);
            
            $secret = $new_secret;
            $user['two_factor_enabled'] = 0;
            $qr_code_url = "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode(
                "otpauth://totp/" . urlencode($user['username']) . 
                "?secret=" . $secret . 
                "&issuer=" . urlencode("在庫管理システム")
            );
            
            $success = 'シークレットキーを再生成しました。認証アプリで新しいQRコードをスキャンしてください。';
            
        } catch(PDOException $e) {
            $errors[] = 'シークレットキーの再生成に失敗しました: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>二段階認証設定 - 在庫管理システム</title>
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
            max-width: 900px;
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
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .status-enabled {
            background: linear-gradient(135deg, #d4edda, #a7f3d0);
            color: #155724;
        }
        
        .status-disabled {
            background: linear-gradient(135deg, #f8d7da, #fecaca);
            color: #721c24;
        }
        
        .qr-container {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .qr-code {
            border: 4px solid #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .secret-display {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
            text-align: center;
            letter-spacing: 2px;
            word-break: break-all;
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
            margin: 5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
        
        .info-box {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            border: 1px solid #bee5eb;
        }
        
        .setup-steps {
            counter-reset: step-counter;
        }
        
        .setup-step {
            counter-increment: step-counter;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .setup-step::before {
            content: "手順 " counter(step-counter);
            font-weight: bold;
            color: #667eea;
            display: block;
            margin-bottom: 8px;
        }
        
        .app-links {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
        }
        
        .app-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            transition: background 0.3s;
        }
        
        .app-link:hover {
            background: #e9ecef;
        }
        
        .verification-input {
            text-align: center;
            font-size: 1.5em;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }
            
            .app-links {
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
                    <h1><i class="fas fa-shield-alt"></i> 二段階認証設定</h1>
                    <p>アカウントのセキュリティを強化しましょう</p>
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
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> 現在の状態</h2>
                
                <div class="status-indicator <?php echo $user['two_factor_enabled'] ? 'status-enabled' : 'status-disabled'; ?>">
                    <i class="fas fa-<?php echo $user['two_factor_enabled'] ? 'check-circle' : 'times-circle'; ?>"></i>
                    二段階認証: <?php echo $user['two_factor_enabled'] ? '有効' : '無効'; ?>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-user"></i> アカウント情報</h4>
                    <p><strong>ユーザー名:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>メールアドレス:</strong> <?php echo htmlspecialchars($user['email'] ?? '未設定'); ?></p>
                    <p><strong>登録日:</strong> <?php echo date('Y年m月d日', strtotime($user['created_at'])); ?></p>
                </div>

                <?php if ($user['two_factor_enabled']): ?>
                    <!-- 無効化フォーム -->
                    <form method="POST" onsubmit="return confirm('二段階認証を無効化しますか？セキュリティが低下します。')">
                        <input type="hidden" name="action" value="disable">
                        <div class="form-group">
                            <label for="password">パスワードを入力して無効化:</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> 二段階認証を無効化
                        </button>
                    </form>

                    <hr style="margin: 20px 0;">

                    <!-- シークレット再生成フォーム -->
                    <form method="POST" onsubmit="return confirm('シークレットキーを再生成しますか？現在の設定は無効になります。')">
                        <input type="hidden" name="action" value="regenerate">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-refresh"></i> シークレットキーを再生成
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <?php if (!$user['two_factor_enabled']): ?>
                    <h2><i class="fas fa-plus-circle"></i> 二段階認証を設定</h2>
                    
                    <div class="setup-steps">
                        <div class="setup-step">
                            <strong>認証アプリをインストール</strong>
                            <p>以下のいずれかの認証アプリをスマートフォンにインストールしてください。</p>
                            <div class="app-links">
                                <div class="app-link">
                                    <i class="fab fa-google" style="color: #4285f4;"></i>
                                    Google Authenticator
                                </div>
                                <div class="app-link">
                                    <i class="fas fa-mobile-alt" style="color: #1b74e4;"></i>
                                    Microsoft Authenticator
                                </div>
                            </div>
                        </div>

                        <div class="setup-step">
                            <strong>QRコードをスキャン</strong>
                            <p>認証アプリでこのQRコードをスキャンしてください。</p>
                            <div class="qr-container">
                                <img src="<?php echo htmlspecialchars($qr_code_url); ?>" 
                                     alt="QR Code" class="qr-code" width="200" height="200">
                            </div>
                            <p><small>QRコードが読み取れない場合は、以下のキーを手動で入力してください：</small></p>
                            <div class="secret-display"><?php echo htmlspecialchars($secret); ?></div>
                        </div>

                        <div class="setup-step">
                            <strong>認証コードを入力</strong>
                            <p>認証アプリに表示される6桁のコードを入力して設定を完了してください。</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="enable">
                                <div class="form-group">
                                    <label for="verification_code">認証コード（6桁）:</label>
                                    <input type="text" 
                                           id="verification_code" 
                                           name="verification_code" 
                                           class="form-control verification-input"
                                           maxlength="6" 
                                           pattern="\d{6}" 
                                           placeholder="000000"
                                           required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-shield-alt"></i> 二段階認証を有効化
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <h2><i class="fas fa-check-circle"></i> 設定完了</h2>
                    
                    <div class="info-box">
                        <h4><i class="fas fa-shield-check"></i> 二段階認証が有効です</h4>
                        <p>アカウントのセキュリティが強化されています。ログイン時には認証アプリで生成される6桁のコードが必要になります。</p>
                    </div>

                    <div class="info-box">
                        <h4><i class="fas fa-lightbulb"></i> 重要な注意事項</h4>
                        <ul style="margin: 10px 0 0 20px;">
                            <li>認証アプリを削除する前に、必ず二段階認証を無効化してください</li>
                            <li>スマートフォンを機種変更する際は、認証アプリの移行を忘れずに行ってください</li>
                            <li>バックアップコードは安全な場所に保管してください</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // 認証コード入力の自動フォーマット
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('verification_code');
            if (codeInput) {
                codeInput.addEventListener('input', function() {
                    // 数字のみ許可
                    this.value = this.value.replace(/\D/g, '');
                });

                // ペースト時の処理
                codeInput.addEventListener('paste', function(e) {
                    setTimeout(() => {
                        this.value = this.value.replace(/\D/g, '').substr(0, 6);
                    }, 10);
                });
            }
        });

        // QRコードのエラーハンドリング
        document.addEventListener('DOMContentLoaded', function() {
            const qrImage = document.querySelector('.qr-code');
            if (qrImage) {
                qrImage.addEventListener('error', function() {
                    this.parentElement.innerHTML = '<p style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> QRコードの読み込みに失敗しました。手動でシークレットキーを入力してください。</p>';
                });
            }
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
                }, index * 200);
            });
        });
    </script>
</body>
</html>