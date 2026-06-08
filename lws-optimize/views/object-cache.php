<?php
/**
 * Memcached Object Cache Drop-In
 * Place in wp-content/object-cache.php
 *
 * @lwsop-signature LWS_OPTIMIZE_OBJECT_CACHE_v1
 *
 * 4.5.12 — Defense in depth : tous les appels Memcached sont try/catch.
 * Même si l'environnement passe le validate au boot, toute exception runtime
 * (timeout, perte de socket, conflit sessions, etc.) doit dégrader silencieusement
 * vers `return false` au lieu de faire écran blanc HTTP 500 en wp-admin.
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

try {
$memcached_instance = new Memcached();
    $memcached_instance->addServer('127.0.0.1', 11211);
    // 4.5.12 — timeouts agressifs : éviter qu'un Memcached lent ne bloque la page entière.
    $memcached_instance->setOption(Memcached::OPT_CONNECT_TIMEOUT, 200);
    $memcached_instance->setOption(Memcached::OPT_POLL_TIMEOUT,    200);
    $memcached_instance->setOption(Memcached::OPT_SEND_TIMEOUT,    200);
    $memcached_instance->setOption(Memcached::OPT_RECV_TIMEOUT,    200);
} catch (\Throwable $e) {
    error_log('LWS Optimize Memcached init exception: ' . $e->getMessage());
    $memcached_instance = null;
}

class WP_Object_Cache {
    private $cache = [];
    private $memcached;
    private $group_ops = [];
    private $cache_hits = 0;
    private $cache_misses = 0;
    private $non_persistent_groups = [];
    private $global_groups = [];

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
        if (!$this->memcached || in_array($group, $this->non_persistent_groups, true)) {
            return true; // runtime-only
        }
        try {
            return (bool) $this->memcached->set($id, $data, (int) $expire);
        } catch (\Throwable $e) {
            error_log('LWS Optimize Memcached SET exception: ' . $e->getMessage());
            return false;
        }
    }

    public function get($key, $group = 'default', $force = false, &$found = null) {
        $id = $this->buildKey($key, $group);

        if (!$force && isset($this->cache[$id])) {
            $found = true;
            $this->cache_hits++;
            return $this->cache[$id];
        }

        if (!$this->memcached || in_array($group, $this->non_persistent_groups, true)) {
            $found = false;
            $this->cache_misses++;
            return false;
        }

        try {
            $value = $this->memcached->get($id);
            $rc = $this->memcached->getResultCode();
            if ($value === false && $rc !== Memcached::RES_SUCCESS) {
                if ($rc !== Memcached::RES_NOTFOUND) {
                    error_log(sprintf('LWS Optimize Memcached GET non-success (%d): %s for key %s', $rc, $this->memcached->getResultMessage(), $id));
                }
                $found = false;
                $this->cache_misses++;
                return false;
            }
        $found = true;
        $this->cache[$id] = $value;
        $this->cache_hits++;
        return $value;
        } catch (\Throwable $e) {
            error_log('LWS Optimize Memcached GET exception: ' . $e->getMessage());
            $found = false;
            $this->cache_misses++;
            return false;
        }
    }

    /**
     * 4.5.12 — WP 5.5+ : récupération multiple. Si absent du drop-in, WP utilise
     * un fallback foreach qui appelle get() N fois → pénalise wp-admin (qui fait
     * massivement du get_multiple sur les options/transients).
     */
    public function get_multiple($keys, $group = 'default', $force = false) {
        $values = [];
        foreach ((array) $keys as $key) {
            $values[$key] = $this->get($key, $group, $force);
        }
        return $values;
    }

    public function set_multiple(array $data, $group = '', $expire = 0) {
        $results = [];
        foreach ($data as $key => $value) {
            $results[$key] = $this->set($key, $value, $group, $expire);
        }
        return $results;
    }

    public function add_multiple(array $data, $group = '', $expire = 0) {
        $results = [];
        foreach ($data as $key => $value) {
            $results[$key] = $this->add($key, $value, $group, $expire);
        }
        return $results;
    }

    public function delete_multiple(array $keys, $group = '') {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->delete($key, $group);
        }
        return $results;
    }

    public function delete($key, $group = 'default') {
        $id = $this->buildKey($key, $group);
        unset($this->cache[$id]);
        if (!$this->memcached) {
            return true;
        }
        try {
            return (bool) $this->memcached->delete($id);
        } catch (\Throwable $e) {
            error_log('LWS Optimize Memcached DELETE exception: ' . $e->getMessage());
            return false;
        }
    }

    public function flush() {
        $this->cache = [];
        if (!$this->memcached) {
            return true;
        }
        try {
            return (bool) $this->memcached->flush();
        } catch (\Throwable $e) {
            error_log('LWS Optimize Memcached FLUSH exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 4.5.12 — WP 6.0+ : purge cache mémoire après save_post sans toucher Memcached
     */
    public function flush_runtime() {
        $this->cache = [];
        return true;
    }

    /**
     * 4.5.12 — WP 6.1+ : purge un groupe — fallback (Memcached n'a pas de groupes natifs)
     */
    public function flush_group($group) {
        foreach (array_keys($this->cache) as $id) {
            unset($this->cache[$id]);
        }
        return true;
    }

    public function incr($key, $offset = 1, $group = 'default') {
        $id = $this->buildKey($key, $group);
        if (!$this->memcached) {
            return false;
        }
        try {
        return $this->memcached->increment($id, $offset);
        } catch (\Throwable $e) {
            error_log('LWS Optimize Memcached INCR exception: ' . $e->getMessage());
            return false;
        }
    }

    public function decr($key, $offset = 1, $group = 'default') {
        $id = $this->buildKey($key, $group);
        if (!$this->memcached) {
            return false;
        }
        try {
        return $this->memcached->decrement($id, $offset);
        } catch (\Throwable $e) {
            error_log('LWS Optimize Memcached DECR exception: ' . $e->getMessage());
            return false;
        }
    }

    public function replace($key, $data, $group = 'default', $expire = 0) {
        if ($this->get($key, $group) === false) {
            return false;
        }
        return $this->set($key, $data, $group, $expire);
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

    public function close() {
        return true;
    }

    public function add_global_groups($groups) {
        $this->global_groups = array_unique(array_merge($this->global_groups, (array) $groups));
    }

    public function add_non_persistent_groups($groups) {
        $this->non_persistent_groups = array_unique(array_merge($this->non_persistent_groups, (array) $groups));
    }

    /**
     * 4.5.12 — WP 6.1+ : déclare les capabilities supportées
     */
    public function supports($feature) {
        switch ($feature) {
            case 'add_multiple':
            case 'set_multiple':
            case 'get_multiple':
            case 'delete_multiple':
            case 'flush_runtime':
            case 'flush_group':
                return true;
        }
        return false;
    }

    private function buildKey($key, $group) {
        if (empty($group)) {
            $group = 'default';
        }
        return md5(WP_CACHE_KEY_SALT . ':' . $group . ':' . $key);
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

function wp_cache_flush_runtime() {
    global $wp_object_cache;
    return $wp_object_cache->flush_runtime();
}

function wp_cache_flush_group($group) {
    global $wp_object_cache;
    return $wp_object_cache->flush_group($group);
}

function wp_cache_get($key, $group = '', $force = false, &$found = null) {
    global $wp_object_cache;
    return $wp_object_cache->get($key, $group, $force, $found);
}

function wp_cache_get_multiple($keys, $group = '', $force = false) {
    global $wp_object_cache;
    return $wp_object_cache->get_multiple($keys, $group, $force);
}

function wp_cache_set_multiple(array $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->set_multiple($data, $group, $expire);
}

function wp_cache_add_multiple(array $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->add_multiple($data, $group, $expire);
}

function wp_cache_delete_multiple(array $keys, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->delete_multiple($keys, $group);
}

function wp_cache_init() {
    global $wp_object_cache;
    $wp_object_cache = new WP_Object_Cache();
}

function wp_cache_replace($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->replace($key, $data, $group, $expire);
}

function wp_cache_set($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->set($key, $data, $group, $expire);
}

function wp_cache_add_global_groups($groups) {
    global $wp_object_cache;
    $wp_object_cache->add_global_groups($groups);
}

function wp_cache_add_non_persistent_groups($groups) {
    global $wp_object_cache;
    $wp_object_cache->add_non_persistent_groups($groups);
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

function wp_cache_supports($feature) {
    global $wp_object_cache;
    return $wp_object_cache->supports($feature);
}

function wp_cache_switch_to_blog($blog_id) {
    // Multisite : no-op (la clé inclut déjà WP_CACHE_KEY_SALT qui contient le prefix)
    return true;
}

wp_cache_init();
