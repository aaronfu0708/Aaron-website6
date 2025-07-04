<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    case 'get_leaderboard':
        getLeaderboard($db);
        break;
    case 'get_user_rank':
        getUserRank($db);
        break;
    default:
        echo json_encode(['error' => '無效的操作']);
        break;
}

function getLeaderboard($db) {
    $topic_id = $_GET['topic_id'] ?? null;
    $limit = min(50, intval($_GET['limit'] ?? 20)); // 最多顯示50名
    
    // 防作弊：排除異常帳號
    $suspicious_users = getSuspiciousUsers($db);
    $exclude_users = implode(',', $suspicious_users);
    $exclude_clause = $exclude_users ? "AND u.id NOT IN ($exclude_users)" : "";
    
    if ($topic_id) {
        // 特定主題排行榜
        $sql = "
            SELECT 
                u.username,
                f.familiarity_percentage,
                f.total_questions,
                f.correct_answers,
                ROUND(f.correct_answers / f.total_questions * 100, 2) as accuracy_rate,
                AVG(ar.answer_time) as avg_answer_time
            FROM familiarity f
            JOIN users u ON f.user_id = u.id
            LEFT JOIN answer_records ar ON u.id = ar.user_id
            WHERE f.topic_id = ? $exclude_clause
            GROUP BY u.id, f.familiarity_percentage, f.total_questions, f.correct_answers
            HAVING f.total_questions >= 1 AND accuracy_rate > 0
            ORDER BY f.familiarity_percentage DESC, accuracy_rate DESC, avg_answer_time ASC
            LIMIT $limit
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$topic_id]);
    } else {
        // 總排行榜（平均熟悉度）
        $sql = "
            SELECT 
                u.username,
                AVG(f.familiarity_percentage) as avg_familiarity,
                SUM(f.total_questions) as total_questions,
                SUM(f.correct_answers) as total_correct,
                ROUND(SUM(f.correct_answers) / SUM(f.total_questions) * 100, 2) as overall_accuracy,
                AVG(ar.answer_time) as avg_answer_time
            FROM users u
            LEFT JOIN familiarity f ON u.id = f.user_id
            LEFT JOIN answer_records ar ON u.id = ar.user_id
            WHERE u.id > 0 $exclude_clause
            GROUP BY u.id, u.username
            HAVING total_questions >= 1 AND overall_accuracy > 0
            ORDER BY avg_familiarity DESC, overall_accuracy DESC, avg_answer_time ASC
            LIMIT $limit
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }
    
    $leaderboard = $stmt->fetchAll();
    
    // 添加排名
    foreach ($leaderboard as $index => &$user) {
        $user['rank'] = $index + 1;
    }

    echo json_encode(['success' => true, 'leaderboard' => $leaderboard]);
}

function getUserRank($db) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => '請先登入']);
        return;
    }

    $user_id = $_SESSION['user_id'];
    $topic_id = $_GET['topic_id'] ?? null;
    
    // 防作弊：檢查是否為可疑帳號
    if (isSuspiciousUser($db, $user_id)) {
        echo json_encode(['error' => '您的帳號已被標記為異常']);
        return;
    }
    
    if ($topic_id) {
        // 特定主題排名
        $stmt = $db->prepare("
            SELECT 
                f.familiarity_percentage,
                f.total_questions,
                f.correct_answers,
                ROUND(f.correct_answers / f.total_questions * 100, 2) as accuracy_rate,
                (
                    SELECT COUNT(*) + 1
                    FROM familiarity f2
                    WHERE f2.topic_id = f.topic_id 
                    AND f2.familiarity_percentage > f.familiarity_percentage
                    AND f2.user_id NOT IN (" . implode(',', getSuspiciousUsers($db)) . ")
                ) as rank
            FROM familiarity f
            WHERE f.user_id = ? AND f.topic_id = ?
        ");
        $stmt->execute([$user_id, $topic_id]);
    } else {
        // 總排名
        $stmt = $db->prepare("
            SELECT 
                AVG(f.familiarity_percentage) as avg_familiarity,
                SUM(f.total_questions) as total_questions,
                SUM(f.correct_answers) as total_correct,
                ROUND(SUM(f.correct_answers) / SUM(f.total_questions) * 100, 2) as overall_accuracy,
                (
                    SELECT COUNT(*) + 1
                    FROM (
                        SELECT AVG(f2.familiarity_percentage) as avg_fam
                        FROM familiarity f2
                        GROUP BY f2.user_id
                        HAVING avg_fam > (
                            SELECT AVG(f3.familiarity_percentage)
                            FROM familiarity f3
                            WHERE f3.user_id = ?
                        )
                        AND f2.user_id NOT IN (" . implode(',', getSuspiciousUsers($db)) . ")
                    ) as subquery
                ) as rank
            FROM familiarity f
            WHERE f.user_id = ?
        ");
        $stmt->execute([$user_id, $user_id]);
    }
    
    $user_rank = $stmt->fetch();
    
    if ($user_rank) {
        echo json_encode(['success' => true, 'user_rank' => $user_rank]);
    } else {
        echo json_encode(['error' => '尚未有排名資料']);
    }
}

function getSuspiciousUsers($db) {
    // 防作弊檢測邏輯
    $suspicious_users = [];
    
    // 1. 檢測異常答題時間（太快或太慢）
    $stmt = $db->prepare("
        SELECT DISTINCT ar.user_id
        FROM answer_records ar
        WHERE ar.answer_time < 2 OR ar.answer_time > 300
        GROUP BY ar.user_id
        HAVING COUNT(*) > 10
    ");
    $stmt->execute();
    $fast_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $suspicious_users = array_merge($suspicious_users, $fast_users);
    
    // 2. 檢測異常正確率（100%且題數很多）
    $stmt = $db->prepare("
        SELECT f.user_id
        FROM familiarity f
        WHERE f.familiarity_percentage = 100 
        AND f.total_questions > 20
        AND f.correct_answers = f.total_questions
    ");
    $stmt->execute();
    $perfect_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $suspicious_users = array_merge($suspicious_users, $perfect_users);
    
    // 3. 檢測短時間內大量答題
    $stmt = $db->prepare("
        SELECT ar.user_id
        FROM answer_records ar
        WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY ar.user_id
        HAVING COUNT(*) > 50
    ");
    $stmt->execute();
    $spam_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $suspicious_users = array_merge($suspicious_users, $spam_users);
    
    // 4. 檢測答題穩定性異常
    $stmt = $db->prepare("
        SELECT ar.user_id
        FROM answer_records ar
        GROUP BY ar.user_id
        HAVING STDDEV(ar.answer_time) < 1 
        AND COUNT(*) > 15
    ");
    $stmt->execute();
    $stable_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $suspicious_users = array_merge($suspicious_users, $stable_users);
    
    return array_unique($suspicious_users);
}

function isSuspiciousUser($db, $user_id) {
    $suspicious_users = getSuspiciousUsers($db);
    return in_array($user_id, $suspicious_users);
}
?> 