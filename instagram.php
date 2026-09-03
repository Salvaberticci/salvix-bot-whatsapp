<?php
/**
 * Cliente simple para enviar mensajes de Instagram DM.
 * Usa la Instagram Messaging API (Graph API).
 */

function sendInstagramMessage($recipientId, $text, $igToken) {
    $url = "https://graph.facebook.com/v21.0/me/messages";
    $payload = [
        'recipient' => ['id' => $recipientId],
        'message' => ['text' => substr($text, 0, 2000)]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $igToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        logger("ERROR IG a $recipientId: HTTP $httpCode. $response. CURL: $error");
    } else {
        logger("ÉXITO IG: Mensaje enviado a $recipientId.");
    }

    return $httpCode === 200;
}

function sendInstagramAction($recipientId, $action, $igToken) {
    $url = "https://graph.facebook.com/v21.0/me/messages";
    $payload = [
        'recipient' => ['id' => $recipientId],
        'sender_action' => $action  // typing_on, typing_off, mark_seen
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $igToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

function downloadInstagramMedia($mediaId, $igToken) {
    $url = "https://graph.facebook.com/v21.0/$mediaId";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $igToken]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($resp, true);
    $downloadUrl = $data['url'] ?? null;

    if (!$downloadUrl) {
        logger("ERROR IG: No URL de descarga. $resp");
        return null;
    }

    $ch = curl_init($downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $igToken]);
    $binary = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($info['http_code'] !== 200) return null;

    $tmpFile = __DIR__ . '/tmp/ig_' . $mediaId;
    if (!is_dir(__DIR__ . '/tmp')) mkdir(__DIR__ . '/tmp');
    file_put_contents($tmpFile, $binary);
    return $tmpFile;
}
