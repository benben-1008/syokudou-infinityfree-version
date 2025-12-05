<?php
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

$dataDir = __DIR__ . '/../data';
$file = $dataDir . '/reservation-times.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

if (!file_exists($file)) {
    // デフォルトの予約時間設定
    $defaultSettings = [
        'enabled' => false,
        'startTime' => '11:30',
        'endTime' => '12:45',
        'message' => '予約時間: 11:30-12:45'
    ];
    file_put_contents($file, json_encode($defaultSettings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function readJsonSafe($file) {
    $content = @file_get_contents($file);
    if ($content === false || $content === '') return [];
    $json = json_decode($content, true);
    return is_array($json) ? $json : [];
}

function writeJsonSafe($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode(readJsonSafe($file), JSON_UNESCAPED_UNICODE);
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        // 必要なフィールドをチェック
        $requiredFields = ['startTime', 'endTime', 'enabled'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"], JSON_UNESCAPED_UNICODE);
                break 2;
            }
        }
        
        // メッセージが設定されていない場合はデフォルトを生成
        if (!isset($input['message']) || empty($input['message'])) {
            $input['message'] = "予約時間: {$input['startTime']}-{$input['endTime']}";
        }
        
        writeJsonSafe($file, $input);
        echo json_encode(['success' => true, 'message' => '予約時間設定を更新しました'], JSON_UNESCAPED_UNICODE);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
}
?>



