<?php
// HTTPヘッダーの設定（セキュリティとパフォーマンス）
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// リクエストボディを取得
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$userMessage = trim($input['message'] ?? '');
$history = $input['history'] ?? [];
$useOllama = isset($input['useOllama']) ? $input['useOllama'] : true;

// 安全チェック
if ($userMessage === '' || mb_strlen($userMessage) > 3000) {
    echo json_encode(['response' => 'メッセージサイズが不適切です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ollamaが利用可能かチェック
$ollamaAvailable = checkOllamaAvailability();

$response = generateAIResponse($userMessage, $useOllama, $ollamaAvailable, $history);

// デバッグ情報（本番環境でも有効）
$debugInfo = [
    'ollamaAvailable' => $ollamaAvailable,
    'useOllama' => $useOllama,
    'isProduction' => isProductionEnvironment(),
    'messageLength' => mb_strlen($userMessage),
    'historyCount' => count($history),
    'responseLength' => mb_strlen($response)
];

echo json_encode([
    'response' => $response,
    'ollamaUsed' => $ollamaAvailable && $useOllama,
    'ollamaAvailable' => $ollamaAvailable,
    'apiType' => $ollamaAvailable && $useOllama ? 'Ollama' : 'Basic',
    'debug' => $debugInfo
], JSON_UNESCAPED_UNICODE);

// Ollamaの可用性をチェック
function checkOllamaAvailability() {
    // 本番環境ではクラウドOllamaサービスを使用
    if (isProductionEnvironment()) {
        return checkCloudOllamaAvailability();
    }
    
    // ローカル環境ではlocalhostのOllamaをチェック
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:11434/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // エラーが発生した場合は利用不可
    if ($error) {
        error_log("Ollama connection error: " . $error);
        return false;
    }
    
    // 200番台のレスポンスコードなら利用可能
    if ($httpCode >= 200 && $httpCode < 300) {
        // llama3モデルが存在するか確認
        $data = json_decode($response, true);
        if (isset($data['models'])) {
            foreach ($data['models'] as $model) {
                $modelName = $model['name'] ?? '';
                if (strpos($modelName, 'llama3') !== false) {
                    return true;
                }
            }
            error_log("llama3モデルが見つかりません。'ollama pull llama3' を実行してください。");
        }
        return true; // モデルチェックが失敗しても、Ollama自体は利用可能
    }
    
    return false;
}

// クラウドOllamaサービスの可用性をチェック
function checkCloudOllamaAvailability() {
    // 本番環境では、Hugging Face APIなどの無料AI APIサービスが利用可能とみなす
    // 実際の可用性は呼び出し時に確認される
    // ここでは、本番環境であることを確認してtrueを返す
    return true;
}

// 本番環境かどうかを判定
function isProductionEnvironment() {
    // InfinityFreeやその他の本番環境の判定
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalhost = strpos($host, 'localhost') !== false || 
                   strpos($host, '127.0.0.1') !== false ||
                   strpos($host, '::1') !== false;
    
    return !$isLocalhost;
}

// Ollama APIを呼び出し
function callOllamaAPI($userMessage, $history = []) {
    // より自然な会話を生成するシステムプロンプト
    $systemPrompt = <<<EOD
あなたは親切で会話的、論理的に説明できる学校食堂のAIアシスタントです。ChatGPTやCopilotのような自然で流暢な会話を心がけてください。

主な役割：
- メニュー、営業時間、予約について質問に答える
- 学習のお手伝いとして数学、理科、英語などの教育関連の質問にも親切に答える
- 一般的な質問や雑談にも自然に対応する

回答のスタイル：
- 自然で流暢な会話を心がける（ChatGPTやCopilotのような感じで）
- 明確で、例を入れつつ、過剰に長くしすぎない
- ユーザーの発言意図を汲み取り、文脈を理解して自然な会話を続ける
- 固定された回答ではなく、会話の流れに応じて柔軟に応答する
- 宿題の完全な答えを提供するのではなく、学習のヒントや解説を提供する
- 親切で丁寧、かつ自然な口調で対応する
- 必要に応じて「何か他に手伝えることはありますか？」で締める（毎回ではない）
- 同じ質問でも、会話の文脈に応じて異なる表現で答える
- ユーザーの質問に対して、単に情報を列挙するのではなく、会話として自然に返答する

重要なポイント：
- 固定された回答テンプレートを使わない
- 会話の文脈を理解して応答する
- 自然な会話の流れを保つ
- 毎回同じような応答にならないよう、バリエーションを持たせる
- ユーザーの質問の意図を深く理解し、それに応じた適切な応答をする
EOD;
    
    if (isProductionEnvironment()) {
        // 本番環境ではクラウドOllamaサービスを使用
        return callCloudOllamaAPI($userMessage, $systemPrompt, $history);
    } else {
        // ローカル環境ではlocalhostのOllamaを使用
        return callLocalOllamaAPI($userMessage, $systemPrompt, $history);
    }
}

// 利用可能なOllamaモデルを取得
function getAvailableOllamaModel() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:11434/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['models'])) {
            // 優先順位: llama3 > llama2 > その他
            $preferredModels = ['llama3', 'llama2', 'llama', 'mistral', 'phi'];
            foreach ($preferredModels as $preferred) {
                foreach ($data['models'] as $model) {
                    $modelName = $model['name'] ?? '';
                    if (strpos($modelName, $preferred) !== false) {
                        return $modelName;
                    }
                }
            }
            // 利用可能な最初のモデルを返す
            if (!empty($data['models'])) {
                return $data['models'][0]['name'];
            }
        }
    }
    
    // デフォルトはllama3（インストールされていない場合はエラーになる）
    return 'llama3';
}

