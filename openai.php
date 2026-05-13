<?php
require_once __DIR__ . '/config.php';

/**
 * Cliente simple para Groq (OpenAI Compatible) usando cURL
 */

function completeChat($userMessage, $history = []) {
    $url = GROQ_BASE_URL . '/chat/completions';
    
    // Leer el prompt del sistema desde el archivo original
    $systemPrompt = @file_get_contents(__DIR__ . '/prompts/system.md');
    if (!$systemPrompt) {
        $systemPrompt = "Eres un asistente de ventas útil para Salvix. Responde de forma concisa en español.";
    }
    
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
 * Analiza una imagen usando Groq Vision
 */
function analyzeImage($filePath, $userText = "Describe esta imagen") {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    
    $imageData = base64_encode(file_get_contents($filePath));
    $mimeType = 'image/jpeg'; // Simplificado
    
    $payload = [
        'model' => 'llama-3.2-90b-vision-preview',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $userText],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,$imageData"]]
                ]
            ]
        ]
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
    return $data['choices'][0]['message']['content'] ?? "No pude analizar la imagen.";
}
