<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/knowledge.php';

/**
 * Cliente simple para Groq (OpenAI Compatible) usando cURL
 */

function buildSystemPrompt($userMessage = "") {
    $prompt = @file_get_contents(__DIR__ . '/prompts/system.md');
    if (!$prompt) {
        $prompt = "Eres un asistente de ventas útil para Salvix. Responde de forma concisa en español.";
    }
    
    // RAG: Buscar información relevante en la base de conocimientos
    if (!empty($userMessage)) {
        $chunks = searchKnowledge($userMessage, 5);
        if (!empty($chunks)) {
            logger("RAG: Encontrados " . count($chunks) . " fragmentos relevantes para: '$userMessage'");
            $prompt .= "\n\n--- INFORMACIÓN DE APOYO (RELEVANTE PARA ESTA CONSULTA) ---\n";
            foreach ($chunks as $c) {
                $prompt .= "[Fuente: {$c['source_file']}]\n{$c['content']}\n\n";
            }
            $prompt .= "--- FIN INFORMACIÓN DE APOYO ---\n";
        } else {
            logger("RAG: No se encontraron fragmentos relevantes para: '$userMessage'");
        }
    }
    
    // Leer el Inventario de la base de datos
    require_once __DIR__ . '/db.php';
    try {
        $pdo = getDB();
        $inventory = $pdo->query("SELECT * FROM inventory WHERE stock > 0")->fetchAll();
        if (!empty($inventory)) {
            logger("INVENTARIO: Cargados " . count($inventory) . " productos con stock activo.");
            $prompt .= "\n\n--- PRODUCTOS Y PRECIOS DISPONIBLES ---\n";
            foreach ($inventory as $i) {
                $prompt .= "- {$i['item_name']} | Precio: $" . number_format($i['price'], 2) . " | Stock: {$i['stock']}\n";
            }
        } else {
            logger("INVENTARIO: No hay productos con stock > 0 en la base de datos.");
        }
    } catch (Exception $e) {
        logger("ERROR INVENTARIO: " . $e->getMessage());
    }
    
    return $prompt;
}

function completeChat($userMessage, $history = []) {
    $url = GROQ_BASE_URL . '/chat/completions';
    $model = GROQ_MODEL;

    logger("DEBUG: URL GROQ: $url | MODELO: $model");
    
    // Reactivamos RAG e Inventario
    $systemPrompt = buildSystemPrompt($userMessage); 
    
    $messages = [];
    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    
    foreach ($history as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
    
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7
    ];

    logger("DEBUG: Enviando cURL a Groq...");
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Bajamos a 15 segundos para que no se cuelgue

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        logger("ERROR en completeChat (Groq): Código HTTP $httpCode | Error cURL: $curlError | Respuesta: $response");
        return "Lo siento, tengo problemas para procesar tu mensaje ahora mismo.";
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "No pude obtener una respuesta.";
}

function transcribeAudio($filePath) {
    $url = 'https://api.groq.com/openai/v1/audio/transcriptions';
    $ch = curl_init($url);
    $cfile = new CURLFile($filePath, 'audio/ogg', 'audio.ogg');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => $cfile,
        'model' => 'whisper-large-v3',
        'response_format' => 'json',
        'language' => 'es'
    ]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . GROQ_API_KEY]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['text'] ?? null;
}

function analyzeImage($filePath, $userText = "Describe esta imagen", $history = []) {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $imageData = base64_encode(file_get_contents($filePath));
    $mimeType = 'image/jpeg';
    $systemPrompt = buildSystemPrompt($userText);
    
    $messages = [];
    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    foreach ($history as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
    
    $messages[] = [
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => $userText],
            ['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,$imageData"]]
        ]
    ];

    $payload = [
        'model' => 'llama-3.2-11b-vision-preview',
        'messages' => $messages,
        'temperature' => 0.7
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "Lo siento, pude ver la imagen pero no logré procesar una respuesta.";
}