// ローカルOllama APIを呼び出し
function callLocalOllamaAPI($userMessage, $systemPrompt, $history = []) {
    // 利用可能なモデルを自動検出
    $model = getAvailableOllamaModel();
    
    // メッセージ配列を構築
    $messages = [];
    
    // システムプロンプトを追加
    $messages[] = [
        'role' => 'system',
        'content' => $systemPrompt
    ];
    
    // 直近の履歴（6ターンまで）を追加
    foreach (array_slice($history, -6) as $msg) {
        if (isset($msg['role']) && isset($msg['content'])) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }
    }
    
    // 現在のユーザーメッセージを追加
    $messages[] = [
        'role' => 'user',
        'content' => $userMessage
    ];
    
    $requestBody = [
        'model' => $model, // 利用可能なモデルを自動使用
        'messages' => $messages,
        'stream' => false,
        'options' => [
            'temperature' => 0.8,  // より自然な応答のため温度を上げる
            'top_p' => 0.9,        // 多様性を確保
            'repeat_penalty' => 1.1 // 繰り返しを防ぐ
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:11434/api/chat');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // タイムアウトを延長（2分）
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // エラーログを記録
    if ($error) {
        error_log("Local Ollama API error: " . $error);
        return false;
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['message']['content'])) {
            $content = trim($data['message']['content']);
            if ($content !== '') {
                return $content;
            }
        }
        // レスポンスの構造が異なる場合の処理
        if (isset($data['response'])) {
            $content = trim($data['response']);
            if ($content !== '') {
                return $content;
            }
        }
        error_log("Ollama response structure: " . json_encode($data));
    }
    
    error_log("Local Ollama API failed with HTTP code: " . $httpCode);
    error_log("Response: " . substr($response, 0, 500));
    if ($httpCode === 404) {
        $availableModel = getAvailableOllamaModel();
        error_log("Ollamaモデル '{$model}' が見つかりません。利用可能なモデル: {$availableModel}");
        error_log("モデルをインストールしてください: ollama pull {$model} または ollama pull {$availableModel}");
    }
    return false;
}

// クラウドOllama APIを呼び出し
function callCloudOllamaAPI($userMessage, $systemPrompt, $history = []) {
    // 会話履歴を含めたプロンプトを構築
    $fullPrompt = buildPromptWithHistory($userMessage, $systemPrompt, $history);
    
    // Hugging Face APIを試行（無料、APIキー不要）
    $hfResponse = callHuggingFaceAPIWithPrompt($fullPrompt);
    if ($hfResponse !== false && trim($hfResponse) !== '') {
        error_log("Hugging Face API success: " . substr($hfResponse, 0, 100));
        return $hfResponse;
    }
    
    // 簡易プロンプトで再試行
    $simplePrompt = $systemPrompt . "\n\n質問: " . $userMessage . "\n回答:";
    $simpleResponse = callHuggingFaceAPIWithPrompt($simplePrompt);
    if ($simpleResponse !== false && trim($simpleResponse) !== '') {
        error_log("Hugging Face API success (simple): " . substr($simpleResponse, 0, 100));
        return $simpleResponse;
    }
    
    error_log("All Hugging Face API attempts failed");
    return false;
}

