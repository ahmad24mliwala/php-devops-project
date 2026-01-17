<?php
function clear_home_cache() {
    $cacheFile = __DIR__ . '/../cache/home_products.php';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
}
