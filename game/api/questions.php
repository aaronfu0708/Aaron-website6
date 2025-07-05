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
    case 'generate':
        generateQuestions($db);
        break;
    case 'submit_answer':
        submitAnswer($db);
        break;
    case 'get_familiarity':
        getFamiliarity($db);
        break;
    case 'get_daily_limit':
        getDailyLimit($db);
        break;
    case 'get_topics':
        getTopics($db);
        break;
    default:
        echo json_encode(['error' => '無效的操作']);
        break;
}

function generateQuestions($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $topic = $data['topic'] ?? '';
    $difficulty = $data['difficulty'] ?? '初級';
    $question_count = $data['question_count'] ?? 10;

    if (empty($topic)) {
        echo json_encode(['error' => '請選擇主題']);
        return;
    }

    // 檢查敏感內容
    foreach (SENSITIVE_WORDS as $word) {
        if (strpos($topic, $word) !== false) {
            echo json_encode(['error' => '主題包含不當內容']);
            return;
        }
    }

    // 檢查每日限制
    $daily_limit = getDailyLimit($db);
    if (!$daily_limit['can_answer']) {
        echo json_encode(['error' => '已達到每日答題限制']);
        return;
    }

    // 檢查題數限制
    $user_id = $_SESSION['user_id'];
    $is_subscribed = $_SESSION['is_subscribed'] ?? false;
    
    if (!$is_subscribed && $question_count > 10) {
        $question_count = 10;
    } elseif ($is_subscribed && $question_count > SUBSCRIBED_USER_MAX_QUESTIONS) {
        $question_count = SUBSCRIBED_USER_MAX_QUESTIONS;
    }

    // 檢查主題是否存在，不存在則建立
    $stmt = $db->prepare("SELECT id FROM topics WHERE name = ?");
    $stmt->execute([$topic]);
    $topic_record = $stmt->fetch();
    
    if (!$topic_record) {
        $stmt = $db->prepare("INSERT INTO topics (name, description) VALUES (?, ?)");
        $stmt->execute([$topic, "AI生成的主題"]);
        $topic_id = $db->lastInsertId();
    } else {
        $topic_id = $topic_record['id'];
    }

    // 生成題目
    $questions = [];
    for ($i = 0; $i < $question_count; $i++) {
        $question = generateSingleQuestion($topic, $difficulty);
        if ($question) {
            // 儲存題目到資料庫
            $stmt = $db->prepare("INSERT INTO questions (topic_id, question_text, correct_answer, options, difficulty, explanation) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $topic_id,
                $question['question'],
                $question['correct_answer'],
                json_encode($question['options']),
                $difficulty,
                $question['explanation']
            ]);
            
            $question['id'] = $db->lastInsertId();
            $questions[] = $question;
        }
    }

    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'topic' => $topic,
        'difficulty' => $difficulty
    ]);
}

function generateSingleQuestion($topic, $difficulty) {
    $prompt = "請為主題「{$topic}」生成一道{$difficulty}難度的選擇題。要求：
1. 題目要清晰明確
2. 提供4個選項，其中只有1個正確答案
3. 選項要合理，不要出現明顯的錯誤
4. 提供詳細的解析說明
5. 內容要適合學習，避免不當內容

請以JSON格式回傳：
{
    \"question\": \"題目內容\",
    \"options\": [\"選項A\", \"選項B\", \"選項C\", \"選項D\"],
    \"correct_answer\": \"正確答案\",
    \"explanation\": \"詳細解析\"
}";

    $response = callOpenAI($prompt);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['question']) && isset($data['options']) && isset($data['correct_answer'])) {
            return $data;
        }
    }
    
    // 如果AI生成失敗，回傳預設題目
    return [
        'question' => "關於{$topic}的基本概念，下列何者正確？",
        'options' => [
            "選項A：這是一個正確的答案",
            "選項B：這是一個錯誤的答案", 
            "選項C：這也是一個錯誤的答案",
            "選項D：這同樣是錯誤的答案"
        ],
        'correct_answer' => "選項A：這是一個正確的答案",
        'explanation' => "這是關於{$topic}的基本概念說明。"
    ];
}

function callOpenAI($prompt) {
    // 根據設定選擇使用哪個 AI 服務
    if (defined('USE_COHERE') && USE_COHERE) {
        return callCohere($prompt);
    } else if (defined('USE_HUGGINGFACE') && USE_HUGGINGFACE) {
        return callHuggingFace($prompt);
    } else {
        return callOpenAIService($prompt);
    }
}

function callCohere($prompt) {
    $data = [
        'model' => 'command',
        'prompt' => $prompt,
        'max_tokens' => 500,
        'temperature' => 0.7,
        'k' => 0,
        'stop_sequences' => [],
        'return_likelihoods' => 'NONE'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, COHERE_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . COHERE_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        return null;
    }

    if ($response) {
        $result = json_decode($response, true);
        if (isset($result['generations'][0]['text'])) {
            return $result['generations'][0]['text'];
        }
    }

    return null;
}



