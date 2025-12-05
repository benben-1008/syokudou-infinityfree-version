// 予約システムのJavaScript

// 予約時間チェック機能
async function checkReservationTime() {
    try {
        const response = await fetch('api/reservation-times.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const times = await response.json();
        const timeDisplay = document.getElementById('time-display');

        if (!timeDisplay) {
            console.error('time-display要素が見つかりません');
            return true;
        }

        if (!times || !times.enabled) {
            timeDisplay.innerHTML = '<p style="color: #28a745;">✅ 予約時間制限なし（いつでも予約可能）</p>';
            return true;
        }

        const now = new Date();
        const currentTime = now.toTimeString().slice(0, 5); // HH:MM形式
        const startTime = times.startTime;
        const endTime = times.endTime;

        const isWithinTime = currentTime >= startTime && currentTime <= endTime;

        if (isWithinTime) {
            timeDisplay.innerHTML = `
                <p style="color: #28a745;">✅ 現在予約可能です</p>
                <p style="font-size: 14px; color: #6c757d;">予約時間: ${startTime} - ${endTime}</p>
                <p style="font-size: 14px; color: #6c757d;">現在時刻: ${currentTime}</p>
            `;
        } else {
            timeDisplay.innerHTML = `
                <p style="color: #dc3545;">❌ 現在は予約時間外です</p>
                <p style="font-size: 14px; color: #6c757d;">予約時間: ${startTime} - ${endTime}</p>
                <p style="font-size: 14px; color: #6c757d;">現在時刻: ${currentTime}</p>
                <p style="font-size: 14px; color: #6c757d;">${times.message || ''}</p>
            `;
        }

        return isWithinTime;
    } catch (error) {
        console.error('予約時間チェックエラー:', error);
        const timeDisplay = document.getElementById('time-display');
        if (timeDisplay) {
            timeDisplay.innerHTML = '<p style="color: #dc3545;">❌ 予約時間の確認に失敗しました</p>';
        }
        return true; // エラー時は予約を許可
    }
}

// ページリンクの表示
function renderLinks() {
    const base = window.location.href.replace(/[^/]*$/, '');
    const pages = [
        { name: '生徒用サイト (index.html)', file: 'index.html' },
        { name: '予約サイト (reservation.html)', file: 'reservation.html' },
        { name: '予約確認システム (verification.html)', file: 'verification.html' },
        { name: '食堂専用AIアシスタント (ai-assistant-php.html)', file: 'ai-assistant-php.html' }
    ];
    const linksDiv = document.getElementById('page-links');
    if (!linksDiv) return;
    linksDiv.innerHTML = pages.map(p => {
        const url = base + p.file;
        return `<p><strong>${p.name}:</strong> <a href="${url}">${url}</a></p>`;
    }).join('');
}

// ページ読み込み時に実行
document.addEventListener('DOMContentLoaded', function() {
    console.log('PHPページが読み込まれました');
    
    // 予約時間チェックを実行（エラー時も続行）
    if (typeof checkReservationTime === 'function') {
        checkReservationTime().catch(error => {
            console.warn('予約時間チェックに失敗しました（続行）:', error);
        });
    }
    
    // ページがフォーカスされた時に更新（PHPページを再読み込み）
    // 注意: 無限ループを防ぐため、短時間内の再読み込みは無視
    let lastReloadTime = 0;
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            const now = Date.now();
            // 前回の再読み込みから5秒以上経過している場合のみ実行
            if (now - lastReloadTime > 5000) {
                console.log('ページがフォーカスされました。ページを更新します。');
                lastReloadTime = now;
                location.reload();
            }
        }
    });

    // 5分ごとにページを自動更新（オプション - コメントアウト可能）
    // setInterval(() => {
    //     console.log('定期更新: ページを再読み込みします');
    //     location.reload();
    // }, 300000); // 5分 = 300000ms
    
    // フォーム送信処理を設定
    const form = document.getElementById('reservation-form');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                name: document.getElementById('student-name').value,
                food: document.getElementById('food').value
            };
            
            // バリデーション
            if (!validateForm(formData)) {
                return;
            }
            
            // 送信ボタンを無効化して重複送信を防ぐ
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = '送信中...';
            
            try {
                const reservation = await submitReservation(formData);
                alert(`予約が完了しました！\n予約番号: ${reservation.reservationNumber}\n\nこの番号を大切に保管してください。`);
                // 予約完了後はページを再読み込みして最新データを表示
                location.reload();
            } catch (error) {
                console.error('予約の送信に失敗しました:', error);
                alert('予約の送信に失敗しました: ' + error.message);
            } finally {
                // 送信ボタンを元に戻す
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        });
    }
});

