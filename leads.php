<?php
require_once __DIR__ . '/db.php';

/**
 * Lógica para detectar leads y extraer nombres/negocios en PHP
 */

function processLeads($wa_id, $reply, $history) {
    $pdo = getDB();
    
    // 1. Detectar si el bot envió un enlace de acción (Calificado)
    $isQualified = (strpos($reply, '[[ACTION_LINK]]') !== false || strpos($reply, '[[AGENDA_LINK]]') !== false);
    
    // 2. Heurística simple para extraer nombre (muy básica por ahora)
    $nombre = null;
    $negocio = null;
    
    // Buscar en el último mensaje del usuario
    $lastUserMsg = '';
    foreach (array_reverse($history) as $msg) {
        if ($msg['role'] === 'user') {
            $lastUserMsg = $msg['content'];
            break;
        }
    }
    
    // Intento de extraer nombre si dice "Me llamo X"
    if (preg_match('/(?:me llamo|mi nombre es)\s+([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{2,30})/i', $lastUserMsg, $matches)) {
        $nombre = trim($matches[1]);
    }

    // 3. Upsert del Lead (Actualizar o Insertar)
    $status = $isQualified ? 'calificado' : 'en_progreso';
    
    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT wa_id FROM leads WHERE wa_id = ?");
    $stmt->execute([$wa_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        $stmt = $pdo->prepare("UPDATE leads SET qualification_status = ?, nombre = COALESCE(?, nombre), negocio = COALESCE(?, negocio), updated_at = NOW() WHERE wa_id = ?");
        $stmt->execute([$status, $nombre, $negocio, $wa_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO leads (wa_id, qualification_status, nombre, negocio) VALUES (?, ?, ?, ?)");
        $stmt->execute([$wa_id, $status, $nombre, $negocio]);
    }
}

function cleanReply($reply) {
    // Quitar los marcadores internos antes de enviar al usuario
    $clean = str_replace(['[[ACTION_LINK]]', '[[AGENDA_LINK]]'], getenv('QUALIFIED_CTA_URL'), $reply);
    $clean = preg_replace('/\[\[DESCALIFICADO.*?\]\]/i', '', $clean);
    return trim($clean);
}
