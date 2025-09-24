<?php
require_once __DIR__ . '/enhanced_config.php';

$errors = [];
$success = '';
$userId = '';
$username = '';
$name = '';
$email = '';

// 既にログイン済みの場合はダッシュボードにリダイレクト
if (isset($_SESSION['user_id'])) {
    header('Location: enhanced_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = sanitizeInput($_POST['user_id'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    
    // バリデーション
    if (empty($userId)) {
        $errors[] = 'ユーザーIDを入力してください。';
    } elseif (strlen($userId) < 3 || strlen($userId) > 20) {
        $errors[] = 'ユーザーIDは3文字以上20文字以下で入力してください。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $userId)) {
        $errors[] = 'ユーザーIDは英数字とアンダースコアのみ使用できます。';
    }
    
    if (empty($username)) {
        $errors[] = 'ユーザー名を入力してください。';
    } elseif (strlen($username) > 100) {
        $errors[] = 'ユーザー名は100文字以下で入力してください。';
    }
    
    if (empty($password)) {
        $errors[] = 'パスワードを入力してください。';
    } elseif (!validatePassword($password)) {
        $errors[] = 'パスワードは6文字以上で入力してください。';
    }
    
    if (empty($confirmPassword)) {
        $errors[] = 'パスワード（確認）を入力してください。';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'パスワードが一致しません。';
    }
    
    if (empty($name)) {
        $errors[] = '氏名を入力してください。';
    } elseif (strlen($name) > 100) {
        $errors[] = '氏名は100文字以下で入力してください。';
    }
    
    if (empty($email)) {
        $errors[] = 'メールアドレスを入力してください。';
    } elseif (!validateEmail($email)) {
        $errors[] = '有効なメールアドレスを入力してください。';
    }
    
    // 重複チェック
    if (empty($errors)) {
        if (EnhancedDatabase::isUserIdExists($userId)) {
            $errors[] = 'このユーザーIDは既に使用されています。';
        }
        
        if (EnhancedDatabase::isEmailExists($email)) {
            $errors[] = 'このメールアドレスは既に使用されています。';
        }
    }
    
    // 登録処理
    if (empty($errors)) {
        if (EnhancedDatabase::registerUser($userId, $username, $password, $name, $email)) {
            $success = 'アカウントが正常に作成されました。ログインページに移動します。';
            
            // 登録ログ記録
            logAuthAction($userId, 'register', true);
            
            // 3秒後にリダイレクト
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 3000);
            </script>";
        } else {
            $errors[] = 'アカウントの作成に失敗しました。もう一度お試しください。';
            
            // 登録失敗ログ記録
            logAuthAction($userId, 'register', false);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 - 在庫管理システム</title>
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
            padding: 20px 0;
        }
        
        .register-container {
            max-width: 500px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18);
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
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2em;
        }
        
        .register-header p {
            color: #7f8c8d;
            font-size: 1em;
        }
        
        .register-icon {
                background: linear-gradient(135deg, #fffc32ff 0%, #59cce8ff 100%);
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
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
            background: rgba(255,255,255,0.9);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: rgba(255,255,255,1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .register-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px 0;
        }
        
        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(46, 204, 113, 0.3);
        }
        
        .register-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
            align-items: flex-start;
            gap: 10px;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #fecaca);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #a7f3d0);
            color: #155724;
            border: 1px solid #a7f3d0;
        }
        
        .error-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .error-list li {
            margin-bottom: 5px;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.85em;
        }
        
        .strength-weak {
            color: #e74c3c;
        }
        
        .strength-medium {
            color: #f39c12;
        }
        
        .strength-strong {
            color: #27ae60;
        }
        
        .form-help {
            font-size: 0.85em;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        @media (max-width: 600px) {
            .register-container {
                margin: 0 10px;
                padding: 30px 25px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="register-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1>新規登録</h1>
            <p>アカウントを作成して在庫管理システムを利用開始</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <?php echo htmlspecialchars($success); ?>
                    <div style="margin-top: 10px;">
                        <i class="fas fa-spinner fa-spin"></i> 3秒後にログインページに移動します...
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="user_id">ユーザーID <span class="required">*</span></label>
                <input type="text" 
                       id="user_id" 
                       name="user_id" 
                       value="<?php echo htmlspecialchars($userId); ?>" 
                       required 
                       pattern="[a-zA-Z0-9_]{3,20}"
                       placeholder="3-20文字の英数字とアンダースコア">
                <div class="form-help">英数字とアンダースコア（_）のみ、3-20文字</div>
            </div>

            <div class="form-group">
                <label for="username">ユーザー名 <span class="required">*</span></label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       value="<?php echo htmlspecialchars($username); ?>" 
                       required 
                       maxlength="100"
                       placeholder="表示用のユーザー名">
            </div>

            <div class="form-group">
                <label for="name">氏名 <span class="required">*</span></label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       value="<?php echo htmlspecialchars($name); ?>" 
                       required 
                       maxlength="100"
                       placeholder="フルネーム">
            </div>

            <div class="form-group">
                <label for="email">メールアドレス <span class="required">*</span></label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="<?php echo htmlspecialchars($email); ?>" 
                       required 
                       placeholder="example@domain.com">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">パスワード <span class="required">*</span></label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           minlength="6"
                           placeholder="6文字以上">
                    <div id="passwordStrength" class="password-strength"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">パスワード確認 <span class="required">*</span></label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           required 
                           minlength="6"
                           placeholder="パスワードを再入力">
                    <div id="passwordMatch" class="password-strength"></div>
                </div>
            </div>

            <button type="submit" class="register-btn" id="submitBtn">
                <i class="fas fa-user-plus"></i> アカウントを作成
            </button>
        </form>
        <?php endif; ?>

        <div class="links">
            <a href="login.php">
                <i class="fas fa-sign-in-alt"></i> 既にアカウントをお持ちの方はログイン
            </a>
        </div>
    </div>

    <script>
        // パスワード強度チェック
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 6) strength++;
            else feedback.push('6文字以上');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('小文字');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('大文字');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('数字');
            
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            else feedback.push('特殊文字');
            
            if (strength < 2) {
                strengthDiv.className = 'password-strength strength-weak';
                strengthDiv.innerHTML = '弱い - 推奨: ' + feedback.slice(0, 2).join(', ');
            } else if (strength < 4) {
                strengthDiv.className = 'password-strength strength-medium';
                strengthDiv.innerHTML = '普通';
            } else {
                strengthDiv.className = 'password-strength strength-strong';
                strengthDiv.innerHTML = '強い';
            }
        });

        // パスワード一致チェック
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.className = 'password-strength strength-strong';
                matchDiv.innerHTML = 'パスワードが一致しています';
            } else {
                matchDiv.className = 'password-strength strength-weak';
                matchDiv.innerHTML = 'パスワードが一致しません';
            }
        });

        // フォーム送信時のバリデーション
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('パスワードが一致しません。');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('パスワードは6文字以上で入力してください。');
                return false;
            }
            
            // 送信ボタンを無効化（二重送信防止）
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 作成中...';
        });

        // ユーザーID入力時の文字制限
        document.getElementById('user_id').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });

        // リアルタイムバリデーション
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = '#e74c3c';
                    } else {
                        this.style.borderColor = '#27ae60';
                    }
                });
                
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#667eea';
                });
            });
        });
    </script>
</body>
</html>