// これらの関数はPHPで処理されるため、JavaScriptでは不要


// フォームバリデーション
function validateForm(data) {
    if (!data.name.trim()) {
        alert('お名前を入力してください。');
        return false;
    }
    
    if (!data.food) {
        alert('メニューを選択してください。');
        return false;
    }
    
    return true;
}

// 予約を送信
async function submitReservation(formData) {
    // まずメニューデータを取得して残数をチェック・更新
    const menuResponse = await fetch('api/menu.php');
    if (!menuResponse.ok) {
        throw new Error('メニューデータの取得に失敗しました');
    }
    
    const menus = await menuResponse.json();
    const selectedMenu = menus.find(menu => menu.name === formData.food);
    
    if (!selectedMenu) {
        throw new Error('選択されたメニューが見つかりません');
    }
    
    // 残数チェック（無制限でない場合）
    if (selectedMenu.stock !== -1 && selectedMenu.stock <= 0) {
        throw new Error('このメニューは売り切れです');
    }
    
    // 残数を減らす（無制限でない場合）
    if (selectedMenu.stock !== -1) {
        selectedMenu.stock -= 1;
        
        // メニューデータを更新
        const updateResponse = await fetch('api/menu.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(menus)
        });
        
        if (!updateResponse.ok) {
            throw new Error('メニューの残数更新に失敗しました');
        }
    }
    
    // ユーザーIDを取得（ログインしている場合）
    let userId = null;
    try {
        const authResponse = await fetch('api/auth.php', {
            method: 'GET',
            credentials: 'include'
        });
        const authData = await authResponse.json();
        if (authData.loggedIn && authData.user) {
            userId = authData.user.id;
        }
    } catch (error) {
        console.error('ユーザー情報の取得に失敗:', error);
    }
    
    // 予約データを作成
    const reservation = {
        id: Date.now(),
        date: new Date().toISOString().split('T')[0],
        time: new Date().toTimeString().split(' ')[0].substring(0, 5),
        name: formData.name,
        people: 1, // 固定で1人
        food: formData.food,
        reservationNumber: Math.floor(Math.random() * 999) + 1, // 1-999のランダム番号
        userId: userId // ログインしている場合のみ設定
    };
    
    // 既存の予約を取得
    const existingReservations = await fetch('api/reservations.php').then(r => r.json());
    existingReservations.push(reservation);
    
    // 予約を保存
    const saveResponse = await fetch('api/reservations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(existingReservations)
    });
    
    if (!saveResponse.ok) {
        throw new Error('予約の保存に失敗しました');
    }
    
    console.log('予約が送信されました:', reservation);
    return reservation;
}

// メニュー一覧はPHPで表示されるため、JavaScriptでは不要

// データを手動で更新（PHPページを再読み込み）
function refreshData() {
    console.log('手動でデータを更新します（ページ再読み込み）');
    
    // ボタンを一時的に無効化
    const refreshButton = document.querySelector('button[onclick="refreshData()"]');
    if (refreshButton) {
        refreshButton.disabled = true;
        refreshButton.textContent = '🔄 更新中...';
    }
    
    // ページを再読み込み
    location.reload();
}

// フォームをリセット
function resetForm() {
    document.getElementById('reservation-form').reset();
}