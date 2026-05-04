# Changelog

## v0.2.0 — 2026-05-04

### Cambios

- Endpoint de consultas migrado a `ajax/ask.php`, integrado al routing nativo de GLPI 11.
- Página de configuración reescrita con el sistema de templates de GLPI (`Html::header`, `Html::closeForm`, `Html::footer`); el formulario se renderiza dentro del layout de la aplicación con clases Bootstrap propias de GLPI.
- Validación CSRF integrada para el formulario de configuración (vía `Session::checkCSRF`) y para el endpoint de consultas (header `X-Glpi-Csrf-Token` enviado por el cliente).
- Acceso a base de datos a través del wrapper `$DB` de GLPI; eliminada la lectura directa de `config/config_db.php`.
- Eliminada la configuración auxiliar de Apache. El plugin se instala copiando el directorio y habilitándolo desde la interfaz, sin pasos adicionales en el servidor web.

### Features (sin cambios desde 0.1.0)

- Tres modos de operación: `local` (KB de GLPI + docs + xAI), `n8n` (webhook externo), `both` (combinado con deduplicación de fuentes).
- Búsqueda local con scoring ponderado: coincidencia en `name` 3× sobre `answer`, pesos proporcionales a la longitud de la palabra.
- Snippets de hasta 8000 caracteres por item para respuestas completas.
- Renderizado Markdown en el widget cliente (negritas, listas, código inline, enlaces) con escape HTML.
- Campo de API key de xAI write-only — el valor cargado se muestra enmascarado y nunca se incluye en el HTML.

## v0.1.0 — 2026-05-04

Primera release pública.

### Features

- Burbuja flotante inyectada en todas las páginas de GLPI 11 vía `add_javascript`.
- Modos `local`, `n8n` y `both`.
- Búsqueda local con scoring por palabra.
- Mini-parser Markdown en el widget cliente.
- Página de configuración con xAI key enmascarada.
- Credenciales de DB leídas en runtime desde la configuración de GLPI.
