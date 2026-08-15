# Salvix — WhatsApp IA Agent Multi-tenant

> Plataforma PHP de atención al cliente automatizada por IA para WhatsApp Cloud API de Meta. Un solo webhook, múltiples negocios, aislamiento total por cliente.

**Salvix** es un sistema completo de **bots de WhatsApp impulsados por IA** diseñado como plantilla multi-cliente: restaurantes, clínicas odontológicas, empresas de soporte y cualquier negocio pueden tener su propio asistente virtual — con su personalidad, su base de conocimiento, su inventario y su panel de administración — compartiendo **una sola app de Meta y un solo webhook**.

---

## 📑 Índice

1. [Características](#-características)
2. [Arquitectura multi-tenant](#-arquitectura-multi-tenant)
3. [Tecnologías](#-tecnologías)
4. [Cómo funciona](#-cómo-funciona)
5. [Estructura del proyecto](#-estructura-del-proyecto)
6. [Requisitos](#-requisitos)
7. [Instalación y despliegue](#-instalación-y-despliegue)
8. [Configuración en Meta](#-configuración-en-meta)
9. [Registrar un nuevo cliente](#-registrar-un-nuevo-cliente)
10. [Panel de administración](#-panel-de-administración)
11. [Variables de entorno](#-variables-de-entorno)
12. [Base de datos](#-base-de-datos)
13. [Seguridad](#-seguridad)
14. [Personalización del prompt](#-personalización-del-prompt)
15. [Verificación de salud](#-verificación-de-salud)
16. [Solución de problemas](#-solución-de-problemas)

---

## ✨ Características

| Capacidad | Detalle |
|---|---|
| **Multi-tenant** | Un solo deploy y un solo webhook de Meta sirven a N clientes. Cada cliente tiene su propia base de datos, prompt, conocimiento, inventario y leads. |
| **Enrutamiento inteligente** | Los mensajes se identifican por el `phone_number_id` del payload de Meta y se resuelven automáticamente al cliente correcto. |
| **IA conversacional** | Integración con **Groq** (Llama 3.3) para respuestas naturales en el idioma del usuario. |
| **Visión por computadora** | El bot analiza imágenes enviadas por el cliente (modelo de visión de Groq). |
| **Transcripción de voz** | Los audios de WhatsApp se transcriben con Whisper y se responden con normalidad. |
| **RAG / Base de conocimiento** | Cada cliente sube sus documentos (.txt, .csv, .md, .docx) y el bot responde solo con información verificada. |
| **Inventario en tiempo real** | Productos y servicios con precio y stock que el bot puede ofrecer. |
| **CRM de leads** | Calificación automática de prospectos mediante marcadores `[[ACTION_LINK]]` / `[[DESCALIFICADO]]`. |
| **Panel por cliente** | Dashboard, chat en vivo con respuesta manual, logs, inventario y configuración aislados por tenant. |
| **Simulación humana** | Indicador de "escribiendo…", tiempos proporcionales y mensajes divididos en fragmentos naturales. |
| **Dedupe de mensajes** | Evita respuestas duplicadas ante reintentos de Meta (`message_id`). |

---

## 🏗 Arquitectura multi-tenant

Todos los números de todos los clientes viven bajo **una misma app de Meta** y entregan eventos a **un único webhook**. El sistema identifica al cliente por el `phone_number_id` incluido en cada payload y procesa la conversación en la base de datos de ese cliente.

```
Cliente escribe al nº de Restaurante X ──► Meta App "Salvix" (un solo webhook)
                                                 │
                         payload trae metadata.phone_number_id (identifica el número)
                                                 ▼
                                     webhook.php ──► resuelve el cliente en la tabla `tenants`
                                                 │
        ┌──────────────────────────────┬─────────┴──────────┬─────────────────────────────┐
        ▼                              ▼                    ▼                             ▼
  BD "salvix_restaurante"        BD "salvix_clinica"   BD "salvix_soporte"         BD base
  prompt + knowledge +           prompt + knowledge    prompt + knowledge          tabla `tenants`
  inventario + leads + msgs      inventario + leads    inventario + leads          (registro central)
        │                              │                    │
        └────── respuesta usando el phone_number_id de ese cliente (token global único)
```

**Principios de diseño:**

- **Un cliente = una base de datos** con el mismo esquema. Aislamiento total de datos y cero riesgo de mezclar conversaciones.
- **Un solo token de Meta** (System User con acceso a todas las WABAs) sirve para enviar mensajes de todos los números. Opcionalmente, un cliente puede tener su propio token (`wa_token`).
- **El modo monocliente sigue funcionando**: sin clientes registrados, el sistema se comporta como el bot clásico usando las variables del `.env`.

---

## 🛠 Tecnologías

| Componente | Tecnología |
|---|---|
| Lenguaje | PHP 8.2+ |
| Base de datos | MySQL 5.7+ / 8.x (MariaDB compatible) |
| IA | Groq API (compatible con OpenAI SDK) — Llama 3.3, Whisper, visión |
| Mensajería | WhatsApp Cloud API de Meta (Graph API v25.0) |
| RAG | Búsqueda full-text local en MySQL (`MATCH ... AGAINST`) |
| Servidor | Apache / Nginx + PHP-FPM (hosting compartido compatible) |

---

## ⚙️ Cómo funciona

### Flujo de un mensaje

1. Un cliente de WhatsApp escribe al número de uno de tus clientes.
2. Meta envía el evento al webhook (`webhook.php`) con el `phone_number_id` del número receptor.
3. **Handshake (GET):** Meta verifica la URL con `hub_verify_token` antes de suscribirla.
4. **Procesamiento (POST):**
   - El sistema resuelve el tenant por `phone_number_id` en la tabla `tenants` (BD base).
   - Si el número no está registrado, se registra en el log y se responde `200 OK` (Meta nunca reintenta).
   - Dedupe: si el `message_id` ya se procesó, se omite (protección ante reintentos).
   - Se responde `200 OK` de inmediato y se procesa en segundo plano (`fastcgi_finish_request`).
5. **Tipos de mensaje:** texto → IA; imagen → descarga + análisis de visión; audio → descarga + transcripción Whisper.
6. **Construcción del prompt del sistema:** prompt del cliente (BD) + fragmentos relevantes del RAG + inventario con stock.
7. **Generación de respuesta** con Groq, incluyendo historial de los últimos 10 mensajes.
8. **Leads:** si la respuesta contiene `[[ACTION_LINK]]` / `[[DESCALIFICADO]]`, se procesa el lead con IA y se reemplaza el marcador por el enlace CTA del cliente.
9. **Envío natural:** "escribiendo…", espera proporcional al largo y fragmentos de ≤200 caracteres.
10. La conversación se persiste en la BD del cliente.

### Marcadores del prompt

| Marcador | Efecto |
|---|---|
| `[[ACTION_LINK]]` | Se reemplaza por el enlace CTA del cliente y marca el lead como **calificado**. |
| `[[AGENDA_LINK]]` | Alias de `[[ACTION_LINK]]` (agendamiento). |
| `[[DESCALIFICADO: motivo]]` | Elimina el marcador del mensaje visible y registra la descalificación. |

---

## 📁 Estructura del proyecto

```
├── webhook.php               # Webhook único de Meta (handshake + enrutamiento multi-tenant)
├── tenants.php               # Registro central de clientes (CRUD + instalación de BD)
├── config.php                # Carga del .env + constantes globales + logger
├── db.php                    # Conexiones PDO (base y por tenant)
├── whatsapp.php              # Cliente de la Cloud API (envío, acciones, descarga de medios)
├── openai.php                # Cliente Groq (chat, visión, transcripción) + prompt builder
├── knowledge.php             # RAG: indexado y búsqueda full-text por tenant
├── leads.php                 # Detección/extracción de leads + limpieza de marcadores
├── admin.php                 # Panel de administración (super admin + por cliente)
├── health.php                # Endpoint de salud (estado de BD, PHP y tenants)
├── setup_db.php              # Instalador de esquema base + tabla tenants
├── enviar_prueba.php         # Herramienta de prueba para validación ante Meta
├── fix_db.php                # Utilidad de reparación de esquema
├── prompts/
│   └── system.example.md     # Prompt genérico de ejemplo (semilla para nuevos clientes)
├── knowledge/                # Documentos por cliente: knowledge/<slug>/
├── migrations/
│   ├── run_migrations.php    # Runner de migraciones por URL
│   ├── 001_settings_table.sql
│   └── 002_tenants_table.sql # Tabla del registro central de clientes
├── .env.example              # Plantilla de configuración
└── img/                      # Recursos gráficos (logo)
```

---

## 📋 Requisitos

- **PHP 8.2+** con extensiones: `pdo_mysql`, `curl`, `fileinfo`, `zip`, `mbstring`, `openssl`.
- **MySQL 5.7+** (el usuario de BD necesita privilegios de `CREATE DATABASE` para instalar clientes).
- **HTTPS** obligatorio (Meta exige webhook por TLS).
- Una **app de Meta** con WhatsApp Cloud API activado (sistema de test o producción).
- Clave API de **Groq** (gratuita en console.groq.com).

---

## 🚀 Instalación y despliegue

### Opción A — Local (XAMPP / Laragon)

```bash
# 1. Clonar y entrar
git clone <repo> "bot-whatsapp"
cd bot-whatsapp

# 2. Configurar el .env (ver sección Variables de entorno)
cp .env.example .env
```

3. Inicia Apache y MySQL.
4. Crea la BD base en phpMyAdmin y ajusta `DB_NAME/DB_USER/DB_PASS` en `.env`.
5. Ejecuta el instalador: `http://localhost/bot-whatsapp/setup_db.php`
6. Verifica: `http://localhost/bot-whatsapp/health.php`

> Para exponer el webhook local a Meta usa un túnel HTTPS (ngrok, cloudflared, etc.) apuntando a `https://tu-tunel/webhook.php`.

### Opción B — Producción (hosting compartido o VPS)

1. Sube el código a `public_html` (o el DocumentRoot).
2. Asegura que `.env` no sea accesible vía web (el `.htaccess` incluido lo protege).
3. Crea la BD base y configura `.env`.
4. Ejecuta `https://tudominio.com/setup_db.php` una vez.
5. (Alternativa) Ejecuta el runner de migraciones: `https://tudominio.com/migrations/run_migrations.php`
6. Registra el webhook en Meta (sección siguiente).
7. Verifica `/health.php` y el handshake del webhook.

### Opción C — Contenedores (Docker / Coolify)

- Imagen base: `php:8.2-apache` con `pdo_mysql`, `curl`, `zip`, `fileinfo` habilitados.
- Servicio MySQL/PG separado con volumen persistente.
- Configura las variables de entorno en el panel del proveedor y ejecuta `setup_db.php` tras el primer arranque.

---

## 🔧 Configuración en Meta

### Una sola vez (plataforma)

1. En **developers.facebook.com**, crea (o usa) la app **Salvix**.
2. Agrega el producto **WhatsApp** → activa la Cloud API.
3. En *WhatsApp → Configuration*:
   - **Callback URL:** `https://tudominio.com/webhook.php`
   - **Verify token:** el mismo valor de `VERIFY_TOKEN` en tu `.env`
4. Suscríbete al campo **`messages`** del webhook.
5. Crea un **System User** en tu Business Manager y agrégalo a **todas** las WABAs de tus clientes con permisos `whatsapp_business_messaging` y `whatsapp_business_management`. Su token es el `WHATSAPP_API_TOKEN` global.

### Por cada cliente

1. En tu Business Manager, crea la **WABA del cliente** y agrega su número de teléfono (verificado).
2. Conecta el System User de la app a esa WABA (así el token global cubre ese número).
3. Suscríbete la app a los eventos de esa WABA — **el webhook sigue siendo el mismo**.
4. Obtén el **`phone_number_id`** del número (Business Manager → WhatsApp → Configuración del número, o Graph Explorer con `GET /<WABA_ID>/phone_numbers`).
5. Registra al cliente en el panel (ver siguiente sección).

---

## 🏢 Registrar un nuevo cliente

1. Entra al panel como **super admin** (`admin.php` con las credenciales del `.env`).
2. Ve a **Clientes → Registrar Nuevo Cliente** y completa:
   - **Slug** único (ej. `restaurante_x`).
   - **Nombre del negocio**.
   - **Phone Number ID** de Meta (clave de enrutamiento).
   - **WABA ID** (referencia opcional).
   - Credenciales de su base de datos (si se deja vacío el nombre, se crea `salvix_<slug>`).
   - Usuario/contraseña del panel del cliente.
   - **Enlace CTA** (agendamiento o WhatsApp del equipo).
   - Token propio opcional (en blanco = usa el token global).
3. Pulsa **Crear e Instalar Cliente**: el sistema crea la BD, instala el esquema, siembra el prompt de ejemplo y crea `knowledge/<slug>/`.
4. El cliente accede a su panel en `https://tudominio.com/admin.php?tenant=restaurante_x`.

> Los clientes no se registran solos: el super admin es quien crea y administra la plataforma.

---

## 🖥 Panel de administración

### Super admin (sin `?tenant=`)

| Vista | Función |
|---|---|
| Dashboard | KPIs globales y conversaciones de la BD base |
| **Clientes** | Registrar, editar, eliminar e ingresar a cada cliente |
| Leads / Inventario / Conocimiento | Gestión de la BD base (modo monocliente) |
| Bot Config | Prompt base |
| APIs & Tokens | Credenciales globales (.env) |
| Logs | Log completo del sistema |

### Panel por cliente (`admin.php?tenant=<slug>`)

| Vista | Función |
|---|---|
| Dashboard | Mensajes, leads y conversaciones de **ese** cliente |
| Leads | Prospectos calificados por la IA |
| Inventario | Productos/servicios con precio y stock que ofrece el bot |
| Conocimiento | Subir documentos a `knowledge/<slug>/` y sincronizar el índice RAG |
| Bot Config | Editar el prompt del bot **de ese cliente** o generarlo con IA |
| Logs | Solo los eventos de ese cliente (`[tenant: <slug>]`) |

Desde el chat de una conversación se puede **responder manualmente** al cliente por WhatsApp.

---

## 🔑 Variables de entorno

```env
# --- BASE DE DATOS (BD base: registro de clientes + modo monocliente) ---
DB_HOST=localhost
DB_NAME=salvix_db
DB_USER=tu_usuario
DB_PASS=tu_password

# --- WHATSAPP CLOUD API ---
WHATSAPP_API_TOKEN=tu_token_aqui          # Token global (System User con todas las WABAs)
WHATSAPP_PHONE_NUMBER_ID=tu_id_aqui       # Respaldar en modo monocliente
VERIFY_TOKEN=salvix_token_2026            # Debe coincidir con Meta

# --- INTELIGENCIA ARTIFICIAL (GROQ) ---
GROQ_API_KEY=tu_api_key_groq
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_BASE_URL=https://api.groq.com/openai/v1

# --- ADMIN (super admin) ---
ADMIN_USER=salvix_admin
ADMIN_PASSWORD=salvix_2026_secure

# --- CTA por defecto (modo monocliente) ---
QUALIFIED_CTA_URL=https://tu-enlace-de-agendamiento.com
```

| Variable | Obligatoria | Descripción |
|---|---|---|
| `DB_*` | ✅ | Conexión a la BD base |
| `WHATSAPP_API_TOKEN` | ✅ | Token global de la Cloud API |
| `WHATSAPP_PHONE_NUMBER_ID` | ⚠️ | Solo necesario en modo monocliente |
| `VERIFY_TOKEN` | ✅ | Token de verificación del webhook |
| `GROQ_API_KEY` / `GROQ_MODEL` / `GROQ_BASE_URL` | ✅ | IA conversacional |
| `ADMIN_USER` / `ADMIN_PASSWORD` | ✅ | Acceso super admin |
| `QUALIFIED_CTA_URL` | ⚠️ | CTA por defecto (monocliente) |

---

## 🗄 Base de datos

### BD base (`DB_NAME` del `.env`)

| Tabla | Propósito |
|---|---|
| `tenants` | Registro central de clientes (slug, nombre, phone_number_id, credenciales de BD, admin, CTA, token) |
| `messages`, `leads`, `inventory`, `knowledge_chunks`, `settings` | Datos del modo monocliente / panel base |

### BD de cada cliente (`salvix_<slug>`)

| Tabla | Propósito |
|---|---|
| `messages` | Historial de conversaciones (con `message_id` para dedupe) |
| `leads` | Prospectos y su estado de calificación |
| `inventory` | Productos/servicios con precio y stock |
| `knowledge_chunks` | Fragmentos indexados del RAG (full-text) |
| `settings` | Prompt del bot del cliente (`system_prompt`) |

Las migraciones se registran en `_migrations` y se aplican una sola vez vía `migrations/run_migrations.php`.

---

## 🔒 Seguridad

- **Credenciales en BD, nunca en el repositorio:** los datos de conexión de cada cliente viven en la tabla `tenants`, no en archivos de código.
- **`.env` protegido** por `.htaccess` y en `.gitignore`.
- **Aislamiento por cliente:** cada tenant solo ve su BD, sus logs y su panel.
- **El panel por cliente** no accede a credenciales globales (la vista *APIs & Tokens* solo existe para el super admin).
- **Sin secretos en el código:** antes de cada commit se escanea el repositorio en busca de tokens reales.
- **Logs de depuración** (`debug.log`) excluidos de git.

---

## 🧠 Personalización del prompt

El comportamiento de cada bot se define en su prompt (`settings.system_prompt` de su BD), editable desde **Bot Config**:

1. **Manual:** pega o edita el prompt directamente.
2. **Auto-generado:** describe el negocio en una frase y la IA redacta el prompt completo.

Reglas recomendadas del prompt:

- Define el rol del bot (ej. *"Eres el asistente virtual de la Clínica Dental Sonrisa"*).
- Prohíbe inventar información: responder solo con el prompt, el RAG o el inventario.
- Indica cuándo usar `[[ACTION_LINK]]` (intención de compra/agendamiento) y `[[DESCALIFICADO: motivo]]`.
- Estilo: mensajes cortos, lenguaje coloquial, una pregunta a la vez.

---

## 🩺 Verificación de salud

`https://tudominio.com/health.php` devuelve JSON con:

- Estado de PHP y versión.
- Estado de la conexión a la BD base.
- Lista de clientes registrados con su `phone_number_id`.

---

## 🔍 Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| Meta no suscribe el webhook (403) | `VERIFY_TOKEN` distinto | Iguala el token en `.env` y en la configuración de Meta |
| Mensaje ignorado sin respuesta | `phone_number_id` no registrado | Registra el cliente en *Clientes* con el ID exacto |
| "Lo siento, tengo problemas…" | Clave Groq inválida o cuota agotada | Revisa `GROQ_API_KEY` y el log |
| El bot responde con info inventada | Prompt sin reglas RAG | Usa el prompt de ejemplo y sube documentos de knowledge |
| Respuestas duplicadas | — | El dedupe por `message_id` ya lo previene |
| No llegan eventos de un número | WABA no suscrita a la app | Suscríbete la app a esa WABA (mismo webhook) |
| Error al crear cliente | Usuario BD sin privilegios `CREATE` | Usa una cuenta con permisos de creación de BD |

**Diagnóstico:** el panel *Logs* (con filtro por cliente) y `debug.log` registran cada paso: payloads crudos, resolución de tenant, llamadas a la IA y envíos.

---

## 📄 Licencia

Proyecto privado — **Salvix · SalvaNova Solutions** · © 2026. Todo el código y la documentación son de uso exclusivo del propietario. No redistribuir sin autorización.