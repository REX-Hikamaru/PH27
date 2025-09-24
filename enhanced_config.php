<?php
// ============================================
// enhanced_config.php - 設定ファイル（完全版・修正版）
// ============================================

// セッションが未開始の場合のみ開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// デバッグモードの定義
define('DEBUG_MODE', true);

// データベース設定
$host = 'localhost';
$dbname = 'beginner_site';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// CSRFトークン生成関数
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRFトークン検証関数
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 画像アップロード処理関数
function handleImageUpload($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return null;
    }
    
    // アップロードディレクトリの作成
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // ファイル拡張子のチェック
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        throw new Exception('許可されていないファイル形式です。');
    }
    
    // ファイルサイズチェック (5MB以下)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('ファイルサイズが大きすぎます。5MB以下にしてください。');
    }
    
    // ユニークなファイル名を生成
    $fileName = uniqid() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    // ファイルを移動
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return $filePath;
    }
    
    throw new Exception('ファイルのアップロードに失敗しました。');
}

// 認証ログ記録関数
function logAuthAttempt($userId, $action, $success = true, $additionalInfo = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO auth_logs (user_id, action, ip_address, user_agent, success, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $success ? 1 : 0
        ]);
    } catch(PDOException $e) {
        if (DEBUG_MODE) {
            error_log("認証ログ記録エラー: " . $e->getMessage());
        }
    }
}

// 認証アクション記録関数（logAuthAttemptのエイリアス）
function logAuthAction($userId, $action, $success = true, $additionalInfo = '') {
    logAuthAttempt($userId, $action, $success, $additionalInfo);
}

// 認証確認関数
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// データベース操作クラス
class EnhancedDatabase {
    private static $pdo;
    
    public static function init($pdo) {
        self::$pdo = $pdo;
    }
    
    // PDO接続取得メソッド
    public static function getConnection() {
        return self::$pdo;
    }
    
    // ユーザー認証
    public static function authenticateUser($userId, $password) {
        try {
            $stmt = self::$pdo->prepare("SELECT id, user_id, username, password, name, email FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                return $user;
            }
            return false;
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("ユーザー認証エラー: " . $e->getMessage());
            }
            return false;
        }
    }
    
    // ユーザー登録
    public static function registerUser($userId, $username, $password, $name, $email) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = self::$pdo->prepare("INSERT INTO users (user_id, username, password, name, email, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            return $stmt->execute([$userId, $username, $hashedPassword, $name, $email]);
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("ユーザー登録エラー: " . $e->getMessage());
            }
            return false;
        }
    }
    
    // ユーザーID重複チェック
    public static function isUserIdExists($userId) {
        try {
            $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("ユーザーID重複チェックエラー: " . $e->getMessage());
            }
            return true; // エラーの場合は重複ありとして扱う
        }
    }
    
    // メールアドレス重複チェック
    public static function isEmailExists($email) {
        try {
            $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("メールアドレス重複チェックエラー: " . $e->getMessage());
            }
            return true; // エラーの場合は重複ありとして扱う
        }
    }
    
    // 全商品取得
    public static function getAllProducts() {
        try {
            $stmt = self::$pdo->query("SELECT * FROM products ORDER BY created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("商品一覧取得エラー: " . $e->getMessage());
            }
            return [];
        }
    }
    
    // 商品取得（ページング対応）
    public static function getProducts($limit = 10, $offset = 0) {
        try {
            $stmt = self::$pdo->prepare("SELECT * FROM products ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("商品一覧取得エラー: " . $e->getMessage());
            }
            return [];
        }
    }
    
    // 商品総数取得
    public static function getProductCount() {
        try {
            $stmt = self::$pdo->query("SELECT COUNT(*) FROM products");
            return $stmt->fetchColumn();
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("商品総数取得エラー: " . $e->getMessage());
            }
            return 0;
        }
    }
    
    // 商品追加（配列対応）
    public static function addProduct($productData) {
        try {
            if (is_array($productData)) {
                $stmt = self::$pdo->prepare("INSERT INTO products (name, category, price, stock_quantity, description, image, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                return $stmt->execute([
                    $productData['name'],
                    $productData['category'],
                    $productData['price'],
                    $productData['stock'],
                    $productData['description'] ?? '',
                    $productData['image'] ?? null,
                    $_SESSION['user_db_id'] ?? null
                ]);
            } else {
                // 従来の引数形式もサポート
                $stmt = self::$pdo->prepare("INSERT INTO products (name, category, price, stock_quantity, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                return $stmt->execute(func_get_args());
            }
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("商品追加エラー: " . $e->getMessage());
            }
            return false;
        }
    }
    
    // 商品取得（ID指定）
    public static function getProduct($id) {
        try {
            $stmt = self::$pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("商品取得エラー: " . $e->getMessage());
            }
            return false;
        }
    }
    
    // 商品更新（ID指定と配列対応）
    public static function updateProduct($id, $productData = null) {
        try {
            if (is_array($productData)) {
                $stmt = self::$pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, stock_quantity = ?, description = ?, image = ?, updated_at = NOW() WHERE id = ?");
                return $stmt->execute([
                    $productData['name'],
                    $productData['category'],
                    $productData['price'],
                    $productData['stock'],
                    $productData['description'] ?? '',
                    $productData['image'] ?? null,
                    $id
                ]);
            } else {
                // 従来の引数形式もサポート
                $args = func_get_args();
                array_shift($args); // $idを除去
                $args[] = $id; // 最後にIDを追加
                $stmt = self::$pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, stock_quantity = ?, description = ?, updated_at = NOW() WHERE id = ?");
                return $stmt->execute($args);
            }
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("商品更新エラー: " . $e->getMessage());
            }
            return false;
        }
    }
    
    // 商品削除
    public static function deleteProduct($id) {
        try {
            $stmt = self::$pdo->prepare("DELETE FROM products WHERE id = ?");
            return $stmt->execute([$id]);
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                error_log("商品削除エラー: " . $e->getMessage());
            }
            return false;
        }
    }
}

// EnhancedDatabaseクラスを初期化
EnhancedDatabase::init($pdo);

// セキュリティ関数
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // パスワードは最低6文字以上
    return strlen($password) >= 6;
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

// エラーハンドリング
function handleError($error, $redirect = null) {
    $_SESSION['error'] = $error;
    if ($redirect) {
        header("Location: $redirect");
        exit();
    }
}

function handleSuccess($message, $redirect = null) {
    $_SESSION['success'] = $message;
    if ($redirect) {
        header("Location: $redirect");
        exit();
    }
}

// メッセージ表示関数
function displayMessages() {
    $output = '';
    
    if (isset($_SESSION['error'])) {
        $output .= '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['success'])) {
        $output .= '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['message'])) {
        $output .= '<div class="alert alert-success"><i class="fas fa-info-circle"></i> ' . htmlspecialchars($_SESSION['message']) . '</div>';
        unset($_SESSION['message']);
    }
    
    return $output;
}

// 設定完了フラグ
define('CONFIG_LOADED', true);
?>