<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataDir = __DIR__ . '/../data';
$today = date('Y-m-d');

// データファイルのパス
$menuFile = $dataDir . '/menu.json';
$reservationsFile = $dataDir . '/reservations.json';
$dailyMenuFile = $dataDir . '/daily-menu.json';
$salesFile = $dataDir . '/sales.json';
$lastResetFile = $dataDir . '/last-reset.json';

// データディレクトリの作成
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

function readJsonSafe($file) {
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    if ($content === false || $content === '') return [];
    $json = json_decode($content, true);
    return is_array($json) ? $json : [];
}

function writeJsonSafe($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 日次リセット処理
function performDailyReset() {
    global $dataDir, $today, $menuFile, $reservationsFile, $dailyMenuFile, $salesFile, $lastResetFile;
    
    // 前日の売上データを保存
    savePreviousDaySales();
    
    // 予約データをクリア
    writeJsonSafe($reservationsFile, []);
    
    // メニューの残数をリセット（デフォルト値に戻す）
    resetMenuQuantities();
    
    // 今日のメニューを設定
    setTodayMenu();
    
    // リセット日時を記録
    writeJsonSafe($lastResetFile, [
        'lastReset' => $today,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    return true;
}

// 前日の売上データを保存
function savePreviousDaySales() {
    global $dataDir, $today, $reservationsFile, $salesFile;
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $reservations = readJsonSafe($reservationsFile);
    
    // 前日の予約データを集計
    $yesterdayReservations = array_filter($reservations, function($reservation) use ($yesterday) {
        return isset($reservation['date']) && $reservation['date'] === $yesterday;
    });
    
    // 売上データを計算
    $salesData = [
        'date' => $yesterday,
        'totalReservations' => count($yesterdayReservations),
        'totalPeople' => array_sum(array_column($yesterdayReservations, 'people')),
        'menuSales' => [],
        'timeSlots' => []
    ];
    
    // メニュー別売上
    foreach ($yesterdayReservations as $reservation) {
        $food = $reservation['food'] ?? '未指定';
        if (!isset($salesData['menuSales'][$food])) {
            $salesData['menuSales'][$food] = 0;
        }
        $salesData['menuSales'][$food] += $reservation['people'];
    }
    
    // 時間帯別売上
    foreach ($yesterdayReservations as $reservation) {
        $time = $reservation['time'] ?? '未指定';
        if (!isset($salesData['timeSlots'][$time])) {
            $salesData['timeSlots'][$time] = 0;
        }
        $salesData['timeSlots'][$time] += $reservation['people'];
    }
    
    // 売上データを保存
    $allSales = readJsonSafe($salesFile);
    $allSales[] = $salesData;
    writeJsonSafe($salesFile, $allSales);
}

// メニューの残数をリセット
function resetMenuQuantities() {
    global $dataDir;
    
    $menuQuantitiesFile = $dataDir . '/menu-quantities.json';
    $defaultQuantities = [
        'カレーライス' => 50,
        'ハンバーグ定食' => 30,
        '唐揚げ定食' => 40,
        '魚の煮付け定食' => 25,
        'ラーメン' => 60,
        'うどん' => 50,
        'そば' => 45,
        'サラダ' => 100,
        '味噌汁' => 100,
        'ご飯' => 200
    ];
    
    writeJsonSafe($menuQuantitiesFile, $defaultQuantities);
}

// 今日のメニューを設定
function setTodayMenu() {
    global $dataDir, $today, $dailyMenuFile;
    
    $menuOptions = [
        'カレーライス',
        'ハンバーグ定食',
        '唐揚げ定食',
        '魚の煮付け定食',
        'ラーメン',
        'うどん',
        'そば'
    ];
    
    // ランダムに今日のメニューを選択
    $todayMenu = $menuOptions[array_rand($menuOptions)];
    
    $dailyMenus = readJsonSafe($dailyMenuFile);
    
    // 今日のメニューを更新または追加
    $found = false;
    foreach ($dailyMenus as &$menu) {
        if ($menu['date'] === $today) {
            $menu['food'] = $todayMenu;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $dailyMenus[] = [
            'date' => $today,
            'food' => $todayMenu
        ];
    }
    
    writeJsonSafe($dailyMenuFile, $dailyMenus);
}

// リセットが必要かチェック
function needsReset() {
    global $lastResetFile, $today;
    
    $lastReset = readJsonSafe($lastResetFile);
    $lastResetDate = $lastReset['lastReset'] ?? null;
    
    return $lastResetDate !== $today;
}

// API処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 手動リセット
    if (performDailyReset()) {
        echo json_encode([
            'success' => true,
            'message' => '日次リセットが完了しました',
            'date' => $today
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'リセット処理中にエラーが発生しました'
        ], JSON_UNESCAPED_UNICODE);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // リセット状況を確認
    $needsReset = needsReset();
    $lastReset = readJsonSafe($lastResetFile);
    
    echo json_encode([
        'needsReset' => $needsReset,
        'lastReset' => $lastReset['lastReset'] ?? null,
        'lastResetTime' => $lastReset['timestamp'] ?? null,
        'today' => $today
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
}
?>
