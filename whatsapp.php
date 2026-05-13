<?php
require_once __DIR__ . '/config.php';

/**
 * Cliente simple para enviar mensajes de WhatsApp Cloud API
 */

function sendWhatsAppText($to, $text) {
    $url = "https://graph.facebook.com/v21.0/" . WA_PHONE_ID . "/messages";
    
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => substr($text, 0, 4096)]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . WA_TOKEN
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}

/**
 * Descarga un archivo multimedia desde Meta usando su ID
 */
function downloadMetaMedia($mediaId) {
    // 1. Obtener la URL de descarga
    $url = "https://graph.facebook.com/v21.0/" . $mediaId;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
    $resp = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($resp, true);
    $downloadUrl = $data['url'] ?? null;
    
    if (!$downloadUrl) return null;

    // 2. Descargar el archivo binario
    $ch = curl_init($downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . WA_TOKEN,
        'User-Agent: curl/7.64.1'
    ]);
    $binary = curl_exec($ch);
    curl_close($ch);

    // Guardar temporalmente
    $tmpFile = __DIR__ . '/tmp/' . $mediaId;
    if (!is_dir(__DIR__ . '/tmp')) mkdir(__DIR__ . '/tmp');
    file_put_contents($tmpFile, $binary);
    
    return $tmpFile;
}
