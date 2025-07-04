<?php
require_once 'config/database.php';
require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

echo "=== 重新計算熟悉度 ===\n";

// 難度加權係數
$difficulty_multipliers = [
    '初級' => 0.3,  // 熟悉度上限 30%
    '中級' => 0.5,  // 熟悉度上限 50%
    '高級' => 0.8,  // 熟悉度上限 80%
    '地獄' => 1.0   // 熟悉度上限 100%
];

// 獲取所有使用者的答題記錄
$stmt = $db->prepare("
    SELECT 
        ar.user_id,
        q.topic_id,
        q.difficulty,
        COUNT(*) as total_questions,
        SUM(ar.is_correct) as correct_answers
    FROM answer_records ar
    JOIN questions q ON ar.question_id = q.id
    GROUP BY ar.user_id, q.topic_id, q.difficulty
    ORDER BY ar.user_id, q.topic_id
");
$stmt->execute();
$records = $stmt->fetchAll();

echo "找到 " . count($records) . " 筆答題記錄\n";

// 重新計算熟悉度
foreach ($records as $record) {
    $user_id = $record['user_id'];
    $topic_id = $record['topic_id'];
    $difficulty = $record['difficulty'];
    $total_questions = $record['total_questions'];
    $correct_answers = $record['correct_answers'];
    
    // 計算正確率
    $accuracy_rate = ($correct_answers / $total_questions) * 100;
    
    // 熟悉度 = 正確率 × 難度加權
    $multiplier = $difficulty_multipliers[$difficulty] ?? 1.0;
    $percentage = min($accuracy_rate * $multiplier, 100);
    
    echo "使用者 $user_id, 主題 $topic_id ($difficulty): 正確率 $accuracy_rate%, 熟悉度 $percentage%\n";
    
    // 更新或插入熟悉度記錄
    $stmt = $db->prepare("
        INSERT INTO familiarity (user_id, topic_id, familiarity_percentage, total_questions, correct_answers) 
        VALUES (?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        familiarity_percentage = VALUES(familiarity_percentage),
        total_questions = VALUES(total_questions),
        correct_answers = VALUES(correct_answers)
    ");
    $stmt->execute([$user_id, $topic_id, $percentage, $total_questions, $correct_answers]);
}

echo "\n=== 重新計算完成 ===\n";

// 顯示更新後的熟悉度
$stmt = $db->prepare("
    SELECT 
        f.user_id,
        u.username,
        f.topic_id,
        t.name as topic_name,
        f.familiarity_percentage,
        f.total_questions,
        f.correct_answers
    FROM familiarity f
    JOIN users u ON f.user_id = u.id
    JOIN topics t ON f.topic_id = t.id
    ORDER BY f.user_id, f.familiarity_percentage DESC
");
$stmt->execute();
$familiarity = $stmt->fetchAll();

echo "\n=== 更新後的熟悉度 ===\n";
foreach ($familiarity as $f) {
    echo "使用者: {$f['username']}, 主題: {$f['topic_name']}, 熟悉度: {$f['familiarity_percentage']}%, 題數: {$f['total_questions']}, 正確: {$f['correct_answers']}\n";
}
?> 