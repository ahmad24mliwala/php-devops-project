<?php
// public_html/image.php

/* ===============================
   BASIC VALIDATION
================================ */
if (empty($_GET['file'])) {
    http_response_code(400);
    exit('No file specified');
}

// Only allow filename (no paths)
$file = basename($_GET['file']);

// Allowed extensions
$allowedExt = ['jpg','jpeg','png','gif','webp'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt)) {
    http_response_code(403);
    exit('Invalid file type');
}

// Upload directory
$uploadDir = __DIR__ . '/uploads/';
$path = $uploadDir . $file;

if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found');
}

/* ===============================
   WEBP AUTO SERVE (IF SUPPORTED)
================================ */
$acceptWebp = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
$webpPath   = $uploadDir . pathinfo($file, PATHINFO_FILENAME) . '.webp';

if ($acceptWebp && file_exists($webpPath)) {
    $path = $webpPath;
    $ext  = 'webp';
}

/* ===============================
   CACHE HEADERS (IMPORTANT)
================================ */
$lastModified = filemtime($path);
$etag = '"' . md5($path . $lastModified) . '"';

header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

// 304 Not Modified
if (
    (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $lastModified)
) {
    http_response_code(304);
    exit;
}

/* ===============================
   MIME TYPE
================================ */
$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp'
];

header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));

/* ===============================
   OUTPUT IMAGE
================================ */
readfile($path);
exit;
