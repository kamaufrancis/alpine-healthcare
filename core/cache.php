<?php
if (!defined('CACHE_DIR')) {
    define('CACHE_DIR', __DIR__ . '/../cache');
}

if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

class SimpleFileCache
{
    private $directory;

    public function __construct($directory = CACHE_DIR)
    {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function get($key, $ttl = 300)
    {
        $path = $this->getPath($key);
        if (!is_file($path)) {
            return null;
        }

        $payload = @file_get_contents($path);
        if ($payload === false) {
            return null;
        }

        $data = @unserialize($payload);
        if (!is_array($data) || !isset($data['expires_at'], $data['value'])) {
            return null;
        }

        if ($data['expires_at'] < time()) {
            @unlink($path);
            return null;
        }

        return $data['value'];
    }

    public function set($key, $value, $ttl = 300)
    {
        $payload = serialize([
            'expires_at' => time() + (int) $ttl,
            'value' => $value,
        ]);

        $path = $this->getPath($key);
        return file_put_contents($path, $payload, LOCK_EX) !== false;
    }

    public function delete($key)
    {
        $path = $this->getPath($key);
        if (is_file($path)) {
            return unlink($path);
        }

        return true;
    }

    private function getPath($key)
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $key);
        return $this->directory . DIRECTORY_SEPARATOR . $safeKey . '.cache';
    }
}

$dashboardCache = new SimpleFileCache();

function getCachedValue($key, callable $callback, $ttl = 300)
{
    global $dashboardCache;

    $cached = $dashboardCache->get($key, $ttl);
    if ($cached !== null) {
        return $cached;
    }

    $value = $callback();
    $dashboardCache->set($key, $value, $ttl);
    return $value;
}