// 会話履歴を含めたプロンプトを構築
function buildPromptWithHistory($userMessage, $systemPrompt, $history = []) {
    $prompt = $systemPrompt . "\n\n";
    
    // 会話履歴を追加（直近6ターンまで）
    if (!empty($history)) {
        $prompt .= "会話履歴:\n";
        foreach (array_slice($history, -6) as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';
            if ($role === 'user') {
                $prompt .= "ユーザー: " . $content . "\n";
            } else {
                $prompt .= "アシスタント: " . $content . "\n";
            }
        }
        $prompt .= "\n";
    }
    
    $prompt .= "現在の質問: " . $userMessage . "\n回答:";
    
    return $prompt;
}

// Hugging Face APIを呼び出し
function callHuggingFaceAPI($userMessage, $systemPrompt) {
    $prompt = $systemPrompt . "\n\n[重要] 食堂に関係しない場合は簡潔に一般的な助言に留め、根拠のない断定を避ける。\n\n質問: " . $userMessage . "\n回答:";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api-inference.huggingface.co/models/microsoft/DialoGPT-medium');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'inputs' => $prompt,
        'parameters' => [
            'max_length' => 180,
            'temperature' => 0.2,
            'do_sample' => false,
            'pad_token_id' => 50256
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (compatible; AI-Assistant/1.0)'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Hugging Face API error: " . $error);
        return false;
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data[0]['generated_text'])) {
            $generatedText = $data[0]['generated_text'];
            // プロンプト部分を除去して回答のみを抽出
            $answer = str_replace($prompt, '', $generatedText);
            return trim($answer) ?: '申し訳ございません。適切な回答を生成できませんでした。';
        }
    }
    
    error_log("Hugging Face API failed with HTTP code: " . $httpCode);
    return false;
}

// Hugging Face APIを呼び出し（プロンプト版、会話履歴対応）
function callHuggingFaceAPIWithPrompt($fullPrompt) {
    // より良いモデルを試行（会話に適したモデル、複数の選択肢）
    $models = [
        'microsoft/DialoGPT-medium',  // 会話用モデル
        'gpt2',  // フォールバック
        'distilgpt2',  // 軽量モデル
        'facebook/blenderbot-400M-distill',  // チャットボット用
    ];
    
    foreach ($models as $model) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api-inference.huggingface.co/models/' . $model);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'inputs' => $fullPrompt,
            'parameters' => [
                'max_length' => 300,  // より長い応答を許可
                'temperature' => 0.7,  // より自然な応答
                'do_sample' => true,
                'top_p' => 0.9,
                'repetition_penalty' => 1.2
            ]
        ], JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (compatible; AI-Assistant/1.0)'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // タイムアウトを延長
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Hugging Face API error ($model): " . $error);
            continue; // 次のモデルを試行
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            // エラーレスポンスのチェック
            if (isset($data['error'])) {
                error_log("Hugging Face API error ($model): " . $data['error']);
                continue;
            }
            
            if (isset($data[0]['generated_text'])) {
                $generatedText = $data[0]['generated_text'];
                // プロンプト部分を除去して回答のみを抽出
                $answer = str_replace($fullPrompt, '', $generatedText);
                $answer = trim($answer);
                
                if ($answer !== '' && mb_strlen($answer) > 5) { // 最低5文字以上
                    error_log("Hugging Face API success ($model): " . substr($answer, 0, 50));
                    return $answer;
                } else {
                    error_log("Hugging Face API empty response ($model)");
                }
            } else {
                error_log("Hugging Face API unexpected response structure ($model): " . substr(json_encode($data), 0, 200));
            }
        } else if ($httpCode === 503) {
            // モデルがロード中の場合
            error_log("Hugging Face API model loading ($model), trying next model...");
            continue;
        } else {
            error_log("Hugging Face API failed with HTTP code: $httpCode ($model)");
            if ($response) {
                error_log("Response: " . substr($response, 0, 200));
            }
        }
    }
    
    return false;
}

