<?php
$LOG = '/tmp/helpbubble.log';
function hblog($m) { global $LOG; @file_put_contents($LOG, '['.date('H:i:s').'] '.$m."\n", FILE_APPEND); }

$DOCS_PATH = '/var/www/html/glpi/plugins/helpbubble/docs';
$CFG_DB_PATH = '/var/www/html/glpi/config/config_db.php';

header('Content-Type: application/json');

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

function hb_call_n8n($url, $question, $payload) {
   $body = [
      'question'   => $question,
      'session_id' => $payload['session_id'] ?? '',
      'user_id'    => $payload['user_id'] ?? null,
      'user_name'  => $payload['user_name'] ?? null,
      'entity_id'  => $payload['entity_id'] ?? null,
      'profile_id' => $payload['profile_id'] ?? null,
      'page'       => $payload['page'] ?? '',
   ];
   $ch = curl_init($url);
   curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($body),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_TIMEOUT => 60,
   ]);
   $resp = curl_exec($ch);
   $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);
   hblog("n8n HTTP $code");
   if ($resp === false || $code !== 200) {
      return ['error' => "n8n HTTP $code"];
   }
   $data = json_decode($resp, true);
   if (!is_array($data)) {
      return ['error' => 'n8n respuesta inválida'];
   }
   return [
      'answer'  => trim((string)($data['answer'] ?? '')),
      'sources' => is_array($data['sources'] ?? null) ? $data['sources'] : [],
   ];
}

function hb_local_search($mysqli, $question, $words, $base_url, $docs_path) {
   // Score por palabra: name vale 3x answer, palabras largas pesan más.
   $scores = [];
   $titles = [];
   $answers = [];
   foreach ($words as $w) {
      $weight = mb_strlen($w);
      $we = $mysqli->real_escape_string($w);
      $r = $mysqli->query("SELECT id, name, answer FROM glpi_knowbaseitems WHERE name LIKE '%$we%' OR answer LIKE '%$we%'");
      if (!$r) continue;
      while ($row = $r->fetch_assoc()) {
         $id = (int)$row['id'];
         $name_match   = stripos($row['name'] ?? '', $w) !== false;
         $answer_match = stripos($row['answer'] ?? '', $w) !== false;
         $add = ($name_match ? 3 : 0) + ($answer_match ? 1 : 0);
         $scores[$id] = ($scores[$id] ?? 0) + $weight * $add;
         $titles[$id] = $row['name'];
         $answers[$id] = $row['answer'];
      }
   }
   arsort($scores);
   $top_ids = array_slice(array_keys($scores), 0, 5);
   $kb_results = [];
   foreach ($top_ids as $id) {
      $kb_results[] = [
         'title'   => $titles[$id],
         'snippet' => mb_substr(strip_tags($answers[$id]), 0, 8000),
         'url'     => $base_url . '/front/knowbaseitem.form.php?id=' . $id,
      ];
   }
   hblog('kb: ' . count($kb_results) . ' top=' . implode(',', $top_ids));

   $file_results = [];
   if (is_dir($docs_path)) {
      $files = glob($docs_path . '/*.{txt,md}', GLOB_BRACE) ?: [];
      foreach ($files as $file) {
         $content = @file_get_contents($file);
         if ($content === false) continue;
         $name = basename($file);
         $match = stripos($content, $question) !== false || stripos($name, $question) !== false;
         if (!$match) foreach ($words as $w) {
            if (stripos($content, $w) !== false || stripos($name, $w) !== false) { $match = true; break; }
         }
         if ($match) {
            $file_results[] = [
               'title'   => $name,
               'snippet' => mb_substr($content, 0, 8000),
               'url'     => $base_url . '/plugins/helpbubble/front/doc.php?file=' . urlencode($name),
            ];
         }
      }
      $file_results = array_slice($file_results, 0, 3);
   }
   return array_merge($kb_results, $file_results);
}

