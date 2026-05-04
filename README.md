# HelpBubble

Asistente de ayuda flotante para GLPI 11. Inyecta una burbuja de chat en todas las páginas autenticadas: el usuario formula una consulta en lenguaje natural y recibe una respuesta concisa con enlaces a las fuentes.

## Modos de operación

| Modo | Descripción |
|------|-------------|
| `local` | Búsqueda en `glpi_knowbaseitems` y archivos `.md`/`.txt` del directorio `docs/` del plugin, con post-procesamiento por [xAI Grok](https://x.ai/) y respuesta estructurada como `{answer, sources}`. |
| `n8n` | Reenvío de la consulta a un workflow externo de [n8n](https://n8n.io/) vía webhook. La respuesta del workflow se devuelve sin transformaciones adicionales. |
| `both` | Ejecuta los dos modos y combina sus respuestas con identificación de origen y deduplicación de fuentes por URL. |

## Requisitos

- GLPI **11.0.x**
- PHP **8.1** o superior, con las extensiones `mysqli`, `curl`, `mbstring` y `json` (todas presentes en una instalación estándar de GLPI)
- Una instancia de MariaDB / MySQL (la misma que use GLPI)
- Modo `local` o `both`: una API key de [xAI](https://x.ai/api)
- Modo `n8n` o `both`: un webhook de n8n compatible con el contrato descripto más abajo

## Instalación

1. Descargar el ZIP del [release deseado](https://github.com/jonatanriccillo/glpihelpbubble/releases) o clonar el repositorio.
2. Copiar el directorio `helpbubble/` a `<glpi-root>/plugins/`.
3. Asegurar permisos de lectura para el usuario del servidor web:

   ```bash
   chown -R www-data:www-data <glpi-root>/plugins/helpbubble
   ```

4. En la interfaz de GLPI, ir a **Configuración → Plugins**, instalar HelpBubble y luego habilitarlo.
5. Hacer clic en el ícono de configuración (engranaje) junto al plugin para acceder al panel de configuración.

No se requieren cambios en la configuración del servidor web.

## Configuración

El panel de configuración permite editar los siguientes parámetros:

- **Modo de operación**: `local`, `n8n` o `both`.
- **URL del webhook n8n**: requerido para los modos `n8n` y `both`.
- **API key de xAI**: requerido para los modos `local` y `both`. El campo es de solo escritura — la key cargada se muestra enmascarada y nunca se incluye en el HTML de la página. Dejar el campo vacío al guardar mantiene la key actual.
- **Modelo de xAI**: por defecto `grok-4-1-fast-non-reasoning`. Acepta cualquier identificador de modelo compatible con la [API de xAI](https://docs.x.ai/).

Los valores se persisten en la tabla `glpi_plugin_helpbubble_config`.

## Contrato del webhook n8n

### Request del plugin

```json
{
   "question":   "¿Cómo configuro Thunderbird con Office 365?",
   "session_id": "uuid-de-la-pestaña",
   "user_id":    42,
   "user_name":  "jdoe",
   "entity_id":  0,
   "profile_id": 4,
   "page":       "/front/ticket.php"
}
```

### Response esperada

```json
{
   "answer":  "Texto de la respuesta, opcionalmente con Markdown",
   "sources": [
      { "title": "manual_outlook_o365.pdf", "url": "https://docs.example.com/..." }
   ]
}
```

Para compatibilidad con flows existentes, también se aceptan las llaves `file_name` y `file_url` como aliases de `title` y `url` en cada source.

## Estructura

```
helpbubble/
├── setup.php             Inicialización del plugin y registro de hooks
├── hook.php              Instalación / desinstalación (creación de tabla)
├── helpbubble.xml        Manifest GLPI
├── ajax/
│   └── ask.php           Endpoint de consultas (POST)
├── front/
│   ├── config.form.php   Página de configuración
│   └── doc.php           Servidor de archivos del directorio docs/
├── public/
│   ├── helpbubble.js     Widget cliente
│   └── helpbubble.css    Estilos
├── docs/                 Documentos opcionales (.md, .txt) para modo local
├── README.md
├── CHANGELOG.md
└── LICENSE
```

### Tabla de configuración

`glpi_plugin_helpbubble_config` (clave-valor):

| Clave         | Default                            | Descripción |
|---------------|------------------------------------|-------------|
| `mode`        | `local`                            | Modo de operación |
| `n8n_url`     | `""`                               | URL completa del webhook n8n |
| `xai_api_key` | `""`                               | Bearer token de xAI |
| `xai_model`   | `grok-4-1-fast-non-reasoning`      | Identificador del modelo de xAI |

## Búsqueda local — algoritmo de relevancia

Cuando el modo es `local` o `both`, la búsqueda en la base de conocimiento aplica un scoring por palabra:

- Coincidencia en `name` del item suma 3 veces más que en `answer`.
- El peso de cada palabra es proporcional a su longitud (las palabras más largas tienden a ser más específicas).
- Se devuelven los 5 items con mayor score acumulado.

Los snippets enviados al modelo se truncan a 8000 caracteres por item, suficiente para procedimientos largos sin exceder la ventana de contexto.

## Documentos locales

Cualquier archivo `.md` o `.txt` colocado en `docs/` se incluye en el conjunto de candidatos del modo `local`. El acceso a estos documentos vía URL pasa por `front/doc.php`, que valida sesión de GLPI antes de servir el contenido.

## Roadmap

- Índice FULLTEXT sobre `glpi_knowbaseitems` para reemplazar el ranking actual basado en `LIKE`.
- Soporte de archivos PDF en `docs/`.
- Permisos por perfil (segmentar la audiencia de la burbuja).
- Persistencia de conversaciones por usuario.
- Streaming progresivo de respuestas.

## Licencia

GPLv3 — ver [LICENSE](LICENSE).

## Autor

Jonatan Riccillo
