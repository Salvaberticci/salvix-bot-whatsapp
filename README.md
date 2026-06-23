# Salvix Wireless IA Agent WhatsApp Bot (PHP Version)

Plataforma de automatización de WhatsApp basada en PHP, diseñada para correr eficientemente en hosting compartido (como Namecheap) y conectar con modelos de IA de última generación.

## 🚀 Características

- **Motor PHP 8.x**: Ejecución rápida y compatible con cualquier hosting.
- **IA Integrada**: Conexión con **Groq (Llama 3.3)** para respuestas inteligentes y naturales.
- **Base de Datos**: Persistencia en **PostgreSQL** (optimizado para Render).
- **Dashboard Admin**: Panel de control para visualizar KPIs y conversaciones en tiempo real.
- **CRM de Leads**: Calificación automática de prospectos mediante marcadores de IA.

## 🛠️ Tecnologías

- **Lenguaje**: PHP 8.2+
- **Base de Datos**: PostgreSQL
- **IA**: Groq API (Compatible con OpenAI SDK)
- **WhatsApp**: Cloud API de Meta

## 📦 Instalación

1. Clona el repositorio.
2. Sube los archivos a tu servidor.
3. Configura el archivo `.env` con tus credenciales.
4. Ejecuta `setup_db.php` una vez para inicializar las tablas.
5. Configura el Webhook en Meta hacia `webhook.php`.

## 🔒 Seguridad

El proyecto incluye un sistema de protección para el archivo `.env` mediante `.htaccess` y está preparado para no exponer datos sensibles en repositorios públicos.

---
*Desarrollado por Salvix Wireless IA Agent - 2026*
