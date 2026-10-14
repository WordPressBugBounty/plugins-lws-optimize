<?php

namespace Lws\Classes\Front;

/**
 * Background queue for the CSS jobs that render the page or call a remote
 * service (Critical CSS, unused CSS removal).
 *
 * A page never waits on these jobs: it is served and cached as-is, its URL is
 * queued, and once the job has produced its result the cached page is purged
 * so the next render applies it. A single runner works through the queue, so
 * however many uncached pages are hit at once, a site has at most one job in
 * flight.
 *
 * The locks and the service pause are marker files, not transients:
 * lwsop_run_global_purge() flushes the object cache, which on Memcached/Redis
 * sites wipes every transient. A full purge deletes the markers with the rest
 * of the cache directory, which simply re-arms every URL.
 */
class LwsOptimizeCSSJobQueue
{
    const HOOK   = 'lwsop_process_css_jobs';
    const OPTION = 'lwsop_css_job_queue';

    // Bounds the queue, so a crawl of thousands of uncached pages cannot grow it forever
    const MAX_ITEMS = 200;

    // A queued URL is not queued again for LOCK_TTL, a failed one for FAIL_TTL
    const LOCK_TTL = 15 * MINUTE_IN_SECONDS;
    const FAIL_TTL = 6 * HOUR_IN_SECONDS;

    // The remote services are left alone for PAUSE_TTL after an error or a timeout
    const PAUSE_MARKER = 'service-paused';
    const PAUSE_TTL    = 15 * MINUTE_IN_SECONDS;

    // Only one runner at a time; its lock outlives a run killed mid-job
    const RUNNER_MARKER = 'running';
    const RUNNER_TTL    = 15 * MINUTE_IN_SECONDS;

    // Wall-clock budget of one run, the remaining jobs wait for the next one
    const RUN_BUDGET = 45;

    /**
     * Queues a job for $url unless one is already queued, running, or failed recently.
     *
     * @param string $type 'ccss' (Critical CSS) or 'ucss' (unused CSS)
     * @param string $url  Absolute URL of the page
     */
    public static function enqueue($type, $url)
    {
        $lock = self::lock_key($type, $url);
        if (self::marker_active($lock)) {
            return;
        }

        $queue = self::read_queue();
        if (count($queue) >= self::MAX_ITEMS) {
            return;
        }

        $queue[$lock] = ['type' => $type, 'url' => $url];
        update_option(self::OPTION, $queue, false);
        self::set_marker($lock, self::LOCK_TTL);

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_single_event(time(), self::HOOK);
        }
    }

    /**
     * Cron callback: runs the queued jobs until the queue is empty, the time
     * budget is spent or the remote services get paused.
     */
    public static function process()
    {
        if (self::marker_active(self::RUNNER_MARKER)) {
            return;
        }
        self::set_marker(self::RUNNER_MARKER, self::RUNNER_TTL);
        self::delete_expired_markers();

        $deadline = time() + self::RUN_BUDGET;
        while (time() < $deadline && !self::service_paused()) {
            $queue = self::read_queue();
            if (empty($queue)) {
                break;
            }

            // Dequeued before running, so a job that kills the run is not retried forever
            $lock = array_key_first($queue);
            $job  = $queue[$lock];
            unset($queue[$lock]);
            update_option(self::OPTION, $queue, false);

            $done = self::run($job);
            $label = $job['type'] === 'ccss' ? 'Critical CSS' : 'Unused CSS';
            if ($done === true) {
                self::purge_url($job['url']);
                self::log("$label ready for {$job['url']} - page purged so that its next caching applies it");
            } elseif ($done === false) {
                if (self::service_paused()) {
                    // The service failed, not the page: put the job back for after the pause
                    update_option(self::OPTION, [$lock => $job] + self::read_queue(), false);
                    self::log("CSS services paused for 15 minutes after an error - $label job for {$job['url']} requeued");
                    break;
                }
                self::set_marker($lock, self::FAIL_TTL);
                self::log("$label generation failed for {$job['url']} - not retried for 6 hours");
            }
        }

        self::delete_marker(self::RUNNER_MARKER);

        if (!empty(self::read_queue()) && !wp_next_scheduled(self::HOOK)) {
            $delay = self::service_paused() ? self::PAUSE_TTL : MINUTE_IN_SECONDS;
            wp_schedule_single_event(time() + $delay, self::HOOK);
        }
    }

    public static function service_paused()
    {
        return self::marker_active(self::PAUSE_MARKER);
    }

    /**
     * Whether a remote service answered normally. A network error, a timeout,
     * a 429 or a 5xx pauses every remote call for PAUSE_TTL.
     */
    public static function service_ok($response)
    {
        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        if ($code === 0 || $code === 429 || $code >= 500) {
            self::set_marker(self::PAUSE_MARKER, self::PAUSE_TTL);
            return false;
        }

        return true;
    }

    /**
     * @return bool|null true if a result was stored, false on failure, null if
     *                   there was nothing to do (feature turned off meanwhile)
     */
    private static function run(array $job)
    {
        if ($job['type'] === 'ccss') {
            return LwsOptimizeCriticalCSSManager::generate_critical_css_cron($job['url']);
        }
        if ($job['type'] === 'ucss') {
            return LwsOptimizeUnusedCSS::generate($job['url']);
        }

        return null;
    }

    /**
     * Removes the cached page of $url (and its edge cache copies) so the next
     * render picks up the new result.
     */
    private static function purge_url($url)
    {
        if (empty($GLOBALS['lws_optimize'])) {
            return;
        }

        $GLOBALS['lws_optimize']->lwsop_purge_url_files(wp_parse_url($url, PHP_URL_PATH) ?: '/');
        $GLOBALS['lws_optimize']->lwsop_purge_varnish_url($url);
        do_action('lwsop_purge_url', $url, 'css_job');
    }

    /**
     * The queue as stored right now. Other requests add jobs while a run goes
     * on: reading the option cache of this long-running process instead would
     * miss them, and writing that stale copy back would drop them.
     */
    private static function read_queue()
    {
        wp_cache_delete(self::OPTION, 'options');
        $queue = get_option(self::OPTION, []);

        return is_array($queue) ? $queue : [];
    }

    public static function log($message)
    {
        if (!empty($GLOBALS['lws_optimize']->log_file)) {
            @file_put_contents($GLOBALS['lws_optimize']->log_file, '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
        }
    }

    private static function lock_key($type, $url)
    {
        return $type . '-' . md5($url);
    }

    private static function marker_dir()
    {
        return LWS_OP_UPLOADS . 'css-jobs/';
    }

    // A marker file holds the timestamp it expires at
    private static function marker_active($name)
    {
        return (int) @file_get_contents(self::marker_dir() . $name) > time();
    }

    private static function set_marker($name, $ttl)
    {
        if (wp_mkdir_p(self::marker_dir())) {
            @file_put_contents(self::marker_dir() . $name, (string) (time() + $ttl));
        }
    }

    private static function delete_marker($name)
    {
        @unlink(self::marker_dir() . $name);
    }

    private static function delete_expired_markers()
    {
        foreach (glob(self::marker_dir() . '*') ?: [] as $file) {
            if (!self::marker_active(basename($file))) {
                @unlink($file);
            }
        }
    }
}