function generateAIResponse($userMessage, $useOllama = true, $ollamaAvailable = false, $history = []) {
    // まず食堂データを確認（最優先）
    $cafeteriaAnswer = answerFromCafeteriaData($userMessage);
    if ($cafeteriaAnswer !== null) {
        return $cafeteriaAnswer;
    }
    
    // Ollamaを最優先で使用（useOllamaがtrueの場合）
    if ($useOllama && $ollamaAvailable) {
        $ollamaResponse = callOllamaAPI($userMessage, $history);
        if ($ollamaResponse !== false && trim($ollamaResponse) !== '' && mb_strlen($ollamaResponse) > 10) {
            return $ollamaResponse;
        }
        // エラーログに記録
        error_log("Ollama API call failed for message: " . substr($userMessage, 0, 100));
        
        // ローカル環境でOllamaが失敗した場合、簡易プロンプトで再試行
        if (!isProductionEnvironment()) {
            $simpleResponse = callOllamaAPISimple($userMessage);
            if ($simpleResponse !== false && trim($simpleResponse) !== '' && mb_strlen($simpleResponse) > 10) {
                return $simpleResponse;
            }
        }
    }
    
    // フォールバック応答を生成（Ollamaが利用できない、または失敗した場合）
    // より自然な会話を生成するため、常にフォールバックを使用
    $fallbackResponse = generateIntelligentFallback($userMessage, $history);
    if ($fallbackResponse !== null) {
        return $fallbackResponse;
    }
    
    // 完全にOllamaが利用できない場合のメッセージ
    if (!$ollamaAvailable || !$useOllama) {
        $unavailableMessages = [
            "申し訳ございませんが、現在AI応答システムが利用できません。\n\n食堂に関する具体的なご質問（メニュー、営業時間、予約など）でしたら、管理者サイトで設定された情報をお答えできます。\n\nAI応答機能をご利用になるには、Ollamaをインストールして「AI APIを使用する」にチェックを入れてください。",
            "現在、AI応答システムが利用できません。\n\n食堂に関するご質問（メニュー、営業時間、予約など）でしたら、管理者サイトで設定された情報をお答えできます。\n\nAI応答機能を使うには、Ollamaをインストールして「AI APIを使用する」にチェックを入れてください。"
        ];
        return $unavailableMessages[array_rand($unavailableMessages)];
    }
    
    // 最後のフォールバック
    $finalMessages = [
        "申し訳ございませんが、適切な応答を生成できませんでした。\n\n考えられる原因：\n- 外部AI APIサービスへの接続が失敗している可能性があります\n- タイムアウトが発生した可能性があります\n\nもう一度お試しいただくか、具体的なご質問をお聞かせください。",
        "申し訳ございませんが、応答を生成できませんでした。\n\n外部AI APIサービスへの接続が失敗している可能性があります。もう一度お試しいただくか、具体的なご質問をお聞かせください。"
    ];
    return $finalMessages[array_rand($finalMessages)];
}