function callOpenAIService($prompt) {
    // 檢查 API 金鑰是否有效
    if (empty(OPENAI_API_KEY) || OPENAI_API_KEY === 'your-openai-api-key-here') {
        return null;
    }

    // 嘗試多個模型
    $models = ['gpt-4o-mini', 'gpt-3.5-turbo'];
    
    foreach ($models as $model) {
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是一個專業的教育題目生成助手，專門為學習者生成高品質的選擇題。'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, OPENAI_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // 檢查錯誤
        if ($error) {
            continue;
        }

        if ($httpCode === 429) {
            continue;
        }

        if ($httpCode !== 200) {
            continue;
        }

        if ($response) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                return $result['choices'][0]['message']['content'];
            } else if (isset($result['error'])) {
                continue;
            }
        }
    }

    return null;
}

function submitAnswer($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => '只允許POST請求']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $question_id = $data['question_id'] ?? 0;
    $user_answer = $data['user_answer'] ?? '';
    $answer_time = $data['answer_time'] ?? 0;
    $is_correct = $data['is_correct'] ?? false;
    // 修正：確保 is_correct 一定是 0 或 1
    $is_correct = ($is_correct) ? 1 : 0;

    if (!$question_id || empty($user_answer)) {
        echo json_encode(['error' => '缺少必要參數']);
        return;
    }

    $user_id = $_SESSION['user_id'];

    // 記錄答題
    $stmt = $db->prepare("INSERT INTO answer_records (user_id, question_id, user_answer, is_correct, answer_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $question_id, $user_answer, $is_correct, $answer_time]);

    // 更新每日限制
    $stmt = $db->prepare("INSERT INTO daily_limits (user_id, questions_answered, date) VALUES (?, 1, CURDATE()) ON DUPLICATE KEY UPDATE questions_answered = questions_answered + 1");
    $stmt->execute([$user_id]);

    // 更新熟悉度
    updateFamiliarity($db, $user_id, $question_id, $is_correct);

    echo json_encode(['success' => true]);
}

function updateFamiliarity($db, $user_id, $question_id, $is_correct) {
    // 獲取題目主題和難度
    $stmt = $db->prepare("SELECT topic_id, difficulty FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();
    
    if (!$question) return;

    $topic_id = $question['topic_id'];
    $difficulty = $question['difficulty'];

    // 難度加權係數
    $difficulty_multipliers = [
        '初級' => 0.3,  // 熟悉度上限 30%
        '中級' => 0.5,  // 熟悉度上限 50%
        '高級' => 0.8,  // 熟悉度上限 80%
        '地獄' => 1.0   // 熟悉度上限 100%
    ];

    $multiplier = $difficulty_multipliers[$difficulty] ?? 1.0;

    // 檢查是否已有熟悉度記錄
    $stmt = $db->prepare("SELECT * FROM familiarity WHERE user_id = ? AND topic_id = ?");
    $stmt->execute([$user_id, $topic_id]);
    $familiarity = $stmt->fetch();

    if ($familiarity) {
        // 更新現有記錄
        $total_questions = $familiarity['total_questions'] + 1;
        $correct_answers = $familiarity['correct_answers'] + ($is_correct ? 1 : 0);
        
        // 計算正確率
        $accuracy_rate = ($correct_answers / $total_questions) * 100;
        
        // 熟悉度 = 正確率 × 難度加權
        $percentage = min($accuracy_rate * $multiplier, 100);

        $stmt = $db->prepare("UPDATE familiarity SET total_questions = ?, correct_answers = ?, familiarity_percentage = ? WHERE user_id = ? AND topic_id = ?");
        $stmt->execute([$total_questions, $correct_answers, $percentage, $user_id, $topic_id]);
    } else {
        // 建立新記錄
        $percentage = $is_correct ? (100 * $multiplier) : 0;
        $stmt = $db->prepare("INSERT INTO familiarity (user_id, topic_id, familiarity_percentage, total_questions, correct_answers) VALUES (?, ?, ?, 1, ?)");
        $stmt->execute([$user_id, $topic_id, $percentage, $is_correct ? 1 : 0]);
    }


}



function getFamiliarity($db) {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("
        SELECT f.*, t.name as topic_name 
        FROM familiarity f 
        JOIN topics t ON f.topic_id = t.id 
        WHERE f.user_id = ? 
        ORDER BY f.familiarity_percentage DESC
    ");
    $stmt->execute([$user_id]);
    $familiarity = $stmt->fetchAll();

    echo json_encode(['success' => true, 'familiarity' => $familiarity]);
}

function getDailyLimit($db) {
    $user_id = $_SESSION['user_id'];
    $is_subscribed = $_SESSION['is_subscribed'] ?? false;
    
    $stmt = $db->prepare("SELECT questions_answered FROM daily_limits WHERE user_id = ? AND date = CURDATE()");
    $stmt->execute([$user_id]);
    $record = $stmt->fetch();
    
    $answered_today = $record ? $record['questions_answered'] : 0;
    $limit = $is_subscribed ? SUBSCRIBED_USER_MAX_QUESTIONS : FREE_USER_DAILY_LIMIT;
    $can_answer = $answered_today < $limit;
    
    return [
        'answered_today' => $answered_today,
        'limit' => $limit,
        'can_answer' => $can_answer,
        'remaining' => max(0, $limit - $answered_today)
    ];
}

function getTopics($db) {
    $stmt = $db->prepare("SELECT * FROM topics ORDER BY is_popular DESC, name ASC");
    $stmt->execute();
    $topics = $stmt->fetchAll();

    echo json_encode([
        'success' => true, 
        'topics' => $topics,
        'popular_topics' => POPULAR_TOPICS,
        'templates' => TOPIC_TEMPLATES
    ]);
}
?> 