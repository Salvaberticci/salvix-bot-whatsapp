# WhatsApp Bot Template

Plantilla publica para crear y desplegar un chatbot de WhatsApp con:

- FastAPI
- WhatsApp Cloud API de Meta
- OpenAI
- Postgres
- Panel admin con login, conversaciones, CRM, dashboard y escalaciones
- Deploy en Coolify
- Instrucciones para Claude Code y OpenAI Codex

## 1. Configuracion local

```bash
cp .env.example .env
```

Rellena `.env` con tus valores reales. No subas `.env` a GitHub.

Variables principales:

- `WHATSAPP_API_TOKEN`: token de Meta WhatsApp Cloud API.
- `WHATSAPP_PHONE_NUMBER_ID`: Phone Number ID de tu numero de WhatsApp.
- `WEBHOOK_DOMAIN`: dominio publico del bot, por ejemplo `https://bot.tudominio.com`.
- `VERIFY_TOKEN`: texto secreto inventado por ti para validar el webhook en Meta.
- `OPENAI_API_KEY`: API key de OpenAI.
- `ADMIN_USER`, `ADMIN_PASSWORD`, `SESSION_SECRET`: acceso al panel `/admin`.
- `QUALIFIED_CTA_URL`: link opcional para leads calificados.

## 2. Personaliza el bot

Edita [prompts/system.md](prompts/system.md). Reemplaza los placeholders con la identidad,
tono, reglas y criterios del negocio.

Si necesitas cargar FAQs, catalogo, politicas o respuestas frecuentes, crea archivos `.md` o
`.txt` dentro de:

```text
prompts/knowledge/
```

## 3. Ejecutar localmente

Necesitas Postgres y las variables de `.env` listas.

```bash
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8000
```

Endpoints:

- `GET /health`
- `GET /webhook`
- `POST /webhook`
- `POST /reload`
- `POST /maintenance/reset-contact`
- `GET /admin`

## 4. Deploy con Claude Code

Abre esta carpeta con Claude Code y pide:

```text
despliega mi bot de WhatsApp en Coolify
```

Claude usara el skill incluido en `.claude/skills/whatsapp-bot-deployer/`.

## 5. Deploy con OpenAI Codex

Abre esta carpeta con Codex. Las instrucciones del repo estan en [AGENTS.md](AGENTS.md).

Si quieres usar el skill de Codex, copia o referencia:

```text
.codex/skills/whatsapp-bot-deployer/
```

Para MCP en Codex, usa como base:

```text
.codex/config.example.toml
```

## 6. Conectar Meta

Cuando el despliegue este listo, configura el webhook en Meta:

- Callback URL: `https://TU_DOMINIO/webhook`
- Verify token: el valor de `VERIFY_TOKEN`
- Webhook field: `messages`

## Seguridad

Antes de publicar este template:

```bash
rg -n "sk-|ghp_|EAA|token_real|password_real|secret_real|api_key_real" .
```

No deben existir secretos reales. Los archivos `.env`, `.mcp.json`, `META_SETUP.md` y
`execution/` estan ignorados porque pueden contener credenciales.
