<?php
// APIとして呼び出された場合のみヘッダーを送信
if (!defined('SALES_DATA_INTERNAL')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache, max-age=0');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

$dataDir = __DIR__ . '/../data';
$file = $dataDir . '/sales-data.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

if (!file_exists($file)) {
    file_put_contents($file, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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

// 予約を追加（+1する）
function addReservationToSales($date, $food, $people = 1, $verified = false) {
    global $file;
    
    $salesData = readJsonSafe($file);
    
    // 日付が存在しない場合は初期化
    if (!isset($salesData[$date])) {
        $salesData[$date] = [
            'reservations' => 0,
            'people' => 0,
            'menuSales' => []
        ];
    }
    
    // 予約数を+1
    $salesData[$date]['reservations'] += $people;
    
    // 認証済みの場合は来客数も+1
    if ($verified) {
        $salesData[$date]['people'] += $people;
        
        // メニュー別売上を+1
        if ($food) {
            if (!isset($salesData[$date]['menuSales'][$food])) {
                $salesData[$date]['menuSales'][$food] = 0;
            }
            $salesData[$date]['menuSales'][$food] += $people;
        }
    }
    
    writeJsonSafe($file, $salesData);
    return true;
}

// 認証状態を更新（未認証→認証済みに変更）
function updateVerificationStatus($date, $food, $people = 1) {
    global $file;
    
    $salesData = readJsonSafe($file);
    
    if (!isset($salesData[$date])) {
        return false;
    }
    
    // 来客数を+1
    $salesData[$date]['people'] += $people;
    
    // メニュー別売上を+1
    if ($food) {
        if (!isset($salesData[$date]['menuSales'][$food])) {
            $salesData[$date]['menuSales'][$food] = 0;
        }
        $salesData[$date]['menuSales'][$food] += $people;
    }
    
    writeJsonSafe($file, $salesData);
    return true;
}

// APIとして呼び出された場合のみ処理を実行
if (!defined('SALES_DATA_INTERNAL')) {
    switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode(readJsonSafe($file), JSON_UNESCAPED_UNICODE);
        break;
        
    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!is_array($data) || !isset($data['action'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request. action is required.'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $action = $data['action'];
        
        if ($action === 'add') {
            // 予約を追加
            $date = $data['date'] ?? date('Y-m-d');
            $food = $data['food'] ?? '';
            $people = intval($data['people'] ?? 1);
            $verified = isset($data['verified']) && ($data['verified'] === true || $data['verified'] === 'true' || $data['verified'] === 1);
            
            if (addReservationToSales($date, $food, $people, $verified)) {
                echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to add reservation'], JSON_UNESCAPED_UNICODE);
            }
        } elseif ($action === 'verify') {
            // 認証状態を更新
            $date = $data['date'] ?? date('Y-m-d');
            $food = $data['food'] ?? '';
            $people = intval($data['people'] ?? 1);
            
            if (updateVerificationStatus($date, $food, $people)) {
                echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update verification'], JSON_UNESCAPED_UNICODE);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    }
}
?>

