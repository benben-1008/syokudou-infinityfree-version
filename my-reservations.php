<?php
session_start();

// HTTPヘッダーの設定（セキュリティとパフォーマンス）
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, max-age=0');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイ予約履歴 - 食堂予約システム</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .page-bg {
            background-image: url('images/olive.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .container {
            background-color: rgba(255, 255, 255, 0.92);
        }

        .login-section {
            margin: 20px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            text-align: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e8f5e9;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid #4caf50;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-size: 18px;
            font-weight: bold;
            color: #2e7d32;
            margin: 0;
        }

        .user-email {
            font-size: 14px;
            color: #666;
            margin: 5px 0 0 0;
        }

        .logout-btn {
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .logout-btn:hover {
            background-color: #c82333;
        }

        .reservation-item {
            margin-bottom: 15px;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .reservation-date {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .reservation-number {
            font-size: 14px;
            color: #666;
            background-color: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .reservation-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }

        .detail-item {
            font-size: 14px;
        }

        .detail-label {
            font-weight: bold;
            color: #666;
        }

        .no-reservations {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body class="page-bg">
    <div class="container">
        <header>
            <h1>📋 マイ予約履歴</h1>
            <p>あなたの予約履歴を確認できます</p>
        </header>

        <div id="login-section" class="login-section" style="display: none;">
            <h3>ログインが必要です</h3>
            <p>予約履歴を確認するには、Googleアカウントでログインしてください。</p>
            <div id="g_id_onload"
                data-client_id="YOUR_GOOGLE_CLIENT_ID"
                data-callback="handleCredentialResponse"
                data-auto_prompt="false">
            </div>
            <div class="g_id_signin" 
                data-type="standard"
                data-size="large"
                data-theme="outline"
                data-text="sign_in_with"
                data-shape="rectangular"
                data-logo_alignment="left">
            </div>
        </div>

        <div id="user-section" style="display: none;">
            <div class="user-info">
                <img id="user-avatar" class="user-avatar" src="" alt="ユーザー画像">
                <div class="user-details">
                    <p class="user-name" id="user-name"></p>
                    <p class="user-email" id="user-email"></p>
                </div>
                <button class="logout-btn" onclick="logout()">ログアウト</button>
            </div>

            <section class="info-section">
                <h2>📝 予約履歴</h2>
                <div id="reservations-list" class="info-card">
                    <div class="loading">読み込み中...</div>
                </div>
            </section>
        </div>

        <footer style="margin-top: 30px;">
            <a href="reservation.php" class="back-link">← 予約ページに戻る</a>
            <a href="index.html" class="back-link" style="margin-left: 20px;">← メインページに戻る</a>
        </footer>
    </div>

    <!-- Google Sign-In API -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    
    <script>
        // Google Client ID（実際の値に置き換えてください）
        const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID';

        // ページ読み込み時にログイン状態を確認
        async function checkLoginStatus() {
            try {
                const response = await fetch('api/auth.php', {
                    method: 'GET',
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.loggedIn) {
                    showUserSection(data.user);
                    loadReservations();
                } else {
                    showLoginSection();
                }
            } catch (error) {
                console.error('ログイン状態の確認に失敗:', error);
                showLoginSection();
            }
        }

        // ログインセクションを表示
        function showLoginSection() {
            document.getElementById('login-section').style.display = 'block';
            document.getElementById('user-section').style.display = 'none';
        }

        // ユーザーセクションを表示
        function showUserSection(user) {
            document.getElementById('login-section').style.display = 'none';
            document.getElementById('user-section').style.display = 'block';
            
            document.getElementById('user-name').textContent = user.name;
            document.getElementById('user-email').textContent = user.email;
            if (user.picture) {
                document.getElementById('user-avatar').src = user.picture;
            }
        }

        // Google Sign-In コールバック
        async function handleCredentialResponse(response) {
            try {
                // IDトークンをデコード（簡易版）
                const payload = JSON.parse(atob(response.credential.split('.')[1]));
                
                const loginResponse = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        idToken: response.credential,
                        userInfo: {
                            sub: payload.sub,
                            email: payload.email,
                            name: payload.name,
                            given_name: payload.given_name,
                            picture: payload.picture
                        }
                    })
                });

                const data = await loginResponse.json();
                
                if (data.success) {
                    showUserSection(data.user);
                    loadReservations();
                } else {
                    alert('ログインに失敗しました: ' + (data.error || '不明なエラー'));
                }
            } catch (error) {
                console.error('ログインエラー:', error);
                alert('ログインに失敗しました。');
            }
        }

        // ログアウト
        async function logout() {
            try {
                await fetch('api/auth.php', {
                    method: 'DELETE',
                    credentials: 'include'
                });
                
                showLoginSection();
                document.getElementById('reservations-list').innerHTML = '<div class="loading">読み込み中...</div>';
            } catch (error) {
                console.error('ログアウトエラー:', error);
            }
        }

        // 予約履歴を読み込み
        async function loadReservations() {
            const listElement = document.getElementById('reservations-list');
            
            try {
                const response = await fetch('api/user-reservations.php', {
                    method: 'GET',
                    credentials: 'include'
                });
                
                if (response.status === 401) {
                    showLoginSection();
                    return;
                }
                
                const reservations = await response.json();
                
                if (reservations.length === 0) {
                    listElement.innerHTML = '<div class="no-reservations">予約履歴がありません。</div>';
                    return;
                }
                
                listElement.innerHTML = reservations.map(reservation => {
                    const date = reservation.date || '不明';
                    const time = reservation.time || '不明';
                    const food = reservation.food || '不明';
                    const number = reservation.reservationNumber || 'なし';
                    
                    return `
                        <div class="reservation-item">
                            <div class="reservation-header">
                                <span class="reservation-date">${date} ${time}</span>
                                <span class="reservation-number">予約番号: ${number}</span>
                            </div>
                            <div class="reservation-details">
                                <div class="detail-item">
                                    <span class="detail-label">メニュー:</span> ${food}
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">人数:</span> ${reservation.people || 1}人
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (error) {
                console.error('予約履歴の読み込みに失敗:', error);
                listElement.innerHTML = '<div class="no-reservations">予約履歴の読み込みに失敗しました。</div>';
            }
        }

        // ページ読み込み時に実行
        document.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus();
        });
    </script>
</body>
</html>

