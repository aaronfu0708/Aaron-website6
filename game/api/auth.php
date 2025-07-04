<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/config.php';

session_start();

$database = new Database();
$db = $database->getConnection();

// 處理OPTIONS請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        handleRegister($db);
        break;
    case 'login':
        handleLogin($db);
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check_session':
        checkSession($db);
        break;
    default:
        echo json_encode(['error' => '無效的操作']);
        break;
}

function handleRegister($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $email = $data['email'] ?? '';

    if (empty($username) || empty($password) || empty($email)) {
        echo json_encode(['error' => '所有欄位都必須填寫']);
        return;
    }

    // 檢查使用者名稱是否已存在
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => '使用者名稱已存在']);
        return;
    }

    // 檢查email是否已存在
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'Email已存在']);
        return;
    }

    // 建立新使用者
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
    
    if ($stmt->execute([$username, $hashed_password, $email])) {
        $user_id = $db->lastInsertId();
        

        
        echo json_encode(['success' => true, 'message' => '註冊成功']);
    } else {
        echo json_encode(['error' => '註冊失敗']);
    }
}

function handleLogin($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['error' => '使用者名稱和密碼都必須填寫']);
        return;
    }

    $stmt = $db->prepare("SELECT id, username, password, email, is_subscribed, subscription_expires_at, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_subscribed'] = $user['is_subscribed'];
        $_SESSION['role'] = $user['role'];
        
        // 檢查訂閱是否過期
        if ($user['is_subscribed'] && $user['subscription_expires_at'] && strtotime($user['subscription_expires_at']) < time()) {
            $stmt = $db->prepare("UPDATE users SET is_subscribed = FALSE WHERE id = ?");
            $stmt->execute([$user['id']]);
            $_SESSION['is_subscribed'] = false;
        }
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'is_subscribed' => $_SESSION['is_subscribed'],
                'role' => $user['role']
            ]
        ]);
    } else {
        echo json_encode(['error' => '使用者名稱或密碼錯誤']);
    }
}

function handleLogout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => '登出成功']);
}

function checkSession($db) {
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT id, username, email, is_subscribed, subscription_expires_at, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // 檢查訂閱是否過期
            if ($user['is_subscribed'] && $user['subscription_expires_at'] && strtotime($user['subscription_expires_at']) < time()) {
                $stmt = $db->prepare("UPDATE users SET is_subscribed = FALSE WHERE id = ?");
                $stmt->execute([$user['id']]);
                $user['is_subscribed'] = false;
                $_SESSION['is_subscribed'] = false;
            }
            
            echo json_encode([
                'logged_in' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'is_subscribed' => $user['is_subscribed'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            session_destroy();
            echo json_encode(['logged_in' => false]);
        }
    } else {
        echo json_encode(['logged_in' => false]);
    }
}
?> 