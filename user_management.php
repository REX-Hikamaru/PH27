<?php
session_start();
require_once __DIR__ . '/enhanced_config.php';

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// 管理者権限チェック（簡単な実装）
$current_user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$current_user = $stmt->fetchColumn();

if ($current_user !== 'admin') {
    $_SESSION['message'] = 'この機能にアクセスする権限がありません。';
    header('Location: enhanced_dashboard.php');
    exit();
}

$success = '';
$errors = [];

// CSRF トークン生成
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF トークン検証関数
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ユーザー管理クラス
class UserManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAllUsers() {
        try {
            $stmt = $this->pdo->query("SELECT id, user_id, username, name, email, two_factor_enabled, created_at FROM users ORDER BY created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('ユーザー一覧の取得に失敗しました: ' . $e->getMessage());
        }
    }
    
    public function deleteUser($userId) {
        try {
            // 自分自身は削除できない
            global $current_user_id;
            if ($userId == $current_user_id) {
                throw new Exception('自分自身を削除することはできません。');
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            throw new Exception('ユーザーの削除に失敗しました: ' . $e->getMessage());
        }
    }
    
    public function createUser($userData) {
        try {
            // 重複チェック
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE user_id = ? OR username = ? OR email = ?");
            $stmt->execute([$userData['user_id'], $userData['username'], $userData['email']]);
            
            if ($stmt->fetch()) {
                throw new Exception('ユーザーID、ユーザー名、またはメールアドレスが既に使用されています。');
            }
            
            // パスワードハッシュ化
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO users (user_id, username, password, name, email) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $userData['user_id'],
                $userData['username'],
                $hashedPassword,
                $userData['name'],
                $userData['email']
            ]);
        } catch (PDOException $e) {
            throw new Exception('ユーザーの作成に失敗しました: ' . $e->getMessage());
        }
    }
}

$userManager = new UserManager($pdo);

