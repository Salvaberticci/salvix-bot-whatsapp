<?php
/**
 * Cargador simple de variables de entorno desde el archivo .env
 */

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Definir constantes para fácil acceso
define('DB_URL', getenv('DATABASE_URL'));
define('WA_TOKEN', getenv('WHATSAPP_API_TOKEN'));
define('WA_PHONE_ID', getenv('WHATSAPP_PHONE_NUMBER_ID'));
define('WA_VERIFY_TOKEN', getenv('VERIFY_TOKEN'));
define('GROQ_API_KEY', getenv('OPENAI_API_KEY'));
define('GROQ_BASE_URL', getenv('OPENAI_BASE_URL') ?: 'https://api.groq.com/openai/v1');
define('GROQ_MODEL', getenv('OPENAI_MODEL') ?: 'llama-3.3-70b-versatile');

function logger($msg) {
    $date = date('Y-m-d H:i:s');
    file_put_contents(__DIR__ . '/debug.log', "[$date] $msg\n", FILE_APPEND);
}
