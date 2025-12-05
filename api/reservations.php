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
$file = $dataDir . '/reservations.json';
if (!is_dir($dataDir)) {
  mkdir($dataDir, 0777, true);
}
if (!file_exists($file)) {
  file_put_contents($file, json_encode([] , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function read_json($file) {
  $content = @file_get_contents($file);
  if ($content === false || $content === '') return [];
  $json = json_decode($content, true);
  return is_array($json) ? $json : [];
}

function write_json($file, $data) {
  return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

switch ($_SERVER['REQUEST_METHOD']) {
  case 'GET':
    echo json_encode(read_json($file), JSON_UNESCAPED_UNICODE);
    break;
  case 'POST':
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // アクションタイプをチェック
    if (isset($data['action']) && $data['action'] === 'verify') {
      // 認証マークを更新するアクション
      if (!is_array($data) || !isset($data['name']) || !isset($data['reservationNumber'])) {
        http_response_code(400);
        echo json_encode([ 'error' => 'Invalid request. name and reservationNumber are required.' ], JSON_UNESCAPED_UNICODE);
        break;
      }
      
      $reservations = read_json($file);
      $found = false;
      
      foreach ($reservations as &$reservation) {
        // 名前と予約番号を比較（型を統一して比較）
        $reservationName = isset($reservation['name']) ? trim($reservation['name']) : '';
        $reservationNumber = isset($reservation['reservationNumber']) ? intval($reservation['reservationNumber']) : null;
        $updateName = isset($data['name']) ? trim($data['name']) : '';
        $updateNumber = isset($data['reservationNumber']) ? intval($data['reservationNumber']) : null;
        
        if ($reservationName === $updateName && 
            $reservationNumber !== null && 
            $updateNumber !== null &&
            $reservationNumber === $updateNumber) {
          // verifiedフラグを更新
          $reservation['verified'] = true;
          $found = true;
          break;
        }
      }
      
      if (!$found) {
        http_response_code(404);
        echo json_encode([ 'error' => 'Reservation not found' ], JSON_UNESCAPED_UNICODE);
        break;
      }
      
      write_json($file, $reservations);
      echo json_encode([ 'ok' => true, 'message' => 'Reservation verified' ], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // 通常のPOST（予約データの置き換え）
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode([ 'error' => 'Invalid JSON array' ], JSON_UNESCAPED_UNICODE);
      break;
    }
    write_json($file, $data);
    echo json_encode([ 'ok' => true ], JSON_UNESCAPED_UNICODE);
    break;
  default:
    http_response_code(405);
    echo json_encode([ 'error' => 'Method not allowed' ], JSON_UNESCAPED_UNICODE);
}
?>

