<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

$DOCS_PATH = __DIR__ . '/../docs';
$file = basename($_GET['file'] ?? '');

if (!$file || !file_exists("$DOCS_PATH/$file")) {
   http_response_code(404);
   exit('Not found');
}

$mime = mime_content_type("$DOCS_PATH/$file") ?: 'application/octet-stream';
header("Content-Type: $mime");
header('Content-Disposition: inline; filename="' . $file . '"');
readfile("$DOCS_PATH/$file");