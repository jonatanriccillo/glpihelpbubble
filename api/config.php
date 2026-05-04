<?php
$CFG_DB_PATH = '/var/www/html/glpi/config/config_db.php';

function hb_db_creds($path) {
   $src = @file_get_contents($path);
   if ($src === false) return null;
   $out = [];
   foreach (['dbhost', 'dbuser', 'dbpassword', 'dbdefault'] as $k) {
      if (preg_match('/\$' . $k . '\s*=\s*\'([^\']*)\'/', $src, $m)) $out[$k] = $m[1];
      else return null;
   }
   return $out;
}

$creds = hb_db_creds($CFG_DB_PATH);
if (!$creds) {
   exit("DB config not readable: $CFG_DB_PATH");
}

mysqli_report(MYSQLI_REPORT_OFF);
try {
   $db = new mysqli($creds['dbhost'], $creds['dbuser'], $creds['dbpassword'], $creds['dbdefault']);
   if ($db->connect_error) throw new \RuntimeException($db->connect_error);
} catch (\Throwable $e) {
   exit("DB error: " . $e->getMessage());
}

$db->query("CREATE TABLE IF NOT EXISTS glpi_plugin_helpbubble_config (
   k VARCHAR(64) PRIMARY KEY,
   v TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
   foreach (["mode", "n8n_url", "xai_api_key", "xai_model"] as $f) {
      if (!isset($_POST[$f])) continue;
      // La key es write-only: si vino vacía, no la pisamos (mantener la actual)
      if ($f === "xai_api_key" && trim($_POST[$f]) === "") continue;
      $s = $db->prepare("REPLACE INTO glpi_plugin_helpbubble_config (k, v) VALUES (?, ?)");
      $s->bind_param("ss", $f, $_POST[$f]);
      $s->execute();
   }
   header("Location: " . strtok($_SERVER["REQUEST_URI"], "?") . "?ok=1");
   exit;
}

$cfg = [];
$r = $db->query("SELECT k, v FROM glpi_plugin_helpbubble_config");
if ($r) while ($x = $r->fetch_assoc()) $cfg[$x["k"]] = $x["v"];

$mode    = $cfg["mode"] ?? "local";
$n8n     = htmlspecialchars($cfg["n8n_url"] ?? "");
$key_raw = $cfg["xai_api_key"] ?? "";
$key_set = $key_raw !== "";
$key_mask = $key_set
   ? htmlspecialchars(substr($key_raw, 0, 6) . str_repeat("•", 12) . substr($key_raw, -4))
   : "";
$model   = htmlspecialchars($cfg["xai_model"] ?? "grok-4-1-fast-non-reasoning");
$cl    = $mode === "local" ? "checked" : "";
$cn    = $mode === "n8n"   ? "checked" : "";
$cb    = $mode === "both"  ? "checked" : "";
$saved = isset($_GET["ok"]);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>HelpBubble Config</title>
<style>
   body { font-family: system-ui, sans-serif; background: #f3f4f6; padding: 40px; margin: 0; }
   .card { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
   h2 { margin-top: 0; color: #2563eb; }
   .f { margin-bottom: 18px; }
   label { display: block; font-weight: 600; margin-bottom: 6px; color: #374151; }
   input[type=text], input[type=password] { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
   .r { font-weight: normal; margin-bottom: 4px; display: block; }
   button { background: #2563eb; color: #fff; border: 0; padding: 10px 22px; border-radius: 6px; font-size: 14px; cursor: pointer; }
   button:hover { background: #1d4ed8; }
   .ok { background: #d1fae5; color: #065f46; padding: 10px; border-radius: 6px; margin-bottom: 18px; }
   .hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
</style>
</head>
<body>
<div class="card">
   <h2>HelpBubble — Configuración</h2>
   <?php if ($saved) echo '<div class="ok">✓ Configuración guardada</div>'; ?>
   <form method="POST">
      <div class="f">
         <label>Modo de operación</label>
         <label class="r"><input type="radio" name="mode" value="local" <?=$cl?>> <b>Local</b> — busca en KB de GLPI + Grok</label>
         <label class="r"><input type="radio" name="mode" value="n8n" <?=$cn?>> <b>n8n</b> — RAG externo vía webhook</label>
         <label class="r"><input type="radio" name="mode" value="both" <?=$cb?>> <b>Ambos</b> — combina respuestas de KB local y n8n</label>
      </div>
      <div class="f">
         <label>URL del webhook n8n</label>
         <input type="text" name="n8n_url" value="<?=$n8n?>" placeholder="http://server:5678/webhook/glpi-rag">
         <div class="hint">Solo se usa cuando el modo es "n8n".</div>
      </div>
      <div class="f">
         <label>API Key xAI</label>
         <input type="password" name="xai_api_key" value="" autocomplete="off"
            placeholder="<?= $key_set ? $key_mask : 'xai-...' ?>">
         <div class="hint">
            Solo se usa cuando el modo es "local".
            <?= $key_set ? 'Hay una key cargada — dejá vacío para mantenerla, o ingresá una nueva para reemplazarla.' : '' ?>
         </div>
      </div>
      <div class="f">
         <label>Modelo xAI</label>
         <input type="text" name="xai_model" value="<?=$model?>">
      </div>
      <button type="submit">Guardar</button>
   </form>
</div>
</body>
</html>