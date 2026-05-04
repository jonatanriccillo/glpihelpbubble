# HelpBubble — Plugin GLPI 11

Asistente flotante para GLPI 11. Inyecta una burbuja de chat en todas las páginas de la app: el usuario hace una pregunta en lenguaje natural y recibe una respuesta resumida con links a las fuentes.

Tres modos de operación, configurables desde la UI:

| Modo | Qué hace |
|------|----------|
| `local` | Busca en la KB de GLPI (`glpi_knowbaseitems`) + archivos `.md`/`.txt` en `docs/`, manda el contexto a [xAI Grok](https://x.ai/) y devuelve `{answer, sources}`. |
| `n8n` | Reenvía la pregunta a un webhook de [n8n](https://n8n.io/) externo (típicamente un workflow con su propio RAG) y devuelve la respuesta tal cual. |
| `both` | Ejecuta los dos en paralelo, combina las respuestas con headers (`**Base de conocimiento de GLPI**` / `**Documentación externa**`) y mergea sources con dedupe por URL. Si solo uno encuentra info, devuelve solo ese. |

## Requisitos

- GLPI **11.0.x**
- PHP **8.1+** con extensiones `mysqli`, `curl`, `mbstring`, `json`
- MariaDB / MySQL (la que ya use GLPI)
- Acceso para configurar un Alias en Apache (para el workaround del firewall — ver más abajo)
- (Modo `local`) Una API key de xAI
- (Modo `n8n`) Un webhook n8n que acepte el payload descripto abajo

## Instalación

### 1. Copiar los archivos al directorio de plugins de GLPI

```bash
cp -r helpbubble /var/www/html/glpi/plugins/
chown -R www-data:www-data /var/www/html/glpi/plugins/helpbubble
```

En instalaciones Docker, el volumen del contenedor es típicamente `<glpi-volume>/_data/plugins/`.

### 2. Configurar el Alias de Apache (workaround firewall GLPI 11)

GLPI 11 introdujo un firewall que bloquea POSTs a scripts legacy de plugins (issue [glpi-project/glpi#21414](https://github.com/glpi-project/glpi/issues/21414)). Para evitarlo, los endpoints HTTP del plugin (`api/ask.php`, `api/config.php`) se sirven directamente por Apache, fuera del routing de GLPI.

Copiar `apache/helpbubble.conf` a `/etc/apache2/conf-available/` del servidor/contenedor y activarlo:

```bash
cp apache/helpbubble.conf /etc/apache2/conf-available/helpbubble.conf
a2enconf helpbubble
apachectl graceful
```

El Alias mapea `/helpbubble-api/` → `<glpi-root>/plugins/helpbubble/api/`.

> Cuando GLPI 11 corrija el bug del firewall, los endpoints podrán moverse a `ajax/` y este Alias dejará de ser necesario.

### 3. Activar el plugin desde GLPI

En la UI: **Configuración → Plugins → Instalar HelpBubble → Habilitar**. Eso crea la tabla `glpi_plugin_helpbubble_config` con los defaults.

### 4. Configurar

Click en la **rueditas** que aparece al lado del plugin en la lista. La página de config se ve embebida dentro del layout de GLPI. Permite editar:

- **Modo de operación**: `local` / `n8n` / `both`
- **URL del webhook n8n** (solo se usa con `n8n` o `both`)
- **API Key xAI** (solo se usa con `local` o `both`) — campo enmascarado, dejá vacío para mantener la actual
- **Modelo xAI** (default: `grok-4-1-fast-non-reasoning`)

## Cómo funciona el flujo

1. El usuario tipea en la burbuja → JS hace POST a `/helpbubble-api/ask.php`.
2. Apache sirve `api/ask.php` directo (no pasa por el routing de GLPI).
3. PHP lee credenciales de DB de `<glpi-root>/config/config_db.php` (no hay nada hardcodeado).
4. Carga la config de la tabla `glpi_plugin_helpbubble_config`.
5. Según el modo:
   - **local**: busca con scoring en KB + docs locales; arma contexto; llama a Grok; parsea JSON.
   - **n8n**: arma payload completo; POST al webhook; devuelve la respuesta tal cual.
   - **both**: corre los dos y combina.
6. Devuelve `{answer, sources}` al widget.

### Ranking de la búsqueda local

El plugin no usa simplemente `LIMIT 5` sin orden. Para cada palabra significativa de la pregunta:

- Match en `name` vale **3x**, match en `answer` vale **1x**.
- Las palabras más largas pesan proporcionalmente más (heurística: las palabras genéricas son cortas, las específicas son largas).
- Score acumulado por item, devolvemos top 5.

### Payload del webhook n8n

```json
{
   "question":   "como configuro thunderbird con office 365",
   "session_id": "uuid-del-browser",
   "user_id":    42,
   "user_name":  "jdoe",
   "entity_id":  0,
   "profile_id": 4,
   "page":       "/front/ticket.php"
}
```

El webhook tiene que devolver:

```json
{
   "answer":  "Texto en Markdown...",
   "sources": [{ "title": "...", "url": "..." }]
}
```

(También aceptamos `file_name`/`file_url` como aliases de `title`/`url` para compatibilidad con flows que ya están así.)

## Arquitectura

```
helpbubble/
├── setup.php               # plugin_init_helpbubble + version + check_config
├── hook.php                # install/uninstall: crea tabla + defaults
├── helpbubble.xml          # manifest GLPI
├── README.md
├── CHANGELOG.md
├── LICENSE
├── api/
│   ├── ask.php             # endpoint principal: POST {question, ...} → {answer, sources}
│   └── config.php          # form HTML de config (POST self-handling)
├── front/
│   ├── config.form.php     # bootstrap GLPI + iframe a /helpbubble-api/config.php
│   └── doc.php             # sirve archivos de docs/ con auth de GLPI
├── ajax/
│   └── ask.php             # placeholder (ver workaround del firewall)
├── public/
│   ├── helpbubble.js       # widget vanilla JS, mini-parser Markdown
│   └── helpbubble.css      # estilos del FAB y panel
├── docs/                   # tus archivos .md/.txt para modo local
│   └── .gitkeep
└── apache/
    └── helpbubble.conf     # Alias para servir api/ fuera de GLPI
```

### Tabla de config

`glpi_plugin_helpbubble_config` — esquema clave-valor:

| Clave         | Default                            | Notas |
|---------------|------------------------------------|-------|
| `mode`        | `local`                            | `local` / `n8n` / `both` |
| `n8n_url`     | `""`                               | URL completa del webhook |
| `xai_api_key` | `""`                               | Bearer token de xAI |
| `xai_model`   | `grok-4-1-fast-non-reasoning`      | Cualquier modelo xAI compatible |

## Troubleshooting

**El endpoint devuelve HTTP 500 con body vacío.**
Casi siempre es un error de conexión a DB. Mirar `/tmp/helpbubble.log` y `/var/log/apache2/php_errors.log` dentro del contenedor. El plugin lee credenciales de `<glpi-root>/config/config_db.php` — si ese archivo no existe o no es legible para `www-data`, la conexión falla.

**La burbuja no aparece.**
- Verificar que el plugin esté **activado** (no solo instalado) en Configuración → Plugins.
- `Ctrl+F5` para descartar JS cacheado en el browser.
- Verificar que GLPI inyecte el `add_javascript` hook: en cualquier página, ver el HTML por `helpbubble.js`.
- El plugin se auto-skipea en pantallas no logueadas (`<body class="not-logged">`).

**La rueditas de configuración no aparece.**
Después de instalar, **desactivar y reactivar** el plugin para que GLPI cachee correctamente `$PLUGIN_HOOKS['config_page']`.

**Modo local responde "No encontré información" pese a que la KB tiene el item.**
- Verificar que el snippet del item no esté truncado por debajo de lo que necesita Grok (ahora son 8000 caracteres).
- Si la pregunta tiene muchas palabras genéricas (ej. "como hago para ..."), el scoring puede dejar afuera al item correcto. Revisar `/tmp/helpbubble.log` para ver el `top=...` con los IDs encontrados.

**Modo n8n devuelve "No encontré..." aunque el webhook tiene la info.**
Probar el webhook directamente con `curl` mandando el payload completo (ver sección de payload). Si el flow espera campos que no estamos mandando, agregarlos a `hb_call_n8n()` en `api/ask.php`.

## Roadmap

- [ ] FULLTEXT index sobre `glpi_knowbaseitems` para reemplazar el LIKE+scoring actual.
- [ ] Soporte de PDF en `docs/` (con `pdftotext` o `smalot/pdfparser`).
- [ ] Permisos por perfil de GLPI (no toda la organización ve la burbuja).
- [ ] Persistencia de conversaciones por usuario.
- [ ] Streaming de respuestas (ahora espera el JSON completo).
- [ ] Endpoint nativo en `ajax/` cuando GLPI 11 corrija el bug del firewall.

## Licencia

GPLv3 — ver [LICENSE](LICENSE).

## Autor

Jonatan Riccillo
