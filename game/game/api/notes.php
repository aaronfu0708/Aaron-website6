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

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => '請先登入']);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_wrong_questions':
        getWrongQuestions($db);
        break;
    case 'toggle_star':
        toggleStar($db);
        break;
    case 'add_wrong_question':
        addWrongQuestion($db);
        break;
    case 'delete_wrong_question':
        deleteWrongQuestion($db);
        break;
    case 'get_notes':
        getNotes($db);
        break;
    case 'create_note':
        createNote($db);
        break;
    case 'update_note':
        updateNote($db);
        break;
    case 'delete_note':
        deleteNote($db);
        break;
    case 'search_notes':
        searchNotes($db);
        break;
    default:
        echo json_encode(['error' => '無效的操作']);
        break;
}

function getWrongQuestions($db) {
    $user_id = $_SESSION['user_id'];
    $is_subscribed = $_SESSION['is_subscribed'] ?? false;
    
    // 一般用戶只能看到有星標的錯題
    if (!$is_subscribed) {
        $stmt = $db->prepare("
            SELECT wq.*, q.question_text, q.correct_answer, q.options, q.explanation, t.name as topic_name
            FROM wrong_questions wq
            JOIN questions q ON wq.question_id = q.id
            JOIN topics t ON q.topic_id = t.id
            WHERE wq.user_id = ? AND wq.is_starred = TRUE
            ORDER BY wq.created_at DESC
        ");
    } else {
        // 訂閱用戶可以看到所有錯題
        $stmt = $db->prepare("
            SELECT wq.*, q.question_text, q.correct_answer, q.options, q.explanation, t.name as topic_name
            FROM wrong_questions wq
            JOIN questions q ON wq.question_id = q.id
            JOIN topics t ON q.topic_id = t.id
            WHERE wq.user_id = ?
            ORDER BY wq.is_starred DESC, wq.created_at DESC
        ");
    }
    
    $stmt->execute([$user_id]);
    $wrong_questions = $stmt->fetchAll();

    echo json_encode(['success' => true, 'wrong_questions' => $wrong_questions]);
}

function toggleStar($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $wrong_question_id = $data['wrong_question_id'] ?? 0;

    if (!$wrong_question_id) {
        echo json_encode(['error' => '缺少必要參數']);
        return;
    }

    $user_id = $_SESSION['user_id'];

    // 檢查是否為使用者的錯題
    $stmt = $db->prepare("SELECT is_starred FROM wrong_questions WHERE id = ? AND user_id = ?");
    $stmt->execute([$wrong_question_id, $user_id]);
    $wrong_question = $stmt->fetch();

    if (!$wrong_question) {
        echo json_encode(['error' => '找不到此錯題']);
        return;
    }

    // 切換星標狀態
    $new_starred = !$wrong_question['is_starred'];
    $stmt = $db->prepare("UPDATE wrong_questions SET is_starred = ? WHERE id = ?");
    $stmt->execute([$new_starred, $wrong_question_id]);

    echo json_encode(['success' => true, 'is_starred' => $new_starred]);
}

function addWrongQuestion($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $question_id = $data['question_id'] ?? 0;
    $user_answer = $data['user_answer'] ?? '';

    if (!$question_id || empty($user_answer)) {
        echo json_encode(['error' => '缺少必要參數']);
        return;
    }

    $user_id = $_SESSION['user_id'];

    // 檢查是否已經存在
    $stmt = $db->prepare("SELECT id FROM wrong_questions WHERE user_id = ? AND question_id = ?");
    $stmt->execute([$user_id, $question_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['error' => '此題目已在收藏中']);
        return;
    }

    // 加入收藏（不管對錯）
    $stmt = $db->prepare("INSERT INTO wrong_questions (user_id, question_id, user_answer, is_starred) VALUES (?, ?, ?, TRUE)");
    if ($stmt->execute([$user_id, $question_id, $user_answer])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => '加入收藏失敗']);
    }
}

function deleteWrongQuestion($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $wrong_question_id = $data['wrong_question_id'] ?? 0;

    if (!$wrong_question_id) {
        echo json_encode(['error' => '缺少必要參數']);
        return;
    }

    $user_id = $_SESSION['user_id'];

    // 檢查是否為使用者的收藏題目
    $stmt = $db->prepare("SELECT id FROM wrong_questions WHERE id = ? AND user_id = ?");
    $stmt->execute([$wrong_question_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['error' => '找不到此收藏題目']);
        return;
    }

    // 刪除收藏題目
    $stmt = $db->prepare("DELETE FROM wrong_questions WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$wrong_question_id, $user_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => '刪除收藏題目失敗']);
    }
}

