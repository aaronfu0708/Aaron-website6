-- AI 題目生成與筆記學習系統資料庫結構
-- 建立資料庫
CREATE DATABASE IF NOT EXISTS ai_learning_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_learning_game;

-- 使用者表格
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_subscribed BOOLEAN DEFAULT FALSE,
    subscription_expires_at TIMESTAMP NULL,
    role ENUM('user', 'admin') DEFAULT 'user'
);



-- 主題表格
CREATE TABLE topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_popular BOOLEAN DEFAULT FALSE,
    is_sensitive BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 題目表格
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    question_text TEXT NOT NULL,
    correct_answer TEXT NOT NULL,
    options JSON NOT NULL,
    difficulty ENUM('初級', '中級', '高級', '地獄') NOT NULL,
    explanation TEXT,
    created_by_ai BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
);

-- 答題記錄表格
CREATE TABLE answer_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    user_answer TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL,
    answer_time INT NOT NULL, -- 答題時間(秒)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- 熟悉度表格
CREATE TABLE familiarity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic_id INT NOT NULL,
    familiarity_percentage DECIMAL(5,2) DEFAULT 0.00,
    total_questions INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_topic (user_id, topic_id)
);

-- 錯題收藏表格
CREATE TABLE wrong_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    user_answer TEXT NOT NULL,
    is_starred BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_question (user_id, question_id)
);

-- 筆記表格 (限訂閱用戶)
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    tags JSON,
    wrong_question_id INT NULL, -- 關聯錯題收藏
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (wrong_question_id) REFERENCES wrong_questions(id) ON DELETE SET NULL
);

-- 每日答題限制表格
CREATE TABLE daily_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    questions_answered INT DEFAULT 0,
    date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date)
);



-- 插入預設主題
INSERT INTO topics (name, description, is_popular) VALUES
('Python程式設計', 'Python基礎語法與程式設計概念', TRUE),
('多益英語', 'TOEIC考試相關英語題目', TRUE),
('統計學', '基礎統計概念與應用', TRUE),
('數學', '基礎數學運算與概念', TRUE),
('歷史', '世界歷史重要事件', FALSE),
('地理', '世界地理知識', FALSE),
('科學', '自然科學基礎知識', FALSE);

-- 插入萬能帳號 (密碼: fs1011)
INSERT INTO users (username, password, email, is_subscribed, role) VALUES
('Aaron', '$2y$10$G83p2b.GrDES9Nn1VqcJ1.KGVP.Vp4G/kaomKy9xZRWpiykG4.XSa', 'aaron@example.com', TRUE, 'admin');

 