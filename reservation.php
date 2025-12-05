<?php
// メニューデータを読み込み
function loadMenus() {
    $menuFile = 'data/menu.json';
    if (file_exists($menuFile)) {
        $json = file_get_contents($menuFile);
        if ($json === false) {
            error_log("メニューファイルの読み込みに失敗: " . $menuFile);
            return [];
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON解析エラー: " . json_last_error_msg());
            return [];
        }
        return $data ?: [];
    }
    error_log("メニューファイルが存在しません: " . $menuFile);
    return [];
}

// 予約データを読み込み
function loadReservations() {
    $reservationFile = 'data/reservations.json';
    if (file_exists($reservationFile)) {
        $json = file_get_contents($reservationFile);
        if ($json === false) {
            error_log("予約ファイルの読み込みに失敗: " . $reservationFile);
            return [];
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("予約JSON解析エラー: " . json_last_error_msg());
            return [];
        }
        return $data ?: [];
    }
    return [];
}

// 予約時間設定を読み込み
function loadReservationTimes() {
    $timesFile = 'data/reservation-times.json';
    if (file_exists($timesFile)) {
        $json = file_get_contents($timesFile);
        if ($json === false) {
            error_log("予約時間ファイルの読み込みに失敗: " . $timesFile);
            return null;
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("予約時間JSON解析エラー: " . json_last_error_msg());
            return null;
        }
        return $data ?: null;
    }
    return null;
}

// データを取得
$menus = loadMenus();
$reservations = loadReservations();
$reservationTimes = loadReservationTimes();

// 現在時刻を取得
$currentTime = date('H:i');
$isWithinTime = true;
$timeMessage = '';

if ($reservationTimes && $reservationTimes['enabled']) {
    $startTime = $reservationTimes['startTime'];
    $endTime = $reservationTimes['endTime'];
    $isWithinTime = $currentTime >= $startTime && $currentTime <= $endTime;
    $timeMessage = $reservationTimes['message'] ?: "予約時間: {$startTime}-{$endTime}";
}

// HTTPヘッダーの設定（セキュリティとパフォーマンス）
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, max-age=0');
?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>食堂予約システム</title>
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
  </style>
</head>

