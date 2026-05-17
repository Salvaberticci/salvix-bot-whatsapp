<?php
require_once __DIR__ . '/db.php';

/**
 * Función para indexar todos los archivos en /knowledge hacia la DB
 */
function indexKnowledge() {
    $pdo = getDB();
    $knowledgeDir = __DIR__ . '/knowledge';
    
    // Limpiar índice anterior
    $pdo->exec("DELETE FROM knowledge_chunks");
    
    if (!is_dir($knowledgeDir)) return 0;
    
    $files = array_diff(scandir($knowledgeDir), array('.', '..', '.htaccess'));
    $count = 0;

    foreach ($files as $file) {
        $filePath = $knowledgeDir . '/' . $file;
        $content = "";
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        if ($ext === 'txt' || $ext === 'md' || $ext === 'csv') {
            $content = file_get_contents($filePath);
        } elseif ($ext === 'docx') {
            $content = readDocx($filePath);
        }

        if (empty($content)) continue;

        // Dividir el contenido en fragmentos (por párrafos o bloques de texto)
        $chunks = preg_split('/\n\s*\n/', $content); // Divide por doble salto de línea

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if (strlen($chunk) < 20) continue; // Ignorar fragmentos muy cortos

            $stmt = $pdo->prepare("INSERT INTO knowledge_chunks (source_file, content) VALUES (?, ?)");
            $stmt->execute([$file, $chunk]);
            $count++;
        }
    }
    
    return $count;
}

/**
 * Busca los fragmentos más relevantes basados en la pregunta del usuario
 */
function searchKnowledge($query, $limit = 5) {
    $pdo = getDB();
    
    // Limpiar la query de caracteres especiales para el buscador
    $cleanQuery = preg_replace('/[+\-><\(\)~*\"@]/', ' ', $query);
    
    // Búsqueda Full-Text (FTS) - Hardcoded LIMIT to prevent SQL errors on string binding
    $stmt = $pdo->prepare("SELECT content, source_file, 
                           MATCH(content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance 
                           FROM knowledge_chunks 
                           WHERE MATCH(content) AGAINST(? IN NATURAL LANGUAGE MODE)
                           ORDER BY relevance DESC 
                           LIMIT 5");
    $stmt->execute([$cleanQuery, $cleanQuery]);
    
    return $stmt->fetchAll();
}

/**
 * Extraer texto de un .docx (ZIP comprimido)
 */
function readDocx($filename) {
    $zip = new ZipArchive();
    if ($zip->open($filename)) {
        $xml = $zip->getFromName("word/document.xml");
        $zip->close();
        if ($xml) {
            return strip_tags($xml);
        }
    }
    return "";
}
