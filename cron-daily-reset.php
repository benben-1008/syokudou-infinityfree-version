<?php
/**
 * 日次自動リセット用のcronジョブスクリプト
 * 毎日午前0時に実行されることを想定
 */

// ログファイルのパス
$logFile = __DIR__ . '/logs/daily-reset.log';

// ログディレクトリの作成
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// ログ関数
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// エラーハンドリング
set_error_handler(function($severity, $message, $file, $line) {
    writeLog("ERROR: {$message} in {$file} on line {$line}");
});

// メイン処理
try {
    writeLog("日次リセット処理を開始します");
    
    // 日次リセットAPIを呼び出し
    $resetUrl = 'http://localhost/api/daily-reset.php';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $resetUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        writeLog("CURL ERROR: {$error}");
        exit(1);
    }
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            writeLog("日次リセットが正常に完了しました: " . $result['message']);
        } else {
            writeLog("日次リセットが失敗しました: " . ($result['message'] ?? 'Unknown error'));
            exit(1);
        }
    } else {
        writeLog("HTTP ERROR: {$httpCode}");
        exit(1);
    }
    
    writeLog("日次リセット処理が完了しました");
    
} catch (Exception $e) {
    writeLog("EXCEPTION: " . $e->getMessage());
    exit(1);
}
?>
