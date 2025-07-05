<?php
// 系統設定檔
define('OPENAI_API_KEY', ''); // 替換OpenAI API金鑰
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// AI 服務設定
define('USE_COHERE', true); 
define('COHERE_API_KEY', ''); // 替換 Cohere API 金鑰
define('COHERE_API_URL', 'https://api.cohere.ai/v1/generate');


// 遊戲設定
define('FREE_USER_DAILY_LIMIT', 20);
define('FREE_USER_TOPIC_LIMIT', 2);
define('SUBSCRIBED_USER_MAX_QUESTIONS', 50);

// 熟悉度設定
define('FAMILIARITY_LEVELS', [
    '初級' => 30,
    '中級' => 50,
    '高級' => 80,
    '地獄' => 100
]);



// 敏感詞過濾
define('SENSITIVE_WORDS', [
    '暴力', '色情', '賭博', '毒品', '政治敏感', '宗教爭議'
]);

// 熱門主題推薦
define('POPULAR_TOPICS', [
    'Python程式設計',
    '多益英語',
    '統計學',
    '數學',
    'JavaScript',
    '資料結構',
    '演算法',
    '網路安全'
]);

// 主題範例模板
define('TOPIC_TEMPLATES', [
    '程式設計' => '我想要{「Python」、「JavaScript」、「Java」}題目',
    '語言學習' => '我想要{「多益」、「托福」、「日文」}題目',
    '數學' => '我想要{「微積分」、「線性代數」、「機率統計」}題目',
    '科學' => '我想要{「物理」、「化學」、「生物」}題目'
]);
?> 