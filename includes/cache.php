<?php
function cache_get(string $key, int $ttl = 300) {
    $file = __DIR__ . "/../cache/$key.php";
    if (file_exists($file) && (time() - filemtime($file) < $ttl)) {
        return require $file;
    }
    return null;
}

function cache_set(string $key, $data) {
    $file = __DIR__ . "/../cache/$key.php";
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }
    file_put_contents($file, "<?php return " . var_export($data, true) . ";");
}
