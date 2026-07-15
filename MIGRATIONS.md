# MIGRATIONS — Salvix Wireless IA Agent

Registro de todos los cambios implementados en el sistema.

---

## Migración 001 — Settings table + Prompt en BD

**Archivo:** `migrations/001_settings_table.sql`
**Estado:** ✅ Aplicada vía `setup_db.php` y `migrations/run_migrations.php`

### Cambios realizados

| Archivo | Cambio |
|---|---|
| `migrations/001_settings_table.sql` | Nueva — CREATE TABLE `settings` |
| `migrations/run_migrations.php` | Nueva — Runner de migraciones por URL |
| `setup_db.php` | Agregada tabla `settings` al instalador |
| `openai.php` | `buildSystemPrompt()` lee de BD en vez de archivo (`custom.md`) |
| `admin.php` | Guardado y carga del system prompt desde BD en vez de archivo |
| `.gitignore` | Se agregó `prompts/custom.md` (ya no se usa) |

### Motivo

Eliminar los conflictos de git causados por `prompts/system.md`. Al guardar el prompt en la base de datos, cada servidor tiene su propia versión sin interferir con el repositorio.

### Cómo aplicar en servidor nuevo

```
http://tudominio.com/migrations/run_migrations.php
```

O simplemente ejecutar `setup_db.php` que ya incluye la tabla.

---

## Migración 000 — Rebranding + UI + Naturalidad

**Cambios manuales** (anteriores al sistema de migraciones).

### Rebranding: "Salvix" → "Salvix Wireless IA Agent"

| Archivo | Cambio |
|---|---|
| `admin.php` | Títulos y sidebar actualizados |
| `privacidad.html` | Nombre de la empresa actualizado |
| `openai.php` | Fallback prompt actualizado |
| `setup_db.php` | Mensaje de éxito actualizado |
| `prompts/system.md` | Referencia a tecnología actualizada |
| `README.md` | Título y footer actualizados |

### Tema visual: Rojo → Azul celeste

| Archivo | Cambio |
|---|---|
| `admin.php` | `--accent`: `#D12424` → `#38bdf8` |
| `admin.php` | `--accent-hover`: `#E03030` → `#7dd3fc` |
| `admin.php` | `--danger`: `#D12424` → `#38bdf8` |
| `admin.php` | Todos los `rgba(209,36,36,...)` → `rgba(56,189,248,...)` |
| `admin.php` | Todos los `rgba(239,68,68,...)` → `rgba(56,189,248,...)` |
| `admin.php` | Texto error: `#fca5a5` → `#bae6fd` |
| `admin.php` | Gradientes, glows, sombras, bordes actualizados |

### Logo: Texto "S" → Imagen

| Archivo | Cambio |
|---|---|
| `admin.php` | Login logo: `<div class="login-logo">S</div>` → `<img src="img/logo.png">` |
| `admin.php` | Sidebar logo: `<div class="sidebar-logo">S</div>` → `<img src="img/logo.png">` |
| `admin.php` | CSS `.login-logo` simplificado (sin gradient ni box-shadow) |
| `admin.php` | CSS `.sidebar-logo` simplificado con `overflow: hidden` |
| `img/logo.png` | Nueva — logo de la marca |

### Tamaño del logo

| Archivo | Cambio |
|---|---|
| `admin.php` | Login logo: `56px` → `280px` (5x más grande) |

### Naturalidad en respuestas

| Archivo | Cambio |
|---|---|
| `whatsapp.php` | Nueva función `sendAction()` para enviar `typing_on`/`typing_off` a WhatsApp |
| `webhook.php` | Antes de enviar respuesta: muestra "escribiendo..." y espera 2-7 segundos (según largo del mensaje) |
| `prompts/system.md` | Nuevas reglas: mensajes de 2-3 oraciones máximo, sin listas ni viñetas, lenguaje coloquial |

### Gestión de prompts (archivo → BD)

| Archivo | Cambio |
|---|---|
| `openai.php` | `buildSystemPrompt()` ahora lee de `settings` (key `system_prompt`) |
| `admin.php` | Guardado de instrucciones usa `INSERT ... ON DUPLICATE KEY UPDATE` en `settings` |
| `admin.php` | AI Prompt Generator guarda en `settings` en vez de archivo |
| `admin.php` | Carga del prompt en el editor lee de `settings` |
| `.gitignore` | Se agregó `prompts/custom.md` |

### Sistema de migraciones

| Archivo | Cambio |
|---|---|
| `migrations/run_migrations.php` | Nueva — runner vía URL que ejecuta `.sql` pendientes |
| `migrations/001_settings_table.sql` | Nueva — CREATE TABLE `settings` |

---

## Cómo ejecutar migraciones en producción

### Opción 1: Vía URL (recomendado)

Acceder desde el navegador:

```
https://tudominio.com/migrations/run_migrations.php
```

### Opción 2: Via setup_db.php

Si es una instalación nueva:

```
https://tudominio.com/setup_db.php
```

### Opción 3: SSH (si está disponible)

```bash
cd /home/usuario/public_html
php migrations/run_migrations.php
```

---

## Notas

- Las migraciones se registran en la tabla `_migrations` y solo se ejecutan una vez.
- Si una migración falla, se reporta el error pero las demás continúan.
- Para crear una nueva migración: `migrations/002_descripcion.sql`.