function getNotes($db) {
    $user_id = $_SESSION['user_id'];
    $is_subscribed = $_SESSION['is_subscribed'] ?? false;

    if (!$is_subscribed) {
        echo json_encode(['error' => '筆記功能僅限訂閱用戶使用']);
        return;
    }

    $stmt = $db->prepare("
        SELECT n.*, wq.question_id, q.question_text, t.name as topic_name
        FROM notes n
        LEFT JOIN wrong_questions wq ON n.wrong_question_id = wq.id
        LEFT JOIN questions q ON wq.question_id = q.id
        LEFT JOIN topics t ON q.topic_id = t.id
        WHERE n.user_id = ?
        ORDER BY n.updated_at DESC
    ");
    $stmt->execute([$user_id]);
    $notes = $stmt->fetchAll();

    echo json_encode(['success' => true, 'notes' => $notes]);
}

function createNote($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $user_id = $_SESSION['user_id'];
    $is_subscribed = $_SESSION['is_subscribed'] ?? false;

    if (!$is_subscribed) {
        echo json_encode(['error' => '筆記功能僅限訂閱用戶使用']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    $tags = $data['tags'] ?? [];
    $wrong_question_id = $data['wrong_question_id'] ?? null;

    if (empty($title) || empty($content)) {
        echo json_encode(['error' => '標題和內容不能為空']);
        return;
    }

    // 如果關聯錯題，檢查是否為使用者的錯題
    if ($wrong_question_id) {
        $stmt = $db->prepare("SELECT id FROM wrong_questions WHERE id = ? AND user_id = ?");
        $stmt->execute([$wrong_question_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => '無法關聯此錯題']);
            return;
        }
    }

    $stmt = $db->prepare("INSERT INTO notes (user_id, title, content, tags, wrong_question_id) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $title, $content, json_encode($tags), $wrong_question_id])) {
        $note_id = $db->lastInsertId();
        echo json_encode(['success' => true, 'note_id' => $note_id]);
    } else {
        echo json_encode(['error' => '建立筆記失敗']);
    }
}

function updateNote($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $user_id = $_SESSION['user_id'];
    $is_subscribed = $_SESSION['is_subscribed'] ?? false;

    if (!$is_subscribed) {
        echo json_encode(['error' => '筆記功能僅限訂閱用戶使用']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $note_id = $data['note_id'] ?? 0;
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    $tags = $data['tags'] ?? [];

    if (!$note_id || empty($title) || empty($content)) {
        echo json_encode(['error' => '缺少必要參數']);
        return;
    }

    // 檢查是否為使用者的筆記
    $stmt = $db->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => '找不到此筆記']);
        return;
    }

    $stmt = $db->prepare("UPDATE notes SET title = ?, content = ?, tags = ? WHERE id = ?");
    if ($stmt->execute([$title, $content, json_encode($tags), $note_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => '更新筆記失敗']);
    }
}

function deleteNote($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $user_id = $_SESSION['user_id'];
    $is_subscribed = $_SESSION['is_subscribed'] ?? false;

    if (!$is_subscribed) {
        echo json_encode(['error' => '筆記功能僅限訂閱用戶使用']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $note_id = $data['note_id'] ?? 0;

    if (!$note_id) {
        echo json_encode(['error' => '缺少必要參數']);
        return;
    }

    // 檢查是否為使用者的筆記
    $stmt = $db->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => '找不到此筆記']);
        return;
    }

    $stmt = $db->prepare("DELETE FROM notes WHERE id = ?");
    if ($stmt->execute([$note_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => '刪除筆記失敗']);
    }
}

function searchNotes($db) {
    $user_id = $_SESSION['user_id'];
    $is_subscribed = $_SESSION['is_subscribed'] ?? false;

    if (!$is_subscribed) {
        echo json_encode(['error' => '筆記功能僅限訂閱用戶使用']);
        return;
    }

    $keyword = $_GET['keyword'] ?? '';
    $tag = $_GET['tag'] ?? '';

    if (empty($keyword) && empty($tag)) {
        echo json_encode(['error' => '請提供搜尋關鍵字或標籤']);
        return;
    }

    $where_conditions = ["n.user_id = ?"];
    $params = [$user_id];

    if (!empty($keyword)) {
        $where_conditions[] = "(n.title LIKE ? OR n.content LIKE ?)";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }

    if (!empty($tag)) {
        $where_conditions[] = "JSON_CONTAINS(n.tags, ?)";
        $params[] = json_encode($tag);
    }

    $where_clause = implode(' AND ', $where_conditions);

    $stmt = $db->prepare("
        SELECT n.*, wq.question_id, q.question_text, t.name as topic_name
        FROM notes n
        LEFT JOIN wrong_questions wq ON n.wrong_question_id = wq.id
        LEFT JOIN questions q ON wq.question_id = q.id
        LEFT JOIN topics t ON q.topic_id = t.id
        WHERE $where_clause
        ORDER BY n.updated_at DESC
    ");
    $stmt->execute($params);
    $notes = $stmt->fetchAll();

    echo json_encode(['success' => true, 'notes' => $notes]);
}
?> 