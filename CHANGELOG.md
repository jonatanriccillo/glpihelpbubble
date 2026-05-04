# Changelog

## v0.1.0 — 2026-05-04

Primera release pública.

### Features

- Burbuja flotante (FAB + panel) inyectada en todas las páginas de GLPI 11 vía hook `add_javascript`.
- Tres modos de operación configurables: `local` (KB de GLPI + docs + xAI Grok), `n8n` (webhook externo), `both` (combinados con dedupe de sources).
- Búsqueda local con scoring: match en `name` 3x, palabras largas pesan más.
- Snippets de hasta 8000 caracteres pasados a Grok para respuestas completas sin cortes.
- Mini-parser Markdown en el widget JS: negritas, listas, código inline, links clickeables, sin XSS.
- Página de configuración embebida en el layout de GLPI (vía `front/config.form.php` con iframe).
- API key xAI con input enmascarado tipo password — la key nunca aparece en el HTML source.
- Credenciales de DB leídas en runtime de `<glpi-root>/config/config_db.php` (cero hardcode).
- Workaround para el bug del firewall de GLPI 11 (issue #21414): endpoints servidos por Apache vía Alias.

### Sintaxis del payload n8n

El plugin envía:

```json
{ "question", "session_id", "user_id", "user_name", "entity_id", "profile_id", "page" }
```
