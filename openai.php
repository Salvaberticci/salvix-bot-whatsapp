<?php
require_once __DIR__ . '/config.php';

/**
 * Cliente simple para Groq (OpenAI Compatible) usando cURL
 */

function buildSystemPrompt() {
    $prompt = @file_get_contents(__DIR__ . '/prompts/system.md');
    if (!$prompt) {
        $prompt = "Eres un asistente de ventas útil para Salvix. Responde de forma concisa en español.";
    }
    
    // Leer archivos de conocimiento
    $knowledgeDir = __DIR__ . '/knowledge';
    if (is_dir($knowledgeDir)) {
        $files = array_diff(scandir($knowledgeDir), array('.', '..', '.htaccess'));
        if (!empty($files)) {
            $prompt .= "\n\n--- BASE DE CONOCIMIENTOS ---\nUtiliza la siguiente información estrictamente para responder a las dudas del usuario. Si la información no está aquí, no la inventes.\n";
            foreach ($files as $file) {
                $content = @file_get_contents($knowledgeDir . '/' . $file);
                if ($content) {
                    $prompt .= "\n[Documento: $file]\n$content\n";
                }
            }
            $prompt .= "--- FIN BASE DE CONOCIMIENTOS ---\n";
        }
    }
    
    // Leer el Inventario de la base de datos
    require_once __DIR__ . '/db.php';
    try {
        $pdo = getDB();
        $inventory = $pdo->query("SELECT * FROM inventory WHERE stock > 0")->fetchAll();
        if (!empty($inventory)) {
            $prompt .= "\n\n--- INVENTARIO DE PRODUCTOS DISPONIBLES ---\nUtiliza esta lista para ofrecer opciones reales, precios y confirmar disponibilidad al cliente. NO ofrezcas productos que no estén aquí:\n";
            foreach ($inventory as $i) {
                $prompt .= "- {$i['item_name']} | Descripción: {$i['description']} | Precio: $" . number_format($i['price'], 2) . " | Stock: {$i['stock']}\n";
            }
            $prompt .= "--- FIN INVENTARIO ---\n";
        }
    } catch (Exception $e) {
        // Si hay error de DB, simplemente continuamos sin inventario
    }
    
    return $prompt;
}

function completeChat($userMessage, $history = []) {
    $url = GROQ_BASE_URL . '/chat/completions';
    
    // Construir prompt dinámico con base de conocimientos
    $systemPrompt = buildSystemPrompt();
    
    $messages = [];
    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    
    foreach ($history as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
    
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $payload = [
        'model' => GROQ_MODEL,
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return "Lo siento, tengo problemas para procesar tu mensaje ahora mismo.";
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "No pude obtener una respuesta.";
}

/**
 * Transcribe un archivo de audio (ogg/mp3/m4a) usando Groq Whisper
 */
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['text'] ?? null;
}

/**
 * Responde a una conversación que incluye una imagen (Multimodal)
 */
function analyzeImage($filePath, $userText = "Describe esta imagen", $history = []) {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    
    $imageData = base64_encode(file_get_contents($filePath));
    $mimeType = 'image/jpeg';
    
    // Construir prompt dinámico con base de conocimientos
    $systemPrompt = buildSystemPrompt();
    
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
        'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
        'messages' => $messages,
        'temperature' => 0.7
    ];

    logger("ENVIANDO A GROQ VISION: Iniciando petición cURL...");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        logger("ERROR GROQ VISION: Código $httpCode. Respuesta: " . $response);
    } else {
        logger("ÉXITO GROQ VISION: Respuesta recibida correctamente.");
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "Lo siento, pude ver la imagen pero no logré procesar una respuesta.";
}