// インテリジェントなフォールバック応答を生成（より自然な会話）
function generateIntelligentFallback($userMessage, $history = []) {
    $message = mb_strtolower($userMessage);
    
    // 会話履歴を分析
    $conversationContext = analyzeConversationContext($history, $userMessage);
    
    // 挨拶への応答（会話履歴を考慮、バリエーションを持たせる）
    if (mb_strpos($message, 'こんにちは') !== false || mb_strpos($message, 'こんばんは') !== false || 
        mb_strpos($message, 'おはよう') !== false || mb_strpos($message, 'hello') !== false || 
        mb_strpos($message, 'hi') !== false) {
        if (empty($history)) {
            $greetings = [
                "こんにちは！食堂のAIアシスタントです。\n\n何かお手伝いできることがございましたら、お気軽にお声かけください。\n\nメニュー、営業時間、予約など、食堂に関するご質問でしたら何でもお答えします！",
                "こんにちは！いらっしゃいませ。\n\n食堂について、メニューや営業時間、予約など、何でもお聞きください。お手伝いさせていただきます！",
                "こんにちは！食堂のAIアシスタントです。\n\n今日はどのようなご用件でしょうか？メニューや営業時間、予約についてお答えできます。"
            ];
            return $greetings[array_rand($greetings)];
        } else {
            $returnGreetings = [
                "こんにちは！またいらっしゃいましたね。\n\n何か他にお手伝いできることはありますか？",
                "こんにちは！おかえりなさい。\n\n他にご質問がございましたら、お気軽にお聞かせください。",
                "こんにちは！\n\n何か他にお手伝いできることはありますか？"
            ];
            return $returnGreetings[array_rand($returnGreetings)];
        }
    }
    
    // お礼への応答（会話履歴を考慮、バリエーションを持たせる）
    if (mb_strpos($message, 'ありがとう') !== false || mb_strpos($message, 'thank') !== false) {
        $thanks = [
            "どういたしまして！\n\n他にもご質問がございましたら、いつでもお声かけください。",
            "いえいえ、お役に立てて嬉しいです！\n\n他に何かございましたら、お気軽にどうぞ。",
            "どういたしまして。\n\n他にもご質問があれば、いつでもお聞かせください。"
        ];
        return $thanks[array_rand($thanks)];
    }
    
    // メニューに関する質問（会話履歴を考慮、バリエーションを持たせる）
    if (mb_strpos($message, 'メニュー') !== false || mb_strpos($message, '料理') !== false || 
        mb_strpos($message, '食べ物') !== false || mb_strpos($message, '定食') !== false ||
        mb_strpos($message, '何が') !== false || mb_strpos($message, '何を') !== false) {
        $cafeteriaAnswer = answerFromCafeteriaData($userMessage);
        if ($cafeteriaAnswer !== null) {
            // データベースからの回答をより自然な形で返す
            return $cafeteriaAnswer;
        }
        // 会話履歴から文脈を取得
        if ($conversationContext['hasMenuContext']) {
            $menuResponses = [
                "メニューについてですね。本日のメニューは管理者サイトで設定されています。\n\n具体的にどのメニューについて知りたいですか？",
                "メニューのことですね。今日のメニューについては、管理者サイトで設定された情報を確認できます。\n\nどのメニューについて詳しく知りたいですか？",
                "メニューについてお答えします。本日のメニューは管理者サイトで設定されています。\n\nどのメニューについて知りたいですか？"
            ];
            return $menuResponses[array_rand($menuResponses)];
        }
        $menuIntroResponses = [
            "メニューについてお答えします。\n\n本日のメニューについては、管理者サイトで設定された情報を確認できます。\n\nどのメニューについて詳しく知りたいですか？",
            "メニューですね。今日のメニューは管理者サイトで設定されています。\n\n具体的にどのメニューについて知りたいですか？",
            "メニューについてお答えできます。本日のメニューは管理者サイトで設定されています。\n\nどのメニューについて詳しく知りたいですか？"
        ];
        return $menuIntroResponses[array_rand($menuIntroResponses)];
    }
    
    // 営業時間に関する質問
    if (mb_strpos($message, '営業時間') !== false || mb_strpos($message, '何時') !== false || 
        mb_strpos($message, '開いて') !== false || mb_strpos($message, '閉まって') !== false ||
        mb_strpos($message, 'いつ') !== false) {
        $now = new DateTime();
        $timeString = $now->format('H:i');
        $isOpen = ($now->format('H') >= 11 && $now->format('H') < 13);
        
        return "営業時間についてお答えします。\n\n⏰ **営業時間**\n\n**平日（月〜金）**\n・11:30 - 13:00\n\n**土日祝日**\n・休業\n\n現在の時刻は{$timeString}です。" . 
               ($isOpen ? "現在営業中です！" : "現在は営業時間外です。") . "\n\n他にご質問はありますか？";
    }
    
    // 予約に関する質問
    if (mb_strpos($message, '予約') !== false) {
        $cafeteriaAnswer = answerFromCafeteriaData($userMessage);
        if ($cafeteriaAnswer !== null) {
            return $cafeteriaAnswer;
        }
        return "予約についてお答えします。\n\n📝 **予約システム**\n\n予約はメインページの「予約サイト」から行えます。\n\n予約可能時間や混雑状況については、管理者サイトで設定された情報を確認できます。\n\n予約について他にご質問はありますか？";
    }
    
    // 会話履歴がある場合、より文脈を考慮した応答
    if (!empty($history)) {
        // 直前の会話を確認
        $lastAssistantMessage = '';
        $lastUserMessage = '';
        foreach (array_reverse($history) as $msg) {
            if (isset($msg['role'])) {
                if ($msg['role'] === 'assistant' && $lastAssistantMessage === '') {
                    $lastAssistantMessage = $msg['content'] ?? '';
                }
                if ($msg['role'] === 'user' && $lastUserMessage === '') {
                    $lastUserMessage = $msg['content'] ?? '';
                }
            }
        }
        
        // 前の会話に関連する応答
        if ($lastUserMessage !== '' && $lastAssistantMessage !== '') {
            // 質問の種類を判定
            $lastMessageLower = mb_strtolower($lastUserMessage);
            if (mb_strpos($lastMessageLower, 'メニュー') !== false) {
                return "メニューについて、他にもご質問はありますか？\n\n例えば、料金やアレルギー対応についてもお答えできます。";
            } else if (mb_strpos($lastMessageLower, '営業') !== false || mb_strpos($lastMessageLower, '時間') !== false) {
                return "営業時間について、他にもご質問はありますか？\n\n予約やメニューについてもお答えできます。";
            } else if (mb_strpos($lastMessageLower, '予約') !== false) {
                return "予約について、他にもご質問はありますか？\n\nメニューや営業時間についてもお答えできます。";
            }
        }
    }
    
    // 質問形式の判定
    if (mb_strpos($message, '？') !== false || mb_strpos($message, '?') !== false ||
        mb_strpos($message, '何') !== false || mb_strpos($message, 'どう') !== false ||
        mb_strpos($message, 'なぜ') !== false || mb_strpos($message, 'どうして') !== false) {
        return "ご質問ありがとうございます。\n\n食堂について以下の内容でしたらお答えできます：\n\n🍽️ メニューについて\n⏰ 営業時間について\n📝 予約について\n⚠️ アレルギー対応について\n💰 料金について\n📍 場所について\n\n具体的にどのことについて知りたいですか？";
    }
    
    // デフォルトの応答（より自然に、バリエーションを持たせる）
    $defaultResponses = [
        "ご質問ありがとうございます。\n\n食堂についてお答えできます。メニュー、営業時間、予約など、どのことについて知りたいですか？\n\nお気軽にお聞かせください！",
        "ご質問をありがとうございます。\n\n食堂について、メニューや営業時間、予約など、何でもお答えできます。どのことについて知りたいですか？",
        "ご質問ありがとうございます。\n\n食堂についてお答えできます。メニュー、営業時間、予約などについて、どのことについて知りたいですか？\n\nお気軽にどうぞ。"
    ];
    return $defaultResponses[array_rand($defaultResponses)];
}

