<?php
require_once __DIR__ . '/enhanced_config.php';

$errors = [];
$userId = '';

// 既にログイン済みの場合はダッシュボードにリダイレクト
if (isset($_SESSION['user_id'])) {
    header('Location: enhanced_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = sanitizeInput($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // バリデーション
    if (empty($userId)) {
        $errors[] = 'ユーザーIDを入力してください。';
    }
    
    if (empty($password)) {
        $errors[] = 'パスワードを入力してください。';
    }
    
    // 認証処理
    if (empty($errors)) {
        $user = EnhancedDatabase::authenticateUser($userId, $password);
        
        if ($user) {
            // ログイン成功
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_account'] = $user['user_id'];
            
            // 認証ログ記録
            logAuthAttempt($userId, 'login', true);
            
            header('Location: enhanced_dashboard.php');
            exit();
        } else {
            // ログイン失敗
            $errors[] = 'ユーザーIDまたはパスワードが間違っています。';
            
            // 認証ログ記録
            logAuthAttempt($userId, 'login', false);
        }
    } else {
        // バリデーションエラー時もログ記録
        logAuthAttempt($userId, 'login_attempt', false);
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - 在庫管理システム</title>
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
        
        .login-container {
            background: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18);
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2em;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 1em;
        }
        
        .login-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            font-size: 2em;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s;
            background: rgba(255,255,255,0.9);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: rgba(255,255,255,1);
        }
        
        .form-group i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bdc3c7;
            pointer-events: none;
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
              background: linear-gradient(135deg, #000000ff 0%, #000000ff 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #fecaca);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .demo-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
        
        .demo-info h4 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .demo-credentials {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        
        .demo-user {
            background: rgba(255,255,255,0.7);
            padding: 8px;
            border-radius: 5px;
            font-size: 0.9em;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #bdc3c7;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 25px;
            }
            
            .demo-credentials {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-box"></i>
            </div>
            <h1>ログイン</h1>
            <p>在庫管理システムへようこそ</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="demo-info">
            <h4><i class="fas fa-info-circle"></i> デモアカウント</h4>
            <p>以下のアカウントでログインできます：</p>
            <div class="demo-credentials">
                <div class="demo-user">
                    <strong>管理者</strong><br>
                    ID: admin<br>
                    PW: password
                </div>
                <div class="demo-user">
                    <strong>ユーザー</strong><br>
                    ID: user<br>
                    PW: password
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="user_id">ユーザーID</label>
                <input type="text" 
                       id="user_id" 
                       name="user_id" 
                       value="<?php echo htmlspecialchars($userId); ?>" 
                       required 
                       autofocus
                       placeholder="ユーザーIDを入力">
                <i class="fas fa-user"></i>
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       placeholder="パスワードを入力">
                <button type="button" class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                </button>
            </div>

            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> ログイン
            </button>
        </form>

        <div class="links">
            <a href="register.php">
                <i class="fas fa-user-plus"></i> 新規アカウント登録
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // フォーム送信時の簡単なバリデーション
        document.querySelector('form').addEventListener('submit', function(e) {
            const userId = document.getElementById('user_id').value.trim();
            const password = document.getElementById('password').value;
            
            if (!userId || !password) {
                e.preventDefault();
                alert('ユーザーIDとパスワードを入力してください。');
                return false;
            }
        });

        // エンターキーでのフォーム送信
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>