function hb_call_grok($cfg, $question, $candidates) {
   if (empty($candidates)) {
      return ['answer' => '', 'sources' => []];
   }
   $ctx = "";
   foreach ($candidates as $i => $c) {
      $n = $i + 1;
      $ctx .= "[$n] {$c['title']}\nURL: {$c['url']}\nCONTENIDO: {$c['snippet']}\n\n";
   }
   $system = 'Sos un asistente de soporte IT en español rioplatense, tono profesional pero directo (sin "che", "boludo", "loco" ni similares). SOLO usás info del CONTEXTO. Si no podés responder con eso, devolvés exactamente: {"answer":"No encontré información sobre eso.","sources":[]}. Nunca inventes ni uses conocimiento general. Devolvés SOLO JSON válido sin markdown wrapper: {"answer":"...","sources":[{"title":"...","url":"..."}]}. El campo "answer" puede contener Markdown (negritas con **, listas con -, código con backticks, links con [texto](url)). Reglas para "answer": completa, con TODOS los pasos del procedimiento sin omitir ni decir que se corta; compacta, sin líneas en blanco entre items de una misma lista; los pasos numerados van con "**1. Título:**" en una línea y el detalle a continuación. Las sources son solo las que efectivamente usaste, copiadas exactas del CONTEXTO.';
   $user = "PREGUNTA: $question\n\nCONTEXTO:\n$ctx";

   $ch = curl_init('https://api.x.ai/v1/chat/completions');
   curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode([
         'model' => $cfg['xai_model'] ?? 'grok-4-1-fast-non-reasoning',
         'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
         ],
         'temperature' => 0.1,
      ]),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
         'Content-Type: application/json',
         'Authorization: Bearer ' . ($cfg['xai_api_key'] ?? ''),
      ],
      CURLOPT_TIMEOUT => 60,
   ]);
   $resp = curl_exec($ch);
   $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);
   hblog("grok HTTP $code");

   if ($resp === false || $code !== 200) {
      return ['error' => "grok HTTP $code"];
   }
   $data = json_decode($resp, true);
   $content = trim(preg_replace('/```json|```/', '', $data['choices'][0]['message']['content'] ?? ''));
   $parsed = json_decode($content, true);

   if (!$parsed || !isset($parsed['answer'])) {
      return [
         'answer'  => $content ?: '',
         'sources' => array_map(fn($c) => ['title' => $c['title'], 'url' => $c['url']], $candidates),
      ];
   }
   return [
      'answer'  => trim((string)$parsed['answer']),
      'sources' => is_array($parsed['sources'] ?? null) ? $parsed['sources'] : [],
   ];
}

function hb_normalize_sources($sources) {
   $out = [];
   foreach ($sources as $s) {
      if (!is_array($s)) continue;
      $title = $s['title'] ?? $s['file_name'] ?? $s['name'] ?? '';
      $url   = $s['url']   ?? $s['file_url']  ?? '';
      if ($title === '' && $url === '') continue;
      $out[] = ['title' => $title ?: $url, 'url' => $url];
   }
   return $out;
}

function hb_no_match($answer) {
   if ($answer === '') return true;
   $a = mb_strtolower($answer);
   return strpos($a, 'no encontré') !== false || strpos($a, 'no encontre') !== false;
}

// ============================================================

$body = file_get_contents('php://input');
$payload = json_decode($body, true) ?: [];
$question = trim($payload['question'] ?? '');
hblog('q: ' . $question);

if ($question === '') {
   echo json_encode(['answer' => 'Pregunta vacía.', 'sources' => []]);
   exit;
}