// 会話の文脈を分析
function analyzeConversationContext($history, $currentMessage) {
    $context = [
        'hasMenuContext' => false,
        'hasReservationContext' => false,
        'hasTimeContext' => false,
        'messageCount' => count($history)
    ];
    
    $allMessages = array_merge($history, [['role' => 'user', 'content' => $currentMessage]]);
    
    foreach ($allMessages as $msg) {
        $content = mb_strtolower($msg['content'] ?? '');
        if (mb_strpos($content, 'メニュー') !== false || mb_strpos($content, '料理') !== false) {
            $context['hasMenuContext'] = true;
        }
        if (mb_strpos($content, '予約') !== false) {
            $context['hasReservationContext'] = true;
        }
        if (mb_strpos($content, '時間') !== false || mb_strpos($content, '営業') !== false) {
            $context['hasTimeContext'] = true;
        }
    }
    
    return $context;
}

// 簡易版Ollama API呼び出し（システムプロンプトなし）
function callOllamaAPISimple($userMessage) {
    if (isProductionEnvironment()) {
        return false; // 本番環境では簡易版は使用しない
    }
    
    // 利用可能なモデルを自動検出
    $model = getAvailableOllamaModel();
    
    $requestBody = [
        'model' => $model, // 利用可能なモデルを自動使用
        'messages' => [
            [
                'role' => 'user',
                'content' => $userMessage
            ]
        ],
        'stream' => false
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:11434/api/chat');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return false;
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['message']['content'])) {
            return trim($data['message']['content']);
        }
    }
    
    return false;
}

