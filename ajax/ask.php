<?php
// Placeholder. El endpoint real está en api/ask.php
// servido por Apache via Alias /helpbubble-api/
http_response_code(410);
header('Content-Type: application/json');
echo json_encode([
   'answer'  => 'Endpoint movido a /helpbubble-api/ask.php',
   'sources' => []
]);