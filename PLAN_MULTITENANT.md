# Plan: Salvix Multi-tenant (una webhook de Meta, muchos clientes)

> Estado: implementado. Este documento es el registro del plan acordado y aplicado.

## Objetivo

Convertir el bot en una plantilla multi-cliente: restaurantes, clínicas odontológicas y
empresas de soporte comparten **una sola app de Meta y un solo webhook**, y cada cliente
tiene su propio sistema (prompt, conocimiento, inventario, leads y panel) aislado en su
propia base de datos.

## Arquitectura

```
Cliente escribe al nº de Restaurante X ──► Meta App "Salvix" (1 webhook: webhook.php)
                                                    │
                    payload trae metadata.phone_number_id (identifica el número/cliente)
                                                    ▼
                                    webhook.php ──► resuelve tenant en tabla `tenants`
                                                    │
        ┌───────────────────────────────┬───────────────┴───────────────┐
        ▼                               ▼                               ▼
  BD "salvix_restaurante"         BD "salvix_clinica"            BD "salvix_soporte"
  (prompt, knowledge,            (prompt, knowledge,             (prompt, knowledge,
   inventario, leads)             inventario, leads)              inventario, leads)
        │                               │                               │
        └───── respuesta usando el phone_number_id de ese cliente (token global único)
```

- **Cada cliente = una base de datos separada** (mismo esquema) + su prompt + su carpeta
  `knowledge/<slug>/`. Cero cambios en las queries existentes y aislamiento total.
- **Un solo token** de Meta sirve para todos los números (misma app, System User con acceso
  a todas las WABAs).
- La clave de enrutamiento es `$value['metadata']['phone_number_id']`, siempre presente en
  el payload.

## Cambios implementados

| Archivo | Cambio |
|---|---|
| `migrations/002_tenants_table.sql` | Nueva — tabla `tenants` (registro central de clientes) |
| `tenants.php` | Nueva — `getTenantByPhoneId()`, `getTenantBySlug()`, `installTenant()`, `installTenantSchema()`, `getAllTenants()` |
| `db.php` | `getDB()` usa `$GLOBALS['TENANT']` si existe; cache PDO por `db_name`; fallback al `.env` (modo monocliente intacto) |
| `config.php` | `logger()` prefija los logs con `[tenant: slug]` |
| `whatsapp.php` | `sendAction()` / `sendWhatsAppText()` / `downloadMetaMedia()` usan el `phone_number_id` del payload y el token del tenant (o el global) |
| `webhook.php` | POST enruta por `phone_number_id` → tenant; número desconocido = log + 200 OK; handshake GET sin cambios |
| `leads.php` | `cleanReply()` usa el `cta_url` del tenant |
| `knowledge.php` | `knowledgeDir()` + `indexKnowledge()` operan sobre `knowledge/<slug>/` del tenant |
| `admin.php` | Login por tenant (`admin.php?tenant=slug`), vista **Clientes** (super admin), vistas aisladas por tenant, rebranding Salvix (blanco/negro/rojo) |
| `setup_db.php` | Instala esquema base + tabla `tenants` |
| `health.php` | Reporta nº de tenants registrados |
| `README.md` / `.env.example` | Documentación multi-tenant + rebranding |

## Rebranding

- Nombre: **Salvix** (antes "Salvix Wireless IA Agent").
- Colores: fondo negro, texto blanco, acento **rojo** `#D12424` (hover `#E03030`),
  `rgba(209,36,36,...)` para fondos/bordes/glows.

## Pasos en Meta (una vez + por cliente)

1. **Una vez:** en la app "Salvix IA" → WhatsApp → Configuration, el webhook apunta a
   `webhook.php`. Un System User con acceso a todas las WABAs (un solo token).
2. **Por cliente:** crear la WABA del cliente en el Business Manager, agregar su número,
   conectar el System User y suscribir la app a esa WABA (mismo webhook). Tomar el
   `phone_number_id` de esa WABA y registrarlo en el panel en **Clientes**.

## Seguridad

- Las credenciales viven en la BD (`tenants`), no en archivos commitables ni en `.env`.
- La BD base sigue protegida por `.htaccess`; `knowledge/` ya tiene su `.htaccess`.
- El panel por cliente solo expone los datos de ese tenant.