// サーバー上のデータ(JSON)から回答を合成
function answerFromCafeteriaData($userMessage) {
    $msg = mb_strtolower($userMessage);

    $dataDir = __DIR__ . '/../data';
    $today = (new DateTime())->format('Y-m-d');

    // 休業日
    $holidays = readJsonSafe($dataDir . '/holidays.json');
    $todayHoliday = null;
    foreach ($holidays as $h) {
        if (($h['date'] ?? '') === $today) { $todayHoliday = $h; break; }
    }

    // 定食
    $dailyMenus = readJsonSafe($dataDir . '/daily-menu.json');
    $todayMenu = null;
    foreach ($dailyMenus as $m) {
        if (($m['date'] ?? '') === $today) { $todayMenu = $m; break; }
    }

    // 予約時間
    $reservationTimes = readJsonSafe($dataDir . '/reservation-times.json');

    // 予約人数（全件）: 過去データをクリアしていない場合も合算
    $reservations = readJsonSafe($dataDir . '/reservations.json');
    $totalCount = is_array($reservations) ? count($reservations) : 0;

    // 混雑予測
    $congestion = '空いています';
    if ($totalCount >= 30) $congestion = '非常に混雑';
    else if ($totalCount >= 15) $congestion = 'やや混雑';

    // ルール: 質問に応じて決定的返答（より自然な会話形式で）
    if (mb_strpos($msg, '定食') !== false || mb_strpos($msg, 'メニュー') !== false) {
        $menuFood = $todayMenu['food'] ?? '未設定';
        $statusText = $todayHoliday ? ('休業（理由: ' . ($todayHoliday['reason'] ?? '不明') . '）') : '営業予定';
        
        $responses = [
            "本日の定食は「{$menuFood}」です。\n\n営業状況は{$statusText}です。",
            "今日の定食は「{$menuFood}」となっています。\n\n営業状況は{$statusText}です。",
            "本日の定食メニューは「{$menuFood}」です。\n\n営業状況は{$statusText}です。"
        ];
        return $responses[array_rand($responses)];
    }

    if (mb_strpos($msg, '休業') !== false || mb_strpos($msg, '営業') !== false) {
        if ($todayHoliday) {
            $reason = $todayHoliday['reason'] ?? '不明';
            $responses = [
                "本日は🚫 休業となっております。\n\n理由: {$reason}",
                "申し訳ございませんが、本日は🚫 休業です。\n\n理由: {$reason}",
                "本日は🚫 休業となっています。\n\n理由: {$reason}"
            ];
            return $responses[array_rand($responses)];
        } else {
            $responses = [
                "本日は✅ 営業予定です。",
                "本日は✅ 営業しています。",
                "本日は✅ 営業予定となっています。"
            ];
            return $responses[array_rand($responses)];
        }
    }

    if (mb_strpos($msg, '予約時間') !== false || mb_strpos($msg, 'いつ予約') !== false || mb_strpos($msg, '予約可能') !== false) {
        if (!empty($reservationTimes) && ($reservationTimes['enabled'] ?? false)) {
            $startTime = $reservationTimes['startTime'] ?? '未設定';
            $endTime = $reservationTimes['endTime'] ?? '未設定';
            $message = $reservationTimes['message'] ?? '';
            $messageText = $message ? "\n\n補足: {$message}" : '';
            
            $responses = [
                "予約可能時間は{$startTime}から{$endTime}までです。{$messageText}",
                "予約は{$startTime}から{$endTime}まで受け付けています。{$messageText}",
                "予約可能時間は{$startTime}〜{$endTime}です。{$messageText}"
            ];
            return $responses[array_rand($responses)];
        }
        $responses = [
            "予約時間の制限は現在ありません。いつでも予約可能です。",
            "予約はいつでも可能です。時間制限はありません。",
            "予約時間の制限はありません。いつでも予約できます。"
        ];
        return $responses[array_rand($responses)];
    }

    if (mb_strpos($msg, '予約') !== false || mb_strpos($msg, '混雑') !== false || mb_strpos($msg, '人数') !== false) {
        $responses = [
            "現在の予約人数は{$totalCount}人です。\n\n混雑予測: {$congestion}",
            "予約人数は{$totalCount}人となっています。\n\n混雑予測: {$congestion}",
            "現在{$totalCount}人の予約があります。\n\n混雑予測: {$congestion}"
        ];
        return $responses[array_rand($responses)];
    }

    return null; // データ駆動の対象外
}

function readJsonSafe($path) {
    if (!file_exists($path)) return [];
    $txt = @file_get_contents($path);
    if ($txt === false || $txt === '') return [];
    $json = json_decode($txt, true);
    return is_array($json) ? $json : [];
}
?>
