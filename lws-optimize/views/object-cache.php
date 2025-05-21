<?php
/**
 * Memcached Object Cache Drop-In
 * Place in wp-content/object-cache.php
 */

if (!class_exists('Memcached')) {
    error_log('Memcached extension not installed or enabled.');
    return;
}

if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
    if (defined('DB_NAME') && defined('DB_USER')) {
        global $wpdb;
        define('WP_CACHE_KEY_SALT', DB_NAME . DB_USER . $wpdb->prefix);
    }
}

global $memcached_instance;

$memcached_instance = new Memcached();
$memcached_instance->addServer('127.0.0.1', 11211); // Change if your Memcached server is elsewhere

class WP_Object_Cache {
    private $cache = [];
    private $memcached;
    private $group_ops = [];
    private $cache_hits = 0;
    private $cache_misses = 0;

    public function __construct() {
        global $memcached_instance;
        $this->memcached = $memcached_instance;
    }

    public function add($key, $data, $group = 'default', $expire = 0) {
        if ($this->get($key, $group) !== false) {
            return false;
        }
        return $this->set($key, $data, $group, $expire);
    }

    public function set($key, $data, $group = 'default', $expire = 0) {
        $id = $this->buildKey($key, $group);
        $this->cache[$id] = $data;
        return $this->memcached->set($id, $data, $expire);
    }

    public function get($key, $group = 'default', $force = false, &$found = null) {
        $id = $this->buildKey($key, $group);

        if (isset($this->cache[$id])) {
            $found = true;
            $this->cache_hits++;
            return $this->cache[$id];
        }

        $value = $this->memcached->get($id);
        if ($value === false && $this->memcached->getResultCode() != Memcached::RES_SUCCESS) {
            $found = false;
            $this->cache_misses++;
            return false;
        }

        $found = true;
        $this->cache[$id] = $value;
        $this->cache_hits++;
        return $value;
    }

    public function delete($key, $group = 'default') {
        $id = $this->buildKey($key, $group);
        unset($this->cache[$id]);
        return $this->memcached->delete($id);
    }

    public function flush() {
        $this->cache = [];
        return $this->memcached->flush();
    }

    public function incr($key, $offset = 1, $group = 'default') {
        $id = $this->buildKey($key, $group);
        return $this->memcached->increment($id, $offset);
    }

    public function decr($key, $offset = 1, $group = 'default') {
        $id = $this->buildKey($key, $group);
        return $this->memcached->decrement($id, $offset);
    }

    public function stats() {
        return [
            'hits' => $this->cache_hits,
            'misses' => $this->cache_misses,
            'groups' => $this->group_ops,
        ];
    }

    public function reset() {
        $this->cache = [];
    }

    private function buildKey($key, $group) {
        return md5(DB_NAME . ':' . $group . ':' . $key);
    }
}

function wp_cache_add($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->add($key, $data, $group, $expire);
}

function wp_cache_close() {
    return true;
}

function wp_cache_delete($key, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->delete($key, $group);
}

function wp_cache_flush() {
    global $wp_object_cache;
    return $wp_object_cache->flush();
}

function wp_cache_get($key, $group = '', $force = false, &$found = null) {
    global $wp_object_cache;
    return $wp_object_cache->get($key, $group, $force, $found);
}

function wp_cache_init() {
    global $wp_object_cache;
    $wp_object_cache = new WP_Object_Cache();
}

function wp_cache_replace($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    if (!$wp_object_cache->delete($key, $group)) {
        return false;
    }
    return $wp_object_cache->set($key, $data, $group, $expire);
}

function wp_cache_set($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->set($key, $data, $group, $expire);
}

function wp_cache_add_global_groups($groups) {
    // no-op
}

function wp_cache_add_non_persistent_groups($groups) {
    // no-op
}

function wp_cache_incr($key, $offset = 1, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->incr($key, $offset, $group);
}

function wp_cache_decr($key, $offset = 1, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->decr($key, $offset, $group);
}

function wp_cache_reset() {
    global $wp_object_cache;
    return $wp_object_cache->reset();
}

wp_cache_init();