// フォーム処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $errors[] = '不正なリクエストです。ページを更新して再試行してください。';
    } else {
        try {
            if ($action === 'create_user') {
                // ユーザー作成
                $userData = [
                    'user_id' => trim($_POST['user_id'] ?? ''),
                    'username' => trim($_POST['username'] ?? ''),
                    'password' => $_POST['password'] ?? '',
                    'name' => trim($_POST['name'] ?? ''),
                    'email' => trim($_POST['email'] ?? '')
                ];
                
                // バリデーション
                if (empty($userData['user_id'])) {
                    $errors[] = 'ユーザーIDを入力してください。';
                }
                if (empty($userData['username'])) {
                    $errors[] = 'ユーザー名を入力してください。';
                }
                if (empty($userData['password'])) {
                    $errors[] = 'パスワードを入力してください。';
                } elseif (strlen($userData['password']) < 6) {
                    $errors[] = 'パスワードは6文字以上で入力してください。';
                }
                if (empty($userData['name'])) {
                    $errors[] = '氏名を入力してください。';
                }
                if (empty($userData['email'])) {
                    $errors[] = 'メールアドレスを入力してください。';
                } elseif (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = '有効なメールアドレスを入力してください。';
                }
                
                if (empty($errors)) {
                    $userManager->createUser($userData);
                    $success = 'ユーザーを作成しました。';
                }
                
            } elseif ($action === 'delete_user') {
                // ユーザー削除
                $userId = (int)($_POST['user_id'] ?? 0);
                
                if ($userId <= 0) {
                    $errors[] = '無効なユーザーIDです。';
                } else {
                    $userManager->deleteUser($userId);
                    $success = 'ユーザーを削除しました。';
                }
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// ユーザー一覧取得
try {
    $users = $userManager->getAllUsers();
} catch (Exception $e) {
    $errors[] = $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー管理 - 在庫管理システム</title>
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
        
        .card {
            background: rgba(255,255,255,0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            text-decoration: none;
        }
        
        .btn-primary {
           background: linear-gradient(135deg, #fffc32ff 0%, #59cce8ff 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8em;
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
        
        .table-container {
            overflow-x: auto;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .user-table th,
        .user-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .user-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .user-table {
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-users"></i> ユーザー管理</h1>
                    <p>システムユーザーの管理</p>
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

        <!-- 新規ユーザー作成 -->
        <div class="card">
            <h2 class="section-title"><i class="fas fa-user-plus"></i> 新規ユーザー作成</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="user_id">ユーザーID:</label>
                        <input type="text" id="user_id" name="user_id" class="form-control" 
                               placeholder="例: user001" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">ユーザー名:</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="例: 田中太郎" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">氏名:</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               placeholder="例: 田中 太郎" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">メールアドレス:</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="例: tanaka@example.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">パスワード:</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="6文字以上" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> ユーザーを作成
                </button>
            </form>
        </div>

        <!-- ユーザー一覧 -->
        <div class="card">
            <h2 class="section-title"><i class="fas fa-list"></i> ユーザー一覧</h2>
            
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ユーザーID</th>
                            <th>ユーザー名</th>
                            <th>氏名</th>
                            <th>メールアドレス</th>
                            <th>二段階認証</th>
                            <th>登録日</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['user_id']); ?></strong>
                                        <?php if ($user['id'] == $current_user_id): ?>
                                            <span style="color: #667eea; font-size: 0.8em;">(現在のユーザー)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['two_factor_enabled'] ? 'status-enabled' : 'status-disabled'; ?>">
                                            <?php echo $user['two_factor_enabled'] ? '有効' : '無効'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y年m月d日', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['id'] != $current_user_id): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('ユーザー「<?php echo htmlspecialchars($user['username']); ?>」を削除しますか？この操作は取り消せません。');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <button type="submit" class="btn btn-danger btn-small">
                                                    <i class="fas fa-trash"></i> 削除
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 0.8em;">削除不可</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #6c757d;">
                                    ユーザーが見つかりません。
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- システム情報 -->
        <div class="card">
            <h2 class="section-title"><i class="fas fa-info-circle"></i> システム情報</h2>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <strong>総ユーザー数:</strong>
                        <span style="font-size: 1.2em; color: #667eea;"><?php echo count($users); ?>人</span>
                    </div>
                    <div>
                        <strong>二段階認証有効:</strong>
                        <span style="font-size: 1.2em; color: #2ecc71;">
                            <?php echo count(array_filter($users, fn($u) => $u['two_factor_enabled'])); ?>人
                        </span>
                    </div>
                    <div>
                        <strong>現在のユーザー:</strong>
                        <span style="font-size: 1.2em; color: #e67e22;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // フォーム送信時の確認
        document.addEventListener('DOMContentLoaded', function() {
            const createForm = document.querySelector('form[action="create_user"]');
            if (createForm) {
                createForm.addEventListener('submit', function(e) {
                    const userId = this.querySelector('#user_id').value;
                    const username = this.querySelector('#username').value;
                    
                    if (!confirm(`新しいユーザー「${username} (${userId})」を作成しますか？`)) {
                        e.preventDefault();
                    }
                });
            }
            
            // パスワード強度チェック（簡易版）
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    if (password.length >= 6) strength++;
                    if (password.match(/[a-z]/)) strength++;
                    if (password.match(/[A-Z]/)) strength++;
                    if (password.match(/[0-9]/)) strength++;
                    if (password.match(/[^a-zA-Z0-9]/)) strength++;
                    
                    // パスワード強度の表示（簡易版）
                    this.style.borderColor = strength >= 3 ? '#2ecc71' : '#e74c3c';
                });
            }
        });
        
        // テーブルの行をクリック時のハイライト
        const tableRows = document.querySelectorAll('.user-table tbody tr');
        tableRows.forEach(row => {
            row.addEventListener('mouseover', function() {
                this.style.background = '#f1f3f4';
            });
            
            row.addEventListener('mouseout', function() {
                this.style.background = '';
            });
        });
    </script>
</body>
</html>