---
name: whatsapp-bot-deployer
description: Deploy a generic WhatsApp Cloud API bot template to Coolify. Use when Codex needs to deploy, publish, configure, or troubleshoot a WhatsApp bot on Coolify with FastAPI, Postgres, OpenAI, GitHub, Meta webhook verification, public HTTPS domain, environment variables, MCP, and admin panel credentials.
---

# WhatsApp Bot Deployer

Use this skill to deploy the template to Coolify and connect Meta WhatsApp webhooks.

## Required env

Validate these values in `.env` before deploy:

```text
GITHUB_TOKEN
COOLIFY_URL
COOLIFY_TOKEN
COOLIFY_PROJECT_UUID
WHATSAPP_API_TOKEN
WHATSAPP_PHONE_NUMBER_ID
WEBHOOK_DOMAIN
VERIFY_TOKEN
OPENAI_API_KEY
ADMIN_USER
ADMIN_PASSWORD
SESSION_SECRET
```

If `DATABASE_URL` is empty, provision Postgres in Coolify and set the internal connection URL.
If `RELOAD_TOKEN` is empty, generate one with a cryptographic random value.

## Deploy steps

1. Inspect `.env` without exposing secrets.
2. Check `prompts/system.md`; ask for customization only if placeholders would make the bot unusable.
3. Create or reuse a private GitHub repository.
4. Push only sanitized project files.
5. Use Coolify MCP or API to create Postgres and a public application.
6. Configure:
   - FQDN: `WEBHOOK_DOMAIN`
   - Dockerfile: `/Dockerfile`
   - Healthcheck: `/health`
   - Port: `8000`
7. Add app env vars.
8. Deploy and inspect logs until healthy.
9. Test public `/health`.
10. Test Meta verification endpoint with the configured verify token.
11. Give the user the Callback URL and Verify Token for Meta.

## Secret policy

Never commit or print full values from `.env`, `.mcp.json`, `META_SETUP.md`, or `execution/`.
Before git operations, run a secret scan and stop if real credentials appear.

Keep this skill reusable. Add external integrations only in private project variants.
