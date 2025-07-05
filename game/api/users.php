<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
session_start();

// 僅允許管理員（帳號為 Aaron）操作
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || $_SESSION['username'] !== 'Aaron') {
    echo json_encode(['error' => '僅限管理員操作']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listUsers($db);
        break;
    case 'upgrade':
        upgradeUser($db);
        break;
    case 'downgrade':
        downgradeUser($db);
        break;
    case 'delete':
        deleteUser($db);
        break;
    default:
        echo json_encode(['error' => '無效的操作']);
        break;
}

function listUsers($db) {
    $stmt = $db->prepare('SELECT id, username, email, is_subscribed FROM users ORDER BY id ASC');
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'users' => $users]);
}

function upgradeUser($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? 0;
    if (!$user_id) {
        echo json_encode(['error' => '缺少 user_id']);
        return;
    }
    $stmt = $db->prepare('UPDATE users SET is_subscribed = 1 WHERE id = ?');
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
}

function downgradeUser($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? 0;
    if (!$user_id) {
        echo json_encode(['error' => '缺少 user_id']);
        return;
    }
    $stmt = $db->prepare('UPDATE users SET is_subscribed = 0 WHERE id = ?');
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
}

function deleteUser($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? 0;
    if (!$user_id) {
        echo json_encode(['error' => '缺少 user_id']);
        return;
    }
    // 取得目前登入者的 id
    $current_user_id = $_SESSION['user_id'];
    if ($user_id == $current_user_id) {
        echo json_encode(['error' => '不能刪除自己']);
        return;
    }
    $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
} 