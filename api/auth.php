<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataDir = __DIR__ . '/../data';
$usersFile = $dataDir . '/users.json';

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

// Google IDトークンを検証（簡易版 - 本番環境では適切な検証が必要）
function verifyGoogleToken($idToken) {
    // 注意: 本番環境では、Googleの公式ライブラリを使用してトークンを検証する必要があります
    // ここでは簡易的な実装として、トークンが存在することを確認します
    if (empty($idToken)) {
        return false;
    }
    
    // 実際の実装では、GoogleのAPIエンドポイントを使用してトークンを検証します
    // https://oauth2.googleapis.com/tokeninfo?id_token=TOKEN
    // ここでは簡易実装として、トークンが存在することを確認します
    return true;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        // ログイン処理
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['idToken']) || !isset($input['userInfo'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $idToken = $input['idToken'];
        $userInfo = $input['userInfo'];
        
        // トークンの検証（簡易版）
        if (!verifyGoogleToken($idToken)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // ユーザー情報を取得
        $googleId = $userInfo['sub'] ?? $userInfo['id'] ?? null;
        $email = $userInfo['email'] ?? '';
        $name = $userInfo['name'] ?? $userInfo['given_name'] ?? 'ユーザー';
        $picture = $userInfo['picture'] ?? '';
        
        if (!$googleId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user info'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // ユーザーデータを読み込み
        $users = readJsonSafe($usersFile);
        
        // 既存ユーザーを検索または新規作成
        $user = null;
        foreach ($users as $u) {
            if ($u['googleId'] === $googleId) {
                $user = $u;
                break;
            }
        }
        
        if (!$user) {
            // 新規ユーザーを作成
            $user = [
                'id' => uniqid('user_', true),
                'googleId' => $googleId,
                'email' => $email,
                'name' => $name,
                'picture' => $picture,
                'createdAt' => date('Y-m-d H:i:s')
            ];
            $users[] = $user;
            writeJsonSafe($usersFile, $users);
        } else {
            // 既存ユーザーの情報を更新
            $user['email'] = $email;
            $user['name'] = $name;
            $user['picture'] = $picture;
            $user['lastLogin'] = date('Y-m-d H:i:s');
            
            // ユーザーリストを更新
            foreach ($users as &$u) {
                if ($u['googleId'] === $googleId) {
                    $u = $user;
                    break;
                }
            }
            writeJsonSafe($usersFile, $users);
        }
        
        // セッションにユーザー情報を保存
        $_SESSION['user'] = $user;
        $_SESSION['googleId'] = $googleId;
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'picture' => $user['picture']
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case 'GET':
        // 現在のログイン状態を確認
        if (isset($_SESSION['user'])) {
            echo json_encode([
                'loggedIn' => true,
                'user' => [
                    'id' => $_SESSION['user']['id'],
                    'name' => $_SESSION['user']['name'],
                    'email' => $_SESSION['user']['email'],
                    'picture' => $_SESSION['user']['picture'] ?? ''
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['loggedIn' => false], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'DELETE':
        // ログアウト処理
        session_destroy();
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
}
?>