<body class="page-bg">
  <div class="container">
    <header>
      <h1>🍽️ 食堂予約システム</h1>
      <p>お名前とご希望のメニューを選択してください</p>
    </header>

    <main>
      <section class="reservation-section">
        <div class="reservation-card">
          <h2>📝 予約フォーム</h2>

          <!-- 予約時間表示 -->
          <div id="reservation-time-info"
            style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
            <h3 style="margin: 0 0 10px 0; color: #495057;">⏰ 予約可能時間</h3>
            <div id="time-display">
              <?php if ($reservationTimes && $reservationTimes['enabled']): ?>
                <?php if ($isWithinTime): ?>
                  <p style="color: #28a745;">✅ 現在予約可能です</p>
                  <p style="font-size: 14px; color: #6c757d;"><?= htmlspecialchars($timeMessage) ?></p>
                  <p style="font-size: 14px; color: #6c757d;">現在時刻: <?= $currentTime ?></p>
                <?php else: ?>
                  <p style="color: #dc3545;">❌ 現在は予約時間外です</p>
                  <p style="font-size: 14px; color: #6c757d;"><?= htmlspecialchars($timeMessage) ?></p>
                  <p style="font-size: 14px; color: #6c757d;">現在時刻: <?= $currentTime ?></p>
                <?php endif; ?>
              <?php else: ?>
                <p style="color: #28a745;">✅ 予約時間制限なし（いつでも予約可能）</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- ログインセクション -->
          <div id="login-section" style="margin-bottom: 20px; padding: 15px; background-color: #e8f5e9; border-radius: 8px; border: 1px solid #c8e6c9; display: none;">
            <h3 style="margin: 0 0 10px 0; color: #2e7d32;">🔐 Googleアカウントでログイン</h3>
            <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">ログインすると、お名前が自動入力され、予約履歴を確認できます。</p>
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

          <!-- ユーザー情報表示 -->
          <div id="user-info-section" style="margin-bottom: 20px; padding: 15px; background-color: #e8f5e9; border-radius: 8px; border: 1px solid #c8e6c9; display: none;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
              <img id="user-avatar-small" src="" alt="ユーザー" style="width: 30px; height: 30px; border-radius: 50%;">
              <span id="user-name-display" style="font-weight: bold; color: #2e7d32;"></span>
              <button onclick="logout()" style="margin-left: auto; padding: 5px 10px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">ログアウト</button>
            </div>
            <a href="my-reservations.php" style="font-size: 14px; color: #2e7d32; text-decoration: none;">📋 予約履歴を確認</a>
          </div>

          <form id="reservation-form">
            <div class="form-group">
              <label for="student-name">お名前 *</label>
              <input type="text" id="student-name" required placeholder="例: 田中太郎">
            </div>

            <div class="form-group">
              <label for="food">ご希望のメニュー *</label>
              <select id="food" required>
                <?php if (empty($menus)): ?>
                  <option disabled>メニューが設定されていません</option>
                <?php else: ?>
                  <?php foreach ($menus as $menu): ?>
                    <?php if ($menu['stock'] === -1): ?>
                      <option value="<?= htmlspecialchars($menu['name']) ?>"><?= htmlspecialchars($menu['name']) ?>（無制限）</option>
                    <?php elseif ($menu['stock'] > 0): ?>
                      <option value="<?= htmlspecialchars($menu['name']) ?>"><?= htmlspecialchars($menu['name']) ?>（残り<?= $menu['stock'] ?>食）</option>
                    <?php else: ?>
                      <option value="<?= htmlspecialchars($menu['name']) ?>" disabled><?= htmlspecialchars($menu['name']) ?>（売り切れ）</option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" <?= !$isWithinTime ? 'disabled' : '' ?>>予約を確定</button>
              <button type="button" onclick="resetForm()" class="btn btn-secondary">リセット</button>
            </div>
          </form>
        </div>
      </section>

      <section class="reservation-status-section">
        <div class="reservation-card">
          <h2>📊 現在の予約状況</h2>
          <div style="margin-bottom: 10px;">
            <button onclick="refreshData()" class="btn btn-secondary">🔄 データを更新</button>
          </div>
          <div id="reservations-display">
            <?php if (empty($reservations)): ?>
              <p>予約はありません。</p>
            <?php else: ?>
              <?php
              // メニュー別にグループ化
              $grouped = [];
              foreach ($reservations as $reservation) {
                if (!isset($grouped[$reservation['food']])) {
                  $grouped[$reservation['food']] = [];
                }
                $grouped[$reservation['food']][] = $reservation;
              }
              
              foreach ($grouped as $food => $people) {
                $totalPeople = array_sum(array_column($people, 'people'));
                echo '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background-color: #f8f9fa;">';
                echo '<strong>' . htmlspecialchars($food) . '</strong>: ' . $totalPeople . '人';
                echo '</div>';
              }
              ?>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="menu-table-section">
        <div class="reservation-card">
          <h2>📋 現在のメニュー一覧</h2>
          <div id="menu-display">
            <?php if (empty($menus)): ?>
              <p>メニューが設定されていません。</p>
            <?php else: ?>
              <table class="menu-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr>
                    <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">メニュー</th>
                    <th style="text-align: center; border-bottom: 1px solid #ddd; padding: 8px;">残数</th>
                    <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">状態</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($menus as $menu): ?>
                    <?php
                    $stockDisplay = $menu['stock'] === -1 ? '無制限' : $menu['stock'] . '食';
                    $statusDisplay = $menu['stock'] === -1 ? '✅ 提供中' : 
                                   ($menu['stock'] > 0 ? '✅ 提供中' : '❌ 売り切れ');
                    $statusColor = $menu['stock'] === -1 || $menu['stock'] > 0 ? '#28a745' : '#dc3545';
                    ?>
                    <tr>
                      <td style="padding: 8px; border-bottom: 1px solid #f0f0f0;"><?= htmlspecialchars($menu['name']) ?></td>
                      <td style="padding: 8px; text-align: center; border-bottom: 1px solid #f0f0f0;"><?= $stockDisplay ?></td>
                      <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; color: <?= $statusColor ?>;"><?= $statusDisplay ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>

    <section class="info-section">
      <h2>🔗 URLリンク</h2>
      <div id="page-links" class="info-card">
        <?php
        $base = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
        $pages = [
          ['name' => '生徒用サイト (index.html)', 'file' => 'index.html'],
          ['name' => '管理者サイト (admin.html)', 'file' => 'admin.html'],
          ['name' => '予約サイト (reservation.php)', 'file' => 'reservation.php'],
          ['name' => 'マイ予約履歴 (my-reservations.php)', 'file' => 'my-reservations.php'],
          ['name' => '予約確認システム (verification.html)', 'file' => 'verification.html'],
          ['name' => '食堂専用AIアシスタント (ai-assistant-php.html)', 'file' => 'ai-assistant-php.html']
        ];
        
        foreach ($pages as $page) {
          $url = $base . '/' . $page['file'];
          echo '<p><strong>' . $page['name'] . ':</strong> <a href="' . $url . '">' . $url . '</a></p>';
        }
        ?>
      </div>

      <div
        style="margin-top: 20px; padding: 15px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
        <h3 style="color: #856404; margin-top: 0;">ℹ️ システム情報</h3>
        <p style="color: #856404; margin-bottom: 10px;">
          このサイトはPHPサーバーで動作しています。
        </p>
        <p style="color: #856404; margin-bottom: 10px;">
          <strong>更新方法:</strong><br>
          1. ページを再読み込み（F5キー）<br>
          2. 「🔄 データを更新」ボタンをクリック
        </p>
        <p style="color: #856404; margin: 0;">
          <strong>最終更新:</strong> <?= date('Y-m-d H:i:s') ?>
        </p>
        <p style="color: #856404; margin: 10px 0 0 0; font-size: 12px;">
          <strong>デバッグ情報:</strong><br>
          メニュー数: <?= count($menus) ?><br>
          予約数: <?= count($reservations) ?><br>
          予約時間設定: <?= $reservationTimes ? 'あり' : 'なし' ?>
        </p>
      </div>
    </section>

    <footer>
      <a href="index.html" class="back-link">← メインページに戻る</a>
    </footer>
  </div>

  <!-- Google Sign-In API（オプション - エラー時もページが表示されるように） -->
  <script>
    // Google Sign-In APIの読み込みを試行（失敗してもページは表示される）
    const googleScript = document.createElement('script');
    googleScript.src = 'https://accounts.google.com/gsi/client';
    googleScript.async = true;
    googleScript.defer = true;
    googleScript.onerror = function() {
      console.warn('Google Sign-In APIの読み込みに失敗しました（ログイン機能は無効になります）');
      // ログインセクションを非表示
      document.addEventListener('DOMContentLoaded', function() {
        const loginSection = document.getElementById('login-section');
        if (loginSection) {
          loginSection.style.display = 'none';
        }
      });
    };
    document.head.appendChild(googleScript);
  </script>
  <script src="reservation-script.js"></script>
  <script>
    // Google Client ID（実際の値に置き換えてください）
    const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID';

    // ページ読み込み時にログイン状態を確認
    async function checkLoginStatus() {
      try {
        // タイムアウトを設定（5秒）
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);
        
        const response = await fetch('api/auth.php', {
          method: 'GET',
          credentials: 'include',
          signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
          throw new Error('認証APIの応答が不正です');
        }
        
        const data = await response.json();
        
        if (data.loggedIn) {
          showUserInfo(data.user);
          // 名前を自動入力
          const nameInput = document.getElementById('student-name');
          if (nameInput) {
            nameInput.value = data.user.name;
          }
        } else {
          showLoginSection();
        }
      } catch (error) {
        if (error.name === 'AbortError') {
          console.warn('ログイン状態の確認がタイムアウトしました（無視して続行）');
        } else {
          console.error('ログイン状態の確認に失敗:', error);
        }
        // エラー時はログインセクションを非表示にして、通常の予約フォームを表示
        document.getElementById('login-section').style.display = 'none';
        document.getElementById('user-info-section').style.display = 'none';
      }
    }

    // ログインセクションを表示
    function showLoginSection() {
      document.getElementById('login-section').style.display = 'block';
      document.getElementById('user-info-section').style.display = 'none';
    }

    // ユーザー情報を表示
    function showUserInfo(user) {
      document.getElementById('login-section').style.display = 'none';
      document.getElementById('user-info-section').style.display = 'block';
      
      document.getElementById('user-name-display').textContent = user.name;
      if (user.picture) {
        document.getElementById('user-avatar-small').src = user.picture;
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
          showUserInfo(data.user);
          // 名前を自動入力
          document.getElementById('student-name').value = data.user.name;
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
        document.getElementById('student-name').value = '';
      } catch (error) {
        console.error('ログアウトエラー:', error);
      }
    }

    // ページ読み込み時に実行
    document.addEventListener('DOMContentLoaded', function() {
      // ログイン状態の確認は非同期で実行（ブロックしない）
      setTimeout(() => {
        checkLoginStatus();
      }, 100);
      
      // 予約時間のチェックも実行
      if (typeof checkReservationTime === 'function') {
        checkReservationTime();
      }
    });
  </script>
</body>

</html>
