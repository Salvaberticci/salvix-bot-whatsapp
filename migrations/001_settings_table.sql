-- 001_settings_table.sql
-- Crea la tabla settings para almacenar configuraciones del sistema
-- como el system prompt del bot, evitando conflictos de git con archivos.

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
