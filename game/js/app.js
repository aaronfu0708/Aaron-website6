
const { createApp } = Vue;

createApp({
    data() {
        const data = {
            loading: true,
            gameLoading: false,
            isLoggedIn: false,
            isRegistering: false,
            user: null,
            currentView: 'game',
            
            // 認證表單
            authForm: {
                username: '',
                email: '',
                password: ''
            },
            
            // 遊戲相關
            selectedTopic: '',
            selectedDifficulty: '初級',
            questionCount: 10,
            difficulties: ['初級', '中級', '高級', '地獄'],
            topics: [],
            popularTopics: [],
            familiarity: [],
            
            // 答題相關
            questions: [],
            currentQuestionIndex: 0,
            selectedAnswer: '',
            answerSubmitted: false,
            isCorrect: false,
            answerStartTime: null,
            
            // 新的答題流程
            questionResults: [],
            correctCount: 0,
            

            
            // 筆記相關
            wrongQuestions: [],
            notes: [],
            showCreateNote: false,
            isEditingNote: false,
            editingNoteId: null,
            newNote: {
                title: '',
                content: '',
                tags: []
            },
            
            // 排行榜
            leaderboard: [],
            
            // 通知
            notification: {
                show: false,
                message: '',
                type: 'success'
            },
            
            // 漢堡選單
            showMenu: false,
            adminUsers: [],
            adminUsersLoading: false,
            subscribePlan: 'month', // 新增，預設月訂閱
        };
        console.log('adminUsers in data:', data.adminUsers);
        return data;
    },
    
    computed: {
        currentQuestion() {
            return this.questions[this.currentQuestionIndex] || null;
        }
    },
    
    async mounted() {
        await this.checkSession();
        this.loading = false;
    },
    
    methods: {
        // 認證相關
        async checkSession() {
            try {
                const response = await fetch('api/auth.php?action=check_session');
                const data = await response.json();
                
                if (data.logged_in) {
                    this.isLoggedIn = true;
                    this.user = data.user;
                    await this.loadGameData();
                }
            } catch (error) {
                console.error('檢查會話失敗:', error);
            } finally {
                // 確保無論成功或失敗都會關閉載入畫面
                this.loading = false;
            }
        },
        
        async handleAuth() {
            try {
                const action = this.isRegistering ? 'register' : 'login';
                const response = await fetch(`api/auth.php?action=${action}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(this.authForm)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (action === 'login') {
                        this.isLoggedIn = true;
                        this.user = data.user;
                        this.showMenu = false; // 確保登入後選單是關閉的
                        await this.loadGameData();
                        this.showNotification('登入成功！', 'success');
                    } else {
                        this.showNotification('註冊成功！請登入', 'success');
                        this.isRegistering = false;
                        this.authForm = { username: '', email: '', password: '' };
                    }
                } else {
                    this.showNotification(data.error, 'error');
                }
            } catch (error) {
                this.showNotification('網路錯誤', 'error');
            }
        },
        
        toggleAuthMode() {
            this.isRegistering = !this.isRegistering;
            this.authForm = { username: '', email: '', password: '' };
        },
        
        async logout() {
            try {
                await fetch('api/auth.php?action=logout');
                this.isLoggedIn = false;
                this.user = null;
                this.currentView = 'game';
                this.showNotification('登出成功', 'success');
            } catch (error) {
                this.showNotification('登出失敗', 'error');
            }
        },
        
        // 遊戲資料載入
        async loadGameData() {
            await Promise.all([
                this.loadTopics(),
                this.loadFamiliarity(),
                this.loadWrongQuestions(),
                this.loadNotes()
            ]);
        },
        
        async loadTopics() {
            try {
                const response = await fetch('api/questions.php?action=get_topics');
                const data = await response.json();
                if (data.success) {
                    this.topics = data.topics;
                    this.popularTopics = data.popular_topics;
                }
            } catch (error) {
                console.error('載入主題失敗:', error);
            }
        },
        
        async loadFamiliarity() {
            try {
                const response = await fetch('api/questions.php?action=get_familiarity');
                const data = await response.json();
                if (data.success) {
                    // 確保 familiarity_percentage 是數字
                    this.familiarity = data.familiarity.map(item => ({
                        ...item,
                        familiarity_percentage: parseFloat(item.familiarity_percentage || 0)
                    }));
                }
            } catch (error) {
                console.error('載入熟悉度失敗:', error);
            }
        },
        

        
        async loadWrongQuestions() {
            try {
                const response = await fetch('api/notes.php?action=get_wrong_questions');
                const data = await response.json();
                if (data.success) {
                    this.wrongQuestions = data.wrong_questions;
                }
            } catch (error) {
                console.error('載入錯題失敗:', error);
            }
        },
        
        async loadNotes() {
            if (!this.user.is_subscribed) return;
            
            try {
                const response = await fetch('api/notes.php?action=get_notes');
                const data = await response.json();
                if (data.success) {
                    this.notes = data.notes;
                } else if (data.error) {
                    // 忽略非訂閱用戶的錯誤
                    console.log('筆記功能僅限訂閱用戶');
                }
            } catch (error) {
                console.error('載入筆記失敗:', error);
            }
        },
        
        // 遊戲邏輯
        async startGame() {
            if (!this.selectedTopic) {
                this.showNotification('請選擇主題', 'error');
                return;
            }
            
            // 顯示載入動畫
            this.gameLoading = true;
            
            try {
                const response = await fetch('api/questions.php?action=generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        topic: this.selectedTopic,
                        difficulty: this.selectedDifficulty,
                        question_count: this.questionCount
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.questions = data.questions;
                    this.currentQuestionIndex = 0;
                    this.selectedAnswer = '';
                    this.questionResults = [];
                    this.correctCount = 0;
                    this.currentView = 'quiz';
                    this.answerStartTime = Date.now();
                    this.showNotification('題目生成成功！', 'success');
                } else {
                    this.showNotification(data.error, 'error');
                }
            } catch (error) {
                this.showNotification('生成題目失敗', 'error');
            } finally {
                // 隱藏載入動畫
                this.gameLoading = false;
            }
        },
        
        selectAnswer(answer) {
            this.selectedAnswer = answer;
        },
        
        async submitAnswer() {
            if (!this.selectedAnswer) {
                this.showNotification('請選擇答案', 'error');
                return;
            }
            
            const answerTime = Math.floor((Date.now() - this.answerStartTime) / 1000);
            const isCorrect = this.selectedAnswer === this.currentQuestion.correct_answer;
            
            try {
                await fetch('api/questions.php?action=submit_answer', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        question_id: this.currentQuestion.id,
                        user_answer: this.selectedAnswer,
                        answer_time: answerTime,
                        is_correct: isCorrect
                    })
                });
                
                this.answerSubmitted = true;
                this.isCorrect = isCorrect;
                
                // 更新熟悉度
                await this.loadFamiliarity();
                
            } catch (error) {
                this.showNotification('提交答案失敗', 'error');
            }
        },
        
        async nextQuestion() {
            if (!this.selectedAnswer) {
                this.showNotification('請選擇答案', 'error');
                return;
            }
            
            // 記錄當前題目的結果
            const isCorrect = this.selectedAnswer === this.currentQuestion.correct_answer;
            if (isCorrect) {
                this.correctCount++;
            }
            
            this.questionResults.push({
                question: this.currentQuestion.question,
                userAnswer: this.selectedAnswer,
                correctAnswer: this.currentQuestion.correct_answer,
                explanation: this.currentQuestion.explanation,
                isCorrect: isCorrect
            });
            
            if (this.currentQuestionIndex + 1 < this.questions.length) {
                // 下一題
                this.currentQuestionIndex++;
                this.selectedAnswer = '';
            } else {
                // 全部答完，顯示結果
                await this.submitAllAnswers();
                this.currentView = 'results';
            }
        },
        
        async submitAllAnswers() {
            try {
                // 批量提交所有答案
                for (let i = 0; i < this.questionResults.length; i++) {
                    const result = this.questionResults[i];
                    const question = this.questions[i];
                    const answerTime = Math.floor((Date.now() - this.answerStartTime) / 1000);
                    
                    await fetch('api/questions.php?action=submit_answer', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            question_id: question.id,
                            user_answer: result.userAnswer,
                            answer_time: answerTime,
                            is_correct: result.isCorrect
                        })
                    });
                }
                
                // 更新熟悉度
                await this.loadFamiliarity();
                
            } catch (error) {
                this.showNotification('提交答案失敗', 'error');
            }
        },
        
        async addToWrongQuestions(result) {
            try {
                // 找到對應的題目ID
                const questionIndex = this.questionResults.findIndex(r => 
                    r.question === result.question && 
                    r.userAnswer === result.userAnswer
                );
                
                if (questionIndex !== -1) {
                    const question = this.questions[questionIndex];
                    
                    const response = await fetch('api/notes.php?action=add_wrong_question', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            question_id: question.id,
                            user_answer: result.userAnswer
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.showNotification('已加入收藏', 'success');
                        await this.loadWrongQuestions();
                    } else {
                        this.showNotification(data.error, 'error');
                    }
                }
            } catch (error) {
                this.showNotification('加入收藏失敗', 'error');
            }
        },
        
        backToGame() {
            this.currentView = 'game';
            this.loadGameData();
        },
        
        // 角色相關

        

        
        // 筆記相關
        async toggleStar(wrongQuestionId) {
            try {
                const response = await fetch('api/notes.php?action=toggle_star', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        wrong_question_id: wrongQuestionId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await this.loadWrongQuestions();
                    this.showNotification(data.is_starred ? '已加入星標' : '已移除星標', 'success');
                } else {
                    this.showNotification(data.error, 'error');
                }
            } catch (error) {
                this.showNotification('操作失敗', 'error');
            }
        },
        
        async deleteWrongQuestion(wrongQuestionId) {
            if (!confirm('確定要刪除此收藏題目嗎？')) return;
            
            try {
                const response = await fetch('api/notes.php?action=delete_wrong_question', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        wrong_question_id: wrongQuestionId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await this.loadWrongQuestions();
                    this.showNotification('收藏題目已刪除', 'success');
                } else {
                    this.showNotification(data.error, 'error');
                }
            } catch (error) {
                this.showNotification('刪除失敗', 'error');
            }
        },
        
        createNoteFromQuestion(question) {
            this.newNote = {
                title: `錯題筆記 - ${question.topic_name}`,
                content: `## 題目\n${question.question_text}\n\n## 我的答案\n${question.user_answer}\n\n## 正確答案\n${question.correct_answer}\n\n## 解析\n${question.explanation}\n\n## 我的筆記\n`,
                tags: [question.topic_name, '錯題']
            };
            this.showCreateNote = true;
        },
        
        editNote(note) {
            this.isEditingNote = true;
            this.editingNoteId = note.id;
            this.newNote = {
                title: note.title,
                content: note.content,
                tags: Array.isArray(note.tags) ? note.tags : (note.tags ? JSON.parse(note.tags) : [])
            };
            this.showCreateNote = true;
        },
        
        async saveNote() {
            if (!this.newNote.title || !this.newNote.content) {
                this.showNotification('請填寫標題和內容', 'error');
                return;
            }
            try {
                if (this.isEditingNote && this.editingNoteId) {
                    // 編輯模式
                    const response = await fetch('api/notes.php?action=update_note', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            note_id: this.editingNoteId,
                            title: this.newNote.title,
                            content: this.newNote.content,
                            tags: this.newNote.tags
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.showCreateNote = false;
                        this.isEditingNote = false;
                        this.editingNoteId = null;
                        this.newNote = { title: '', content: '', tags: [] };
                        await this.loadNotes();
                        this.showNotification('筆記更新成功！', 'success');
                    } else {
                        this.showNotification(data.error, 'error');
                    }
                } else {
                    // 建立模式
                    const response = await fetch('api/notes.php?action=create_note', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(this.newNote)
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.showCreateNote = false;
                        this.newNote = { title: '', content: '', tags: [] };
                        await this.loadNotes();
                        this.showNotification('筆記儲存成功！', 'success');
                    } else {
                        this.showNotification(data.error, 'error');
                    }
                }
            } catch (error) {
                this.showNotification('儲存筆記失敗', 'error');
            }
        },
        
        async deleteNote(noteId) {
            if (!confirm('確定要刪除此筆記嗎？')) return;
            
            try {
                const response = await fetch('api/notes.php?action=delete_note', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        note_id: noteId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await this.loadNotes();
                    this.showNotification('筆記刪除成功', 'success');
                } else {
                    this.showNotification(data.error, 'error');
                }
            } catch (error) {
                this.showNotification('刪除筆記失敗', 'error');
            }
        },
        
        // 排行榜
        async loadLeaderboard(topicId = null) {
            try {
                // 處理空字串的情況（總排行榜）
                if (topicId === '') {
                    topicId = null;
                }
                
                const url = topicId 
                    ? `api/leaderboard.php?action=get_leaderboard&topic_id=${topicId}`
                    : 'api/leaderboard.php?action=get_leaderboard';
                    
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    this.leaderboard = data.leaderboard;
                } else {
                    this.showNotification(data.error, 'error');
                }
            } catch (error) {
                this.showNotification('載入排行榜失敗', 'error');
            }
        },
        
        // 工具方法
        renderMarkdown(content) {
            return marked.parse(content || '');
        },
        
        showNotification(message, type = 'success') {
            this.notification = {
                show: true,
                message,
                type
            };
            
            // 統一設定為0.3秒
            setTimeout(() => {
                this.notification.show = false;
            }, 500);
        },
        
        // 漢堡選單方法
        toggleMenu() {
            this.showMenu = !this.showMenu;
        },
        
        async navigateTo(view) {
            this.currentView = view;
            this.showMenu = false; // 關閉選單
            
            // 如果切換到排行榜，自動載入排行榜資料
            if (view === 'leaderboard') {
                await this.loadLeaderboard();
            }
            if(view === 'adminUsers') {
                this.loadAdminUsers();
            }
            if(view === 'subscribe') {
                this.subscribePlan = 'month'; // 預設月訂閱
                this.currentView = 'subscribe';
            }
            if(view === 'unsubscribe') {
                this.subscribePlan = 'month'; // 預設月訂閱
                this.currentView = 'unsubscribe';
            }
        },
        async loadAdminUsers() {
            this.adminUsersLoading = true;
            try {
                const res = await fetch('api/users.php?action=list');
                const data = await res.json();
                if(data.success) {
                    this.adminUsers = data.users;
                } else {
                    this.adminUsers = [];
                    alert(data.error || '載入失敗');
                }
            } catch(e) {
                this.adminUsers = [];
                alert('載入失敗');
            }
            this.adminUsersLoading = false;
        },
        async adminUpgradeUser(userId) {
            if(!confirm('確定要升級為訂閱用戶？')) return;
            await fetch('api/users.php?action=upgrade', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            });
            this.loadAdminUsers();
        },
        async adminDowngradeUser(userId) {
            if(!confirm('確定要降級為一般用戶？')) return;
            await fetch('api/users.php?action=downgrade', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            });
            this.loadAdminUsers();
        },
        async adminDeleteUser(userId) {
            if(!confirm('確定要刪除此用戶？')) return;
            await fetch('api/users.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            });
            this.loadAdminUsers();
        },
        subscribeUser() {
            if (!this.user) return;
            this.user.is_subscribed = true;
            this.user.subscription_type = this.subscribePlan;
            this.showNotification(`訂閱成功！您已成為月訂閱用戶。`, 'success');
            this.navigateTo('game');
        },
        unsubscribeUser() {
            if (!this.user) return;
            this.user.is_subscribed = false;
            this.user.subscription_type = '';
            this.showNotification('已取消訂閱，您已回復為一般用戶。', 'info');
            this.navigateTo('game');
        },
    }
}).mount('#app'); 