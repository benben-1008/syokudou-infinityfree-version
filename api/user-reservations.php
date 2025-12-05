<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ログイン確認
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user']['id'];
$dataDir = __DIR__ . '/../data';
$reservationsFile = $dataDir . '/reservations.json';

function readJsonSafe($file) {
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    if ($content === false || $content === '') return [];
    $json = json_decode($content, true);
    return is_array($json) ? $json : [];
}

// 予約データを読み込み
$allReservations = readJsonSafe($reservationsFile);

// ユーザーの予約のみをフィルタリング
$userReservations = array_filter($allReservations, function($reservation) use ($userId) {
    return isset($reservation['userId']) && $reservation['userId'] === $userId;
});

// 日付順（新しい順）にソート
usort($userReservations, function($a, $b) {
    $dateA = $a['date'] ?? '';
    $timeA = $a['time'] ?? '';
    $dateB = $b['date'] ?? '';
    $timeB = $b['time'] ?? '';
    
    $datetimeA = $dateA . ' ' . $timeA;
    $datetimeB = $dateB . ' ' . $timeB;
    
    return strcmp($datetimeB, $datetimeA); // 降順（新しい順）
});

echo json_encode(array_values($userReservations), JSON_UNESCAPED_UNICODE);
?>

