<?php
$cache = __DIR__ . '/../cache/home_products.json';
if (file_exists($cache)) {
    unlink($cache);
}
