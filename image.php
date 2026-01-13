<?php
if (!isset($_GET['file'])) {
    http_response_code(400);
    exit('No file specified.');
}

// Sanitize filename to prevent path traversal
$file = basename($_GET['file']);

// Correct uploads folder (one level up)
$uploadDir = realpath(__DIR__ . '/../uploads') . '/';

$path = $uploadDir . $file;

if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found.');
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
