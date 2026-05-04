<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

global $DB, $CFG_GLPI;

$action = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/helpbubble/front/config.form.php';

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $stmt = $DB->prepare("REPLACE INTO glpi_plugin_helpbubble_config (k, v) VALUES (?, ?)");
   foreach (['mode', 'n8n_url', 'xai_api_key', 'xai_model'] as $f) {
      if (!isset($_POST[$f])) continue;
      // La API key es write-only: si vino vacía, no se pisa.
      if ($f === 'xai_api_key' && trim($_POST[$f]) === '') continue;
      $val = (string)$_POST[$f];
      $stmt->bind_param('ss', $f, $val);
      $stmt->execute();
   }
   $saved = true;
}

$cfg = [];
$res = $DB->doQuery("SELECT k, v FROM glpi_plugin_helpbubble_config");
if ($res) while ($row = $res->fetch_assoc()) $cfg[$row['k']] = $row['v'];

$mode    = $cfg['mode']     ?? 'local';
$n8n_url = $cfg['n8n_url']  ?? '';
$model   = $cfg['xai_model'] ?? 'grok-4-1-fast-non-reasoning';
$key_raw = $cfg['xai_api_key'] ?? '';
$key_set = $key_raw !== '';
$key_mask = $key_set
   ? substr($key_raw, 0, 6) . str_repeat('•', 12) . substr($key_raw, -4)
   : '';

Html::header(
   'HelpBubble',
   $_SERVER['PHP_SELF'],
   'config',
   'PluginHelpbubbleConfig'
);
?>
<div class="container-fluid p-4">
   <h2 class="mb-4">HelpBubble — Configuración</h2>

   <?php if ($saved): ?>
      <div class="alert alert-success">Configuración guardada.</div>
   <?php endif; ?>

   <form method="POST" action="<?= htmlspecialchars($action) ?>" class="card p-4" style="max-width:760px">
      <div class="mb-4">
         <label class="form-label fw-bold">Modo de operación</label>
         <div class="form-check">
            <input class="form-check-input" type="radio" name="mode" value="local" id="mode_local" <?= $mode === 'local' ? 'checked' : '' ?>>
            <label class="form-check-label" for="mode_local">
               <strong>Local</strong> — Búsqueda en la base de conocimiento de GLPI con post-procesamiento por xAI Grok.
            </label>
         </div>
         <div class="form-check">
            <input class="form-check-input" type="radio" name="mode" value="n8n" id="mode_n8n" <?= $mode === 'n8n' ? 'checked' : '' ?>>
            <label class="form-check-label" for="mode_n8n">
               <strong>n8n</strong> — Reenvío de la consulta a un workflow externo de RAG vía webhook.
            </label>
         </div>
         <div class="form-check">
            <input class="form-check-input" type="radio" name="mode" value="both" id="mode_both" <?= $mode === 'both' ? 'checked' : '' ?>>
            <label class="form-check-label" for="mode_both">
               <strong>Combinado</strong> — Ejecuta los dos modos y combina las respuestas.
            </label>
         </div>
      </div>

      <div class="mb-3">
         <label class="form-label fw-bold" for="n8n_url">URL del webhook n8n</label>
         <input type="text" class="form-control" id="n8n_url" name="n8n_url"
                value="<?= htmlspecialchars($n8n_url) ?>"
                placeholder="https://n8n.example.com/webhook/glpi-rag">
         <div class="form-text">Requerido para los modos <em>n8n</em> y <em>Combinado</em>.</div>
      </div>

      <div class="mb-3">
         <label class="form-label fw-bold" for="xai_api_key">API Key de xAI</label>
         <input type="password" class="form-control" id="xai_api_key" name="xai_api_key"
                value="" autocomplete="off"
                placeholder="<?= $key_set ? htmlspecialchars($key_mask) : 'xai-...' ?>">
         <div class="form-text">
            Requerido para los modos <em>Local</em> y <em>Combinado</em>.
            <?= $key_set ? 'Hay una API key cargada — dejá el campo vacío para mantenerla, o ingresá una nueva para reemplazarla.' : '' ?>
         </div>
      </div>

      <div class="mb-3">
         <label class="form-label fw-bold" for="xai_model">Modelo de xAI</label>
         <input type="text" class="form-control" id="xai_model" name="xai_model"
                value="<?= htmlspecialchars($model) ?>">
      </div>

      <div class="mt-4">
         <button type="submit" class="btn btn-primary">Guardar configuración</button>
      </div>
<?php Html::closeForm(); ?>
</div>
<?php
Html::footer();