$creds = hb_db_creds($CFG_DB_PATH);
if (!$creds) {
   echo json_encode(['answer' => 'Error: no pude leer config_db.php', 'sources' => []]);
   exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
try {
   $mysqli = new mysqli($creds['dbhost'], $creds['dbuser'], $creds['dbpassword'], $creds['dbdefault']);
   if ($mysqli->connect_error) throw new \RuntimeException($mysqli->connect_error);
} catch (\Throwable $e) {
   hblog('db connect FAIL: ' . $e->getMessage());
   echo json_encode(['answer' => 'Error DB: ' . $e->getMessage(), 'sources' => []]);
   exit;
}

$cfg = [];
$r = $mysqli->query("SELECT k, v FROM glpi_plugin_helpbubble_config");
if ($r) while ($row = $r->fetch_assoc()) $cfg[$row['k']] = $row['v'];

$mode = $cfg['mode'] ?? 'local';
hblog('mode=' . $mode);

$do_local = ($mode === 'local' || $mode === 'both');
$do_n8n   = ($mode === 'n8n'   || $mode === 'both');

// LOCAL: KB + docs + Grok
$local = null;
if ($do_local) {
   $base_url = '';
   $r = $mysqli->query("SELECT value FROM glpi_configs WHERE name='url_base' LIMIT 1");
   if ($r && ($row = $r->fetch_assoc())) $base_url = $row['value'];
   $words = array_filter(preg_split('/\s+/', $question), fn($w) => mb_strlen($w) > 3);
   $candidates = hb_local_search($mysqli, $question, $words, $base_url, $DOCS_PATH);
   if (empty($candidates)) {
      $local = ['answer' => '', 'sources' => []];
   } else {
      $local = hb_call_grok($cfg, $question, $candidates);
   }
}
$mysqli->close();

// N8N
$n8n = null;
if ($do_n8n) {
   $url = $cfg['n8n_url'] ?? '';
   if (!$url) {
      $n8n = ['error' => 'n8n_url no configurada'];
   } else {
      $n8n = hb_call_n8n($url, $question, $payload);
   }
}

// Combinar y devolver
$is_both   = ($mode === 'both');
$local_ok  = $do_local && $local && empty($local['error']) && !hb_no_match($local['answer']);
$n8n_ok    = $do_n8n   && $n8n   && empty($n8n['error'])   && !hb_no_match($n8n['answer']);

$parts = [];
$sources = [];

if ($is_both) {
   if ($local_ok && $n8n_ok) {
      $parts[] = "**Base de conocimiento de GLPI**\n" . $local['answer'];
      $parts[] = "**Documentación externa**\n" . $n8n['answer'];
      $sources = array_merge(hb_normalize_sources($local['sources']), hb_normalize_sources($n8n['sources']));
   } elseif ($local_ok) {
      $parts[] = $local['answer'];
      $sources = hb_normalize_sources($local['sources']);
   } elseif ($n8n_ok) {
      $parts[] = $n8n['answer'];
      $sources = hb_normalize_sources($n8n['sources']);
   } else {
      $err = '';
      if (!empty($local['error'])) $err .= ' (local: ' . $local['error'] . ')';
      if (!empty($n8n['error']))   $err .= ' (n8n: '   . $n8n['error']   . ')';
      $parts[] = 'No encontré información sobre eso.' . $err;
      // Aún así, mostrar sources si los hay (útil aunque el LLM no haya armado respuesta)
      $sources = array_merge(
         hb_normalize_sources($local['sources'] ?? []),
         hb_normalize_sources($n8n['sources'] ?? [])
      );
   }
} elseif ($do_local) {
   $parts[] = $local['answer'] !== '' ? $local['answer'] : ('No encontré información.' . (!empty($local['error']) ? ' (' . $local['error'] . ')' : ''));
   $sources = hb_normalize_sources($local['sources'] ?? []);
} elseif ($do_n8n) {
   $parts[] = $n8n['answer'] !== '' ? $n8n['answer'] : ('No encontré información.' . (!empty($n8n['error']) ? ' (' . $n8n['error'] . ')' : ''));
   $sources = hb_normalize_sources($n8n['sources'] ?? []);
}

// Dedupe sources por url
$seen = [];
$dedup = [];
foreach ($sources as $s) {
   $k = $s['url'] ?: $s['title'];
   if (isset($seen[$k])) continue;
   $seen[$k] = true;
   $dedup[] = $s;
}

echo json_encode([
   'answer'  => implode("\n\n", $parts),
   'sources' => $dedup,
]);
hblog('done');
