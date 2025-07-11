<?php
// 檢查資料庫連線並自動創建表格
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("無法連接到資料庫");
    }
    
    // 檢查是否需要創建表格
    $stmt = $db->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        // 自動執行安裝
        $sql_file = 'database/schema.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            
            // 移除CREATE DATABASE和USE語句
            $sql_content = preg_replace('/CREATE DATABASE.*?;/s', '', $sql_content);
            $sql_content = preg_replace('/USE.*?;/s', '', $sql_content);
            
            // 分割SQL語句
            $statements = [];
            $current_statement = '';
            $lines = explode("\n", $sql_content);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if (empty($line) || strpos($line, '--') === 0) {
                    continue;
                }
                
                $current_statement .= $line . "\n";
                
                if (strpos($line, ';') !== false) {
                    $statements[] = trim($current_statement);
                    $current_statement = '';
                }
            }
            
            if (!empty(trim($current_statement))) {
                $statements[] = trim($current_statement);
            }
            
            // 執行SQL語句
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;
                
                try {
                    $db->exec($statement);
                } catch (PDOException $e) {
                    // 忽略重複建立表格的錯誤
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
        }
    }
    
} catch (Exception $e) {
    // 如果資料庫連線失敗，顯示錯誤訊息
    echo "<h1>系統錯誤</h1>";
    echo "<p>資料庫連線失敗: " . $e->getMessage() . "</p>";
    echo "<p>請檢查：</p>";
    echo "<ul>";
    echo "<li>XAMPP 是否正在運行</li>";
    echo "<li>MySQL 服務是否啟動</li>";
    echo "<li>資料庫設定是否正確</li>";
    echo "</ul>";
    echo "<p><a href='install.php'>點擊這裡執行安裝</a></p>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 題目生成與筆記學習系統</title>
    <script src="js/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@4.3.0/marked.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Press Start 2P', cursive;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }


        .nintendo-btn {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            border: 3px solid #fff;
            border-radius: 10px;
            color: white;
            padding: 15px 25px;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 0 #c44569;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nintendo-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 0 #c44569;
        }

        .nintendo-btn:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #c44569;
        }

        .nintendo-btn.secondary {
            background: linear-gradient(45deg, #4834d4, #686de0);
            box-shadow: 0 4px 0 #2c3e50;
        }

        .nintendo-btn.secondary:hover {
            box-shadow: 0 6px 0 #2c3e50;
        }

        .nintendo-btn.secondary:active {
            box-shadow: 0 2px 0 #2c3e50;
        }

   
        .nintendo-card {
            background: rgba(255, 255, 255, 0.1);
            border: 3px solid #fff;
            border-radius: 15px;
            padding: 20px;
            margin: 15px 0;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .nintendo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .nintendo-input {
            background: rgba(255, 255, 255, 0.9);
            border: 3px solid #fff;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Press Start 2P', cursive;
            font-size: 14px;
            color: #333;
            width: 100%;
            transition: all 0.3s ease;
        }

        .nintendo-input:focus {
            outline: none;
            border-color: #ff6b6b;
            box-shadow: 0 0 15px rgba(255, 107, 107, 0.5);
        }

       
        .nintendo-select {
            background: rgba(255, 255, 255, 0.9);
            border: 3px solid #fff;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Press Start 2P', cursive;
            font-size: 14px;
            color: #333;
            width: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 20px;
            padding-right: 50px;
        }

        .nintendo-select:focus {
            outline: none;
            border-color: #ff6b6b;
            box-shadow: 0 0 15px rgba(255, 107, 107, 0.5);
        }

        .nintendo-select option {
            background: #fff;
            color: #333;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            padding: 10px;
        }

   
        .nintendo-title {
            text-align: center;
            font-size: 24px;
            margin: 30px 0;
            text-shadow: 3px 3px 0 #c44569;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

     
        .fade-enter-active, .fade-leave-active {
            transition: opacity 0.5s;
        }
        .fade-enter-from, .fade-leave-to {
            opacity: 0;
        }

        .slide-enter-active, .slide-leave-active {
            transition: transform 0.5s;
        }
        .slide-enter-from {
            transform: translateX(-100%);
        }
        .slide-leave-to {
            transform: translateX(100%);
        }



        .question-container {
            background: rgba(255, 255, 255, 0.1);
            border: 3px solid #fff;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
        }

        .question-text {
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .options-grid {
            display: grid;
            gap: 15px;
            margin: 20px 0;
        }

        .option-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid #fff;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }

        .option-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .option-btn.selected {
            background: #ff6b6b;
            border-color: #ff6b6b;
        }

        .option-btn.correct {
            background: #00b894;
            border-color: #00b894;
        }

        .option-btn.incorrect {
            background: #e17055;
            border-color: #e17055;
        }

      
        .progress-bar {
            width: 100%;
            height: 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(45deg, #00b894, #00cec9);
            transition: width 0.5s ease;
        }

   
        @media (max-width: 768px) {
            body {
                padding: 0;
                margin: 0;
                width: 100vw;
                overflow-x: hidden;
            }
            
            .container {
                padding: 0;
                max-width: 95vw;
                width: 95vw;
                margin: 0 auto;
            }
            .nintendo-title {
                font-size: 24px;
                margin: 20px 0;
            }
            .nintendo-btn {
                font-size: 18px;
                padding: auto;
                width: 50%;
                margin-bottom: 12px;
                min-height: 56px;
            }
            
  
            .hamburger-btn {
                display: flex !important;
            }
            
            .nav-menu {
                position: fixed !important;
                top: 60px;
                left: 2.5vw;
                right: 2.5vw;
                width: 95vw;
                max-width: 95vw;
                background: linear-gradient(135deg, #3a2676 80%, #5f4bb6 100%);
                border: 3px solid #fff;
                border-radius: 15px;
                padding: 20px;
                flex-direction: column;
                gap: 12px;
                z-index: 9999;
                display: none;
                box-shadow: 0 8px 25px rgba(60, 40, 120, 0.4), 0 4px 15px rgba(0, 0, 0, 0.3);
                backdrop-filter: blur(10px);
            }
            
            .nav-menu.nav-menu-open {
                display: flex !important;
                opacity: 1 !important;
                visibility: visible !important;
                animation: menuSlideIn 0.4s ease-out;
            }
            
            @keyframes menuSlideIn {
                0% {
                    transform: translateY(-20px);
                    opacity: 0;
                }
                50% {
                    transform: translateY(5px);
                    opacity: 0.8;
                }
                100% {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            .menu-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.3);
                z-index: 9998;
                display: none;
                backdrop-filter: blur(2px);
            }
            
            .menu-overlay.show {
                display: block;
            }
            
            .nav-btn {
                background: rgba(255,255,255,0.08);
                border: 2px solid #fff;
                border-radius: 12px;
                color: #fff;
                padding: 16px 0;
                font-family: 'Press Start 2P', cursive;
                font-size: 15px;
                cursor: pointer;
                transition: all 0.2s;
                box-shadow: 0 2px 0 #3a2676;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 6px;
            }
            
            .nav-btn:hover, .nav-btn:focus {
                background: linear-gradient(90deg, #ff6b6b 30%, #ee5a24 100%);
                color: #fff;
                box-shadow: 0 4px 12px #5f4bb6;
                transform: scale(1.04);
            }
            
            .logout-btn {
                background: linear-gradient(45deg, #e74c3c, #c0392b);
                color: #fff;
                border-color: #e74c3c;
            }
            
            .logout-btn:hover {
                background: linear-gradient(45deg, #ff6b6b, #e74c3c);
                transform: scale(1.05);
            }
            
            .subscribe-btn {
                background: linear-gradient(90deg, #ffe259 0%, #ffa751 100%);
                color: #3a2676;
                font-weight: bold;
                border: 2px solid #fff;
            }
            .subscribe-btn:hover, .subscribe-btn:focus {
                background: linear-gradient(90deg, #ffb347 0%, #ffcc33 100%);
                color: #3a2676;
            }
            
         
            .leaderboard-item {
                padding: 18px 12px;
                gap: 12px;
            }
            
            .rank-badge {
                width: 50px;
                height: 50px;
                font-size: 14px;
            }
            
            .user-info h4 {
                font-size: 18px;
                margin-bottom: 10px;
            }
            
            .stats {
                gap: 6px;
            }
            
            .stat-item {
                font-size: 14px;
            }
            .nintendo-card {
                padding: 18px 8px;
                margin: 12px auto;
                border-radius: 12px;
                width: 96%;
                max-width: 96%;
                box-sizing: border-box;
            }
            .nintendo-input {
                font-size: 18px;
                padding: 18px 12px;
                border-radius: 12px;
                width: 100%;
            }
            
            .nintendo-select {
                font-size: 18px;
                padding: 18px 12px;
                border-radius: 12px;
                width: 100%;
                background-size: 24px;
                padding-right: 60px;
            }

            .question-container {
                padding: 18px 8px;
                font-size: 18px;
            }
            .question-text {
                font-size: 20px;
            }
            .options-grid {
                gap: 18px;
            }
            .option-btn {
                font-size: 18px;
                padding: 18px 12px;
                border-radius: 12px;
                width: 100%;
            }
            .progress-bar {
                height: 24px;
                border-radius: 12px;
            }
            .modal-content {
                padding: auto 2vw;
                max-width: 98vw;
                border-radius: 12px;
            }
            .note-editor textarea {
                font-size: 18px;
                min-height: 180px;
            }
            .note-preview {
                font-size: 18px;
            }
            .note-btn-group {
            display: flex;
            flex-direction: row;
            gap: 12px;
        }
        .question-btn-group {
            display: flex;
            flex-direction: row;
            gap: 8px;
        }
        .note-action-btn {
            font-size: 15px !important;
            min-width: 70px;
            min-height: 36px;
            padding: 8px 0;
            width: auto;
            margin-bottom: 0;
        }
            .create-note-btn {
                font-size: 13px !important;
                min-width: 80px;
                min-height: 32px;
                padding: 6px 0;
                width: auto;
                margin-bottom: 10px;
                display: inline-block;
            }
            .note-create-inline {
                margin-left: 10px !important;
                margin-bottom: 0 !important;
            }
            .modal-content .nintendo-btn {
                font-size: 15px !important;
                min-width: 80px;
                min-height: 32px;
                padding: 8px 0;
                margin-right: 8px;
            }
            
            .game-loading {
                width: 100vw;
                height: 100vh;
                padding: 20px;
            }
            
            .game-loading .loading {
                width: 60px;
                height: 60px;
                border-width: 5px;
                margin-bottom: 25px;
            }
            
            .game-loading h3 {
                font-size: 20px;
                margin-bottom: 12px;
            }
            
            .game-loading p {
                font-size: 14px;
                max-width: 300px;
                line-height: 1.5;
            }
        }

      
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .game-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            backdrop-filter: blur(10px);
            pointer-events: all;
            user-select: none;
        }
        
        .game-loading .loading {
            width: 80px;
            height: 80px;
            border-width: 6px;
            margin-bottom: 30px;
            border-top-color: #fff;
            border-right-color: #fff;
            border-bottom-color: rgba(255, 255, 255, 0.3);
            border-left-color: rgba(255, 255, 255, 0.3);
        }
        
        .game-loading h3 {
            color: #fff;
            font-size: 24px;
            margin-bottom: 15px;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            animation: pulse 2s infinite;
        }
        
        .game-loading p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            text-align: center;
            max-width: 400px;
            line-height: 1.6;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .navigation-container {
            position: relative;
            z-index: 9998;
        }
        
        .nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .hamburger-btn {
            display: flex;
            flex-direction: column;
            background: linear-gradient(45deg, rgba(255, 107, 107, 0.9), rgba(238, 90, 36, 0.9));
            border: 2px solid #fff;
            border-radius: 8px;
            cursor: pointer;
            padding: 8px;
            width: 44px;
            height: 44px;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            position: relative;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
            background: linear-gradient(45deg, rgba(255, 107, 107, 1), rgba(238, 90, 36, 1));
        }
        
        .hamburger-btn span {
            width: 22px;
            height: 3px;
            background: #fff;
            margin: 2px 0;
            transition: 0.3s;
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .nav-menu {
            position: fixed !important;
            top: 60px;
            left: 2.5vw;
            right: 2.5vw;
            width: 95vw;
            max-width: 95vw;
            z-index: 9999;
            display: none;
            background: linear-gradient(135deg, #3a2676 80%, #5f4bb6 100%);
            border: 3px solid #fff;
            border-radius: 15px;
            padding: 20px;
            flex-direction: column;
            gap: 12px;
            box-shadow: 0 8px 25px rgba(60, 40, 120, 0.4), 0 4px 15px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 9998;
            display: none;
            backdrop-filter: blur(2px);
        }
        
        .menu-overlay.show {
            display: block;
        }
        
        .nav-menu.nav-menu-open {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .nav-btn {
            background: rgba(255,255,255,0.08);
            border: 2px solid #fff;
            border-radius: 12px;
            color: #fff;
            padding: 16px 0;
            font-family: 'Press Start 2P', cursive;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 0 #3a2676;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        
        .nav-btn:hover, .nav-btn:focus {
            background: linear-gradient(90deg, #ff6b6b 30%, #ee5a24 100%);
            color: #fff;
            box-shadow: 0 4px 12px #5f4bb6;
            transform: scale(1.04);
        }
        
        .logout-btn {
            background: linear-gradient(45deg, rgba(72, 52, 212, 0.9), rgba(104, 109, 224, 0.7));
            color: #fff;
            border-color: #4834d4;
        }
        
        .logout-btn:hover {
            background: linear-gradient(45deg, rgba(72, 52, 212, 1), rgba(104, 109, 224, 0.9));
        }
        
        .subscribe-btn {
            background: linear-gradient(90deg, #ffe259 0%, #ffa751 100%);
            color: #3a2676;
            font-weight: bold;
            border: 2px solid #fff;
        }
        .subscribe-btn:hover, .subscribe-btn:focus {
            background: linear-gradient(90deg, #ffb347 0%, #ffcc33 100%);
            color: #3a2676;
        }

        /* 答題結果樣式 */
        .question-result {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid #fff;
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
            transition: all 0.3s ease;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .question-number {
            font-weight: bold;
            color: #fff;
        }
        
        .result-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
        }
        
        .result-badge.correct {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
        }
        
        .result-badge.incorrect {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
        }
        
        .question-content {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }
        
        .question-content p {
            margin: 8px 0;
        }
        
   
        .leaderboard-item {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid #fff;
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .leaderboard-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .rank-badge {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
        }

        .user-info h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: #fff;
        }

        .stats {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stat-item {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
        }

        .stat-item strong {
            color: #fff;
        }
     
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            padding: 15px 20px;
            border-radius: 10px;
            border: 3px solid #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            z-index: 99999;
            animation: slideIn 0.1s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

     
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            border: 3px solid #fff;
            border-radius: 15px;
            padding: 30px 12px;
            max-width: 500px;
            width: 90%;
            max-height: 70vh;
            overflow-y: auto;
            color: #333;
        }

   
        .note-editor {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
        }

        .note-editor textarea {
            width: 100%;
            min-height: 200px;
            border: none;
            resize: vertical;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
        }

        .note-preview {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
            color: #333;
        }

        .nintendo-card h4 {
            word-break: break-all;
            word-wrap: break-word;
            max-width: 100%;
            white-space: normal;
            overflow-wrap: break-word;
            font-size: 18px;
            margin: 0 0 8px 0;
        }

        .nintendo-card .flex-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .auth-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
            padding: 0 4vw;
        }
        
        .auth-form-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.05) 100%);
            border: 3px solid rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 30px 25px;
            backdrop-filter: blur(15px);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                0 4px 16px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 380px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        
        .auth-form-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
            pointer-events: none;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .auth-form-container h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 20px;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }
        
        .auth-container form {
            width: 100%;
            position: relative;
            z-index: 1;
        }
        
        .auth-container .nintendo-btn {
            width: 100%;
            font-size: 16px;
            padding: 16px 0;
            margin-bottom: 12px;
            border-radius: 12px;
            position: relative;
            z-index: 1;
        }
        
        .auth-container .nintendo-btn.secondary {
            margin-top: 0;
            background: linear-gradient(45deg, #4834d4, #686de0);
        }
        
        .auth-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .auth-submit-btn,
        .auth-toggle-btn {
            width: 100%;
            font-size: 16px;
            padding: 14px 0;
            border-radius: 12px;
        }
        
        @media (max-width: 768px) {
            .nintendo-title {
                font-size: 28px;
                margin: 50px auto;
            }
            
            .auth-form-container {
                padding: 25px 20px;
                max-width: 90vw;
                border-radius: 16px;
            }
            
            .auth-form-container h2 {
                font-size: 22px;
                margin-bottom: 20px;
            }
            
            .auth-buttons {
                flex-direction: row;
                gap: 8px;
            }
            
            .auth-submit-btn,
            .auth-toggle-btn {
                flex: 1;
                font-size: 14px;
                padding: 12px 8px;
                min-width: 0;
            }
            .add-to-collection-btn {
                font-size: 12px;
                padding: 10px 15px;
                border-radius: 6px;
                min-height: 40px;
            }
            .modal-buttons {
                flex-direction: row;
                gap: 8px;
            }
            
            .modal-save-btn,
            .modal-cancel-btn {
                flex: 1;
                font-size: 14px;
                padding: 10px 8px;
                min-width: 0;
            }
        }

        .add-to-collection-btn {
            font-size: 14px;
            padding: 15px;
            border-radius: 8px;
        }
        
        
        .modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }
        
        .modal-save-btn,
        .modal-cancel-btn {
            width: 100%;
            font-size: 16px;
            padding: 12px 0;
            border-radius: 10px;
        }

.question-content,
.note-preview,
.question-text,
.nintendo-card p,
.nintendo-card pre,
.nintendo-card code {
    font-family: "Noto Serif TC", "PMingLiU", "Times New Roman", serif !important;
    font-size: 16px;
    letter-spacing: normal;
}

.nintendo-card strong,
.nintendo-card b {
    font-family: "Noto Serif TC", "PMingLiU", "Times New Roman", serif !important;
}


.correct-answer,
.user-answer {
    font-family: "Noto Serif TC", "PMingLiU", "Times New Roman", serif !important;
}

@media (max-width: 900px) {
  .admin-users-container .nintendo-card {
    overflow-x: auto;
    padding: 0;
  }
  .admin-users-container table {
    min-width: 600px;
    width: 100%;
    font-size: 15px;
    font-family: "Noto Serif TC", "PMingLiU", "Times New Roman", serif;
    background: #fff;
    color: #333;
  }
  .admin-users-container th,
  .admin-users-container td {
    white-space: nowrap;
    padding: 10px 8px;
    font-size: 15px;
    font-family: "Noto Serif TC", "PMingLiU", "Times New Roman", serif;
    line-height: 1.6;
  }
}

.admin-users-container .nintendo-card table {
  border-radius: 16px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 2px 12px rgba(120, 100, 200, 0.08);
  border-collapse: separate;
  border-spacing: 0;
}
.admin-users-container th,
.admin-users-container td {
  font-size: 16px;
  font-family: "Noto Serif TC", "PMingLiU", "Times New Roman", serif;
  padding: 12px 10px;
  line-height: 1.7;
  text-align: center;
  vertical-align: middle;
}
.admin-users-container th {
  background: #f8f9fa;
  color: #333;
}
.admin-users-container td {
  background: #fff;
  color: #333;
}
.user-badge {
  display: inline-block;
  padding: 0.2em 1.2em;
  border-radius: 1em;
  font-size: 0.95em;
  color: #fff;
  background: linear-gradient(45deg, #7b6eea, #764ba2);
  font-family: "Noto Serif TC", "PMingLiU", "Times New Roman", serif;
}
.user-badge.unsubscribed {
  background: #aaa;
}
.user-badge.admin {
  background: linear-gradient(45deg, #ffb347, #ffcc33);
  color: #333;
  font-weight: bold;
  border: 2px solid #fff;
}
    </style>
</head>
<body>
    <div id="app">
        <div class="container">
         
            <div v-if="loading" class="nintendo-card" style="text-align: center;">
                <div class="loading"></div>
                <p style="margin-top: 20px;">載入中...</p>
            </div>
            <div v-if="gameLoading" class="game-loading">
                <div class="loading"></div>

                <p>正在生成{{ questionCount }} 道 {{ selectedTopic }} 的題目<br>請稍等片刻，馬上開始挑戰！</p>
            </div>

          
            <div v-else-if="!isLoggedIn" class="auth-container">
                <h1 class="nintendo-title">AI 學習遊戲</h1>
                
                <div class="auth-form-container">
                    <h2>{{ isRegistering ? '註冊' : '登入' }}</h2>
                    
                    <form @submit.prevent="handleAuth">
                        <div style="margin-bottom: 15px;">
                            <input 
                                v-model="authForm.username" 
                                type="text" 
                                placeholder="使用者名稱" 
                                class="nintendo-input"
                                required
                            >
                        </div>
                        
                        <div v-if="isRegistering" style="margin-bottom: 15px;">
                            <input 
                                v-model="authForm.email" 
                                type="email" 
                                placeholder="Email" 
                                class="nintendo-input"
                                required
                            >
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <input 
                                v-model="authForm.password" 
                                type="password" 
                                placeholder="密碼" 
                                class="nintendo-input"
                                required
                            >
                        </div>
                        
                        <div class="auth-buttons">
                            <button type="submit" class="nintendo-btn auth-submit-btn">
                                {{ isRegistering ? '註冊' : '登入' }}
                            </button>
                            
                            <button type="button" @click="toggleAuthMode" class="nintendo-btn secondary auth-toggle-btn">
                                {{ isRegistering ? '已有帳號？登入' : '註冊' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

           
            <div v-else>
              
                <div class="nintendo-card navigation-container">
                    <div class="nav-header">
                        <h1 class="nintendo-title" style="margin: 0; font-size: 18px;">歡迎，{{ user.username }}！</h1>
                        <button @click="toggleMenu" class="hamburger-btn">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                    </div>
                </div>
         
                <div :class="['menu-overlay', { 'show': showMenu }]" @click="showMenu = false"></div>
                <div :class="['nav-menu', { 'nav-menu-open': showMenu }]">
                    <button @click="navigateTo('game')" class="nav-btn">遊戲</button>
                    <button @click="navigateTo('notes')" class="nav-btn">筆記</button>
                    <button @click="navigateTo('leaderboard')" class="nav-btn">排行榜</button>
                    <button v-if="!user.is_subscribed && user.username !== 'Aaron'" @click="navigateTo('subscribe')" class="nav-btn subscribe-btn">升級/訂閱</button>
                    <button v-if="user.is_subscribed && user.username !== 'Aaron'" @click="navigateTo('subscribeStatus')" class="nav-btn subscribe-btn">訂閱狀態</button>
                    <button v-if="user.username === 'Aaron'" @click="navigateTo('adminUsers')" class="nav-btn" style="background: linear-gradient(45deg, #ffb347, #ffcc33); color: #333;">用戶管理</button>
                    <button @click="logout" class="nav-btn logout-btn">登出</button>
                </div>

                <!-- 訂閱狀態頁面：所有用戶都能查看 -->
                <div v-if="currentView === 'subscribeStatus'" class="nintendo-card" style="max-width:480px;margin:30px auto;">
                  <h2 class="nintendo-title">訂閱狀態</h2>
                  <div style="margin-bottom:16px;">
                    <span v-if="user.is_subscribed">
                      <strong>目前方案：</strong>
                      <span v-if="user.subscription_type === 'month'">月訂閱（$30/月）</span>
                      <span v-else>訂閱用戶</span>
                    </span>
                    <span v-else>
                      <strong>目前方案：</strong> 一般用戶
                    </span>
                  </div>
                  <div v-if="user.is_subscribed">
                    <ul style="text-align:left;margin-bottom:20px;">
                      <li>無限生成主題與題目</li>
                      <li>完整筆記功能</li>
                      <li>專屬訂閱標示</li>
                      <li>未來更多專屬權益...</li>
                    </ul>
                    <button @click="unsubscribeUser" class="nintendo-btn" style="background:linear-gradient(45deg,#e74c3c,#c0392b);">取消訂閱</button>
                  </div>
                  <div v-else>
                    <ul style="text-align:left;margin-bottom:20px;">
                      <li>每日/主題數量有限制</li>
                      <li>無法建立個人筆記</li>
                      <li>無專屬訂閱標示</li>
                      <li>升級後享有完整權益</li>
                    </ul>
                    <div style="display:flex;gap:20px;justify-content:center;margin-bottom:20px;">
                      <label style="display:flex;align-items:center;gap:6px;">
                        <input type="radio" v-model="subscribePlan" value="month"> 月訂閱（$30/月）
                      </label>
                    </div>
                    <button @click="subscribeUser" class="nintendo-btn">立即升級</button>
                  </div>
                  <button @click="navigateTo('game')" class="nintendo-btn secondary" style="margin-left:10px;margin-top:10px;">返回</button>
                </div>

             
                <div v-if="currentView === 'game'" class="game-container">
                    <h2 class="nintendo-title">選擇主題開始挑戰！</h2>
               
                    <div class="nintendo-card">
                     
                        <input 
                            v-model="selectedTopic" 
                            placeholder="輸入主題，例如：Python程式設計" 
                            class="nintendo-input"
                            list="topics"
                        >
                        <datalist id="topics">
                            <option v-for="topic in topics" :value="topic.name">{{ topic.name }}</option>
                        </datalist>
                        
                        <div style="margin: 15px 0;">
                            
                            <select v-model="selectedTopic" class="nintendo-select">
                                <option value="">熱門主題</option>
                                <option v-for="topic in popularTopics" :value="topic">{{ topic }}</option>
                            </select>
                        </div>
                        
                        <select v-model="selectedDifficulty" class="nintendo-select">
                            <option v-for="difficulty in difficulties" :value="difficulty">{{ difficulty }}</option>
                        </select>
                    </div>

   

             
                    <div class="nintendo-card">
                        <input 
                            v-model.number="questionCount" 
                            type="number" 
                            :min="1" 
                            :max="user.is_subscribed ? 50 : 10"
                            class="nintendo-input"
                        >
                        <p style="margin-top: 10px; font-size: 12px;">
                            {{ user.is_subscribed ? '訂閱用戶可選最多50題' : '一般用戶最多可選10題，訂閱用戶可選最多50題' }}
                        </p>
                    </div>

             
                    <div style="text-align: center; margin: 20px 0;">
                        <button @click="startGame" class="nintendo-btn" :disabled="!selectedTopic">
                            開始挑戰！
                        </button>
                    </div>
                </div>

        
                <div v-if="currentView === 'quiz'" class="quiz-container">
                    <div class="nintendo-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3>第 {{ currentQuestionIndex + 1 }} 題 / {{ questions.length }}</h3>
                            <div class="progress-bar" style="width: 200px;">
                                <div class="progress-fill" :style="{ width: ((currentQuestionIndex + 1) / questions.length * 100) + '%' }"></div>
                            </div>
                        </div>

                        <div class="question-container">
                            <div class="question-text">{{ currentQuestion.question }}</div>
                            
                            <div class="options-grid">
                                <button 
                                    v-for="(option, index) in currentQuestion.options" 
                                    :key="index"
                                    @click="selectAnswer(option)"
                                    :class="['option-btn', { selected: selectedAnswer === option }]"
                                >
                                    {{ option }}
                                </button>
                            </div>

                            <div style="margin-top: 20px; text-align: center;">
                                <button @click="nextQuestion" class="nintendo-btn" :disabled="!selectedAnswer">
                                    {{ currentQuestionIndex + 1 < questions.length ? '下一題' : '完成挑戰' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

      
                <div v-if="currentView === 'results'" class="results-container">
                    <h2 class="nintendo-title">挑戰結果</h2>
                    
                    <div class="nintendo-card">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h3>總成績</h3>
                            <div style="font-size: 24px; margin: 10px 0;">
                                正確：{{ correctCount }} / {{ questions.length }}
                            </div>
                            <div style="font-size: 18px; color: #ff6b6b;">
                                正確率：{{ ((correctCount / questions.length) * 100).toFixed(1) }}%
                            </div>
                        </div>
                    </div>

                    <div class="nintendo-card">
                        <h3>題目詳情</h3>
                        <div v-for="(result, index) in questionResults" :key="index" class="question-result">
                            <div class="result-header">
                                <span class="question-number">第 {{ index + 1 }} 題</span>
                                <span :class="['result-badge', result.isCorrect ? 'correct' : 'incorrect']">
                                    {{ result.isCorrect ? '✓ 正確' : '✗ 錯誤' }}
                                </span>
                            </div>
                            
                            <div class="question-content">
                                <p><strong>題目：</strong>{{ result.question }}</p>
                                <p><strong>您的答案：</strong>{{ result.userAnswer }}</p>
                                <p><strong>正確答案：</strong>{{ result.correctAnswer }}</p>
                                <p><strong>解析：</strong>{{ result.explanation }}</p>
                            </div>
                            
                            <div style="margin-top: 10px;">
                                <button @click="addToWrongQuestions(result)" class="nintendo-btn secondary add-to-collection-btn">
                                    加入收藏
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin: 20px 0;">
                        <button @click="backToGame" class="nintendo-btn">回到遊戲</button>
                    </div>
                </div>



           
                <div v-if="currentView === 'notes'" class="notes-container">
                    <h2 class="nintendo-title">筆記系統</h2>
                 
                    <div class="nintendo-card">
                        <h3>題目收藏</h3>
                        <div v-if="wrongQuestions.length === 0" style="text-align: center; padding: 20px;">
                            還沒有收藏的題目
                        </div>
                        <div v-else>
                            <div v-for="question in wrongQuestions" :key="question.id" class="nintendo-card">
                                <div style="display: flex; align-items: center;">
                                    <h4>{{ question.topic_name }}</h4>
                                </div>
                                <p>{{ question.question_text }}</p>
                                <p><strong>您的答案：</strong>{{ question.user_answer }}</p>
                                <p><strong>正確答案：</strong>{{ question.correct_answer }}</p>
                                <p><strong>解析：</strong>{{ question.explanation }}</p>
                                <div v-if="user.is_subscribed" style="margin-top: 15px;">
                                    <button @click="createNoteFromQuestion(question)" class="nintendo-btn create-note-btn">建立筆記</button>
                                    <button @click="deleteWrongQuestion(question.id)" class="nintendo-btn create-note-btn" style="background: linear-gradient(45deg, #e74c3c, #c0392b); margin-left: 10px;">刪除</button>
                                </div>
                            </div>
                        </div>
                    </div>

           
                    <div v-if="user.is_subscribed" class="nintendo-card">
                        <div style="display: flex; align-items: center; justify-content: flex-start; margin-bottom: 10px;">
                            <h3 style="margin: 0;">我的筆記</h3>
                            <button @click="showCreateNote = true" class="nintendo-btn create-note-btn note-create-inline" style="margin-left: 5px;">建立新筆記</button>
                        </div>
                        <div v-if="notes.length === 0" style="text-align: center; padding: 20px;">
                            還沒有筆記
                        </div>
                        <div v-else>
                            <div v-for="note in notes" :key="note.id" class="nintendo-card">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <h4>{{ note.title }}</h4>
                                    <div class="note-btn-group">
                                        <button @click="editNote(note)" class="nintendo-btn note-action-btn">編輯</button>
                                        <button @click="deleteNote(note.id)" class="nintendo-btn note-action-btn">刪除</button>
                                    </div>
                                </div>
                                <div class="note-preview" v-html="renderMarkdown(note.content)"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="currentView === 'leaderboard'" class="leaderboard-container">
                    <h2 class="nintendo-title">排行榜</h2>
                    
                    <div class="nintendo-card">
                        <div style="margin-bottom: 20px;">
                            <h4>選擇排行榜：</h4>
                            <select @change="loadLeaderboard($event.target.value)" class="nintendo-select">
                                <option value="">總排行榜</option>
                                <option v-for="topic in topics" :key="topic.id" :value="topic.id">{{ topic.name }}排行榜</option>
                            </select>
                        </div>
                        
                        <div v-if="leaderboard.length === 0" style="text-align: center; padding: 20px;">
                            載入中...
                        </div>
                        <div v-else>
                            <div v-for="(user, index) in leaderboard" :key="index" class="leaderboard-item">
                                <div class="rank-badge">#{{ user.rank }}</div>
                                <div class="user-info">
                                    <h4>{{ user.username }}</h4>
                                    <div class="stats">
                                        <span class="stat-item">
                                            <strong>熟悉度:</strong> {{ parseFloat(user.familiarity_percentage || user.avg_familiarity || 0).toFixed(1) }}%
                                        </span>
                                        <span class="stat-item">
                                            <strong>正確率:</strong> {{ parseFloat(user.accuracy_rate || user.overall_accuracy || 0).toFixed(1) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 用戶管理頁面 -->
                <div v-if="currentView === 'adminUsers' && user.username === 'Aaron'" class="admin-users-container">
                    <h2 class="nintendo-title">用戶管理</h2>
                    <div class="nintendo-card">
                        <div v-if="adminUsersLoading" style="text-align:center; padding:20px;">載入中...</div>
                        <div v-else>
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>帳號</th>
                                        <th>Email</th>
                                        <th>訂閱狀態</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="u in adminUsers" :key="u.id">
                                        <td>{{ u.id }}</td>
                                        <td>{{ u.username }}</td>
                                        <td>{{ u.email }}</td>
                                        <td>
                                          <span
                                            :class="['user-badge', u.username === 'Aaron' ? 'admin' : (u.is_subscribed ? '' : 'unsubscribed')]"
                                          >
                                            {{ u.username === 'Aaron' ? '管理者' : (u.is_subscribed ? '訂閱' : '一般') }}
                                          </span>
                                        </td>
                                        <td>
                                            <button v-if="u.username !== 'Aaron' && !u.is_subscribed" @click="adminUpgradeUser(u.id)" class="nintendo-btn" style="font-size:12px; padding:4px 10px;">升級</button>
                                            <button v-if="u.username !== 'Aaron' && u.is_subscribed" @click="adminDowngradeUser(u.id)" class="nintendo-btn secondary" style="font-size:12px; padding:4px 10px;">降級</button>
                                            <button v-if="u.username !== 'Aaron'" @click="adminDeleteUser(u.id)" class="nintendo-btn" style="background:linear-gradient(45deg,#e74c3c,#c0392b);font-size:12px;padding:4px 10px; margin-left:5px;">刪除</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div style="text-align:center; margin:20px 0;">
                        <button @click="navigateTo('game')" class="nintendo-btn">回到遊戲</button>
                    </div>
                </div>

                <!-- 升級/訂閱頁 -->
                <div v-if="currentView === 'subscribe' && !user.is_subscribed" class="nintendo-card" style="max-width:480px;margin:30px auto;">
                  <h2 class="nintendo-title">升級成訂閱用戶</h2>
                  <p style="margin-bottom:10px;">選擇訂閱方案：</p>
                  <div style="display:flex;gap:20px;justify-content:center;margin-bottom:20px;">
                    <label style="display:flex;align-items:center;gap:6px;">
                      <input type="radio" v-model="subscribePlan" value="month"> 月訂閱（$30/月）
                    </label>
                  </div>
                  <ul style="text-align:left;margin-bottom:20px;">
                    <li>無限生成主題與題目</li>
                    <li>完整筆記功能</li>
                    <li>專屬訂閱標示</li>
                    <li>未來更多專屬權益...</li>
                  </ul>
                  <button @click="subscribeUser" class="nintendo-btn">前往付款（模擬）</button>
                  <button @click="navigateTo('game')" class="nintendo-btn secondary" style="margin-left:10px;">返回</button>
                </div>

                <!-- 取消訂閱頁 -->
                <div v-if="currentView === 'unsubscribe' && user.is_subscribed" class="nintendo-card" style="max-width:480px;margin:30px auto;">
                  <h2 class="nintendo-title">取消訂閱</h2>
                  <p style="margin-bottom:10px;">目前訂閱方案：
                    <span v-if="user.subscription_type === 'month'">月訂閱（$30/月）</span>
                    <span v-else>訂閱用戶</span>
                  </p>
                  <ul style="text-align:left;margin-bottom:20px;">
                    <li>取消後將失去無限生成主題與題目權益</li>
                    <li>筆記功能將關閉</li>
                    <li>專屬訂閱標示將移除</li>
                    <li>未來如需再次訂閱可隨時升級</li>
                  </ul>
                  <button @click="unsubscribeUser" class="nintendo-btn" style="background:linear-gradient(45deg,#e74c3c,#c0392b);">確認取消訂閱</button>
                  <button @click="navigateTo('game')" class="nintendo-btn secondary" style="margin-left:10px;">返回</button>
                </div>
            </div>

       
            <div v-if="showCreateNote" class="modal-overlay" @click="showCreateNote = false">
                <div class="modal-content" @click.stop>
                    <h3>{{ isEditingNote ? '編輯筆記' : '建立新筆記' }}</h3>
                    <input v-model="newNote.title" placeholder="筆記標題" class="nintendo-input" style="margin: 10px 0;">
                    <div class="note-editor">
                        <textarea v-model="newNote.content" placeholder="使用Markdown語法撰寫筆記..."></textarea>
                    </div>
                    <div class="note-preview" v-html="renderMarkdown(newNote.content)"></div>
                    <div class="modal-buttons">
                        <button @click="saveNote" class="nintendo-btn modal-save-btn">{{ isEditingNote ? '儲存變更' : '儲存' }}</button>
                        <button @click="showCreateNote = false; isEditingNote = false; editingNoteId = null; newNote = { title: '', content: '', tags: [] }" class="nintendo-btn secondary modal-cancel-btn">取消</button>
                    </div>
                </div>
            </div>

         
            <div v-if="notification.show" class="notification" :class="notification.type">
                {{ notification.message }}
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html> 