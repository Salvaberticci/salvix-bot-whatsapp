<?php
require_once __DIR__ . '/db.php';

/**
 * Utilidades de limpieza de respuestas del bot.
 */

function cleanReply($reply) {
    $clean = preg_replace('/<think>.*?<\/think>/is', '', $reply);
    $tenant = $GLOBALS['TENANT'] ?? null;
    $cta = ($tenant && !empty($tenant['cta_url'])) ? $tenant['cta_url'] : getenv('QUALIFIED_CTA_URL');
    $clean = str_replace(['[[ACTION_LINK]]', '[[AGENDA_LINK]]'], $cta, $clean);
    $clean = preg_replace('/\[\[DESCALIFICADO.*?\]\]/i', '', $clean);
    return trim($clean);
}