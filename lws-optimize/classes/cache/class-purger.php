<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    lwscache
 */

/**
 * Description of purger
 *
 * @package    lwscache
 *
 * @subpackage lwscache/admin
 *
 * @author     LWS
 */
abstract class Purger
{

	/**
	 * Purge cache for url.
	 *
	 * @param string $url URL.
	 * @param bool   $feed Is feed or not.
	 *
	 * @return mixed
	 */
	abstract public function purge_url($url, $feed = true);

	/**
	 * Purge cache for custom url.
	 *
	 * @return mixed
	 */
	abstract public function custom_purge_urls();

	/**
	 * Purge all cache
	 *
	 * @return mixed
	 */
	abstract public function purge_all();

	/**
	 * Purge cache on comment.
	 *
	 * @param int    $comment_id Comment id.
	 * @param object $comment Comment data.
	 */
	public function purge_post_on_comment($comment_id, $comment)
	{

		$oldstatus = '';
		$approved  = $comment->comment_approved;

		if (null === $approved) {
			$newstatus = false;
		} else if ('1' === $approved) {
			$newstatus = 'approved';
		} else if ('0' === $approved) {
			$newstatus = 'unapproved';
		} else if ('spam' === $approved) {
			$newstatus = 'spam';
		} else if ('trash' === $approved) {
			$newstatus = 'trash';
		} else {
			$newstatus = false;
		}

		$this->purge_post_on_comment_change($newstatus, $oldstatus, $comment);
	}

	/**
	 * Purge post cache on comment change.
	 *
	 * @param string $newstatus New status.
	 * @param string $oldstatus Old status.
	 * @param object $comment Comment data.
	 */
	public function purge_post_on_comment_change($newstatus, $oldstatus, $comment)
	{

		global $blog_id;

		$_post_id    = $comment->comment_post_ID;
		$_comment_id = $comment->comment_ID;

		$this->log('* * * * *');
		$this->log('* Blog :: ' . addslashes(get_bloginfo('name')) . ' ( ' . $blog_id . ' ). ');
		$this->log('* Post :: ' . get_the_title($_post_id) . ' ( ' . $_post_id . ' ) ');
		$this->log("* Comment :: $_comment_id.");
		$this->log("* Status Changed from $oldstatus to $newstatus");

		switch ($newstatus) {

			case 'approved':
				$this->log('* Comment ( ' . $_comment_id . ' ) approved. Post ( ' . $_post_id . ' ) purging...');
				$this->log('* * * * *');
				$this->purge_post($_post_id);
				break;

			case 'spam':
			case 'unapproved':
			case 'trash':
				if ('approved' === $oldstatus) {
					$this->log('* Comment ( ' . $_comment_id . ' ) removed as ( ' . $newstatus . ' ). Post ( ' . $_post_id . ' ) purging...');
					$this->log('* * * * *');
					$this->purge_post($_post_id);
				}
				break;
		}
	}

	/**
	 * Purge post cache.
	 *
	 * @param int $post_id Post id.
	 */
	public function purge_post($post_id)
	{

		global $blog_id;

		switch (current_filter()) {

			case 'publish_post':
				$this->log('* * * * *');
				$this->log('* Blog :: ' . addslashes(get_bloginfo('name')) . ' ( ' . $blog_id . ' ).');
				$this->log('* Post :: ' . get_the_title($post_id) . ' ( ' . $post_id . ' ).');
				$this->log('* Post ( ' . $post_id . ' ) published or edited and its status is published');
				$this->log('* * * * *');
				break;

			case 'publish_page':
				$this->log('* * * * *');
				$this->log('* Blog :: ' . addslashes(get_bloginfo('name')) . ' ( ' . $blog_id . ' ).');
				$this->log('* Page :: ' . get_the_title($post_id) . ' ( ' . $post_id . ' ).');
				$this->log('* Page ( ' . $post_id . ' ) published or edited and its status is published');
				$this->log('* * * * *');
				break;

			case 'comment_post':
			case 'wp_set_comment_status':
				break;

			default:
				$_post_type = get_post_type($post_id);
				$this->log('* * * * *');
				$this->log('* Blog :: ' . addslashes(get_bloginfo('name')) . ' ( ' . $blog_id . ' ).');
				$this->log("* Custom post type '" . $_post_type . "' :: " . get_the_title($post_id) . ' ( ' . $post_id . ' ).');
				$this->log("* CPT '" . $_post_type . "' ( " . $post_id . ' ) published or edited and its status is published');
				$this->log('* * * * *');
				break;
		}

		$this->log('Function purge_post BEGIN ===');
		$this->_purge_homepage();

		if ('comment_post' === current_filter() || 'wp_set_comment_status' === current_filter()) {

			$this->_purge_by_options(
				$post_id,
				$blog_id,
				true,
				true,
				true,
			);
		} else {

			$this->_purge_by_options(
				$post_id,
				$blog_id,
				true,
				true,
				true
			);
		}

		$this->custom_purge_urls();

		$this->log('Function purge_post END ^^^');
	}

	public function purge_post_optimize($post_id, $post)
	{

		// If WooCommerce is active, then remove the shop cache when adding/modifying new products
		if (is_plugin_active('woocommerce/woocommerce.php')) {
			if ($post->post_type == "product") {
				$shop_id = wc_get_page_id('shop');
				$shop = get_post($shop_id)->post_name;

				$this->purge_post($shop_id);
			}
		}

		$this->purge_post($post_id);
	}

	public function purge_post_optimize_2($post_id, $status)
	{
		$post = get_post($post_id);
		$this->purge_post($post_id);

		// If WooCommerce is active, then remove the shop cache when removing products
		if (is_plugin_active('woocommerce/woocommerce.php')) {
			if ($post->post_type == "product") {
				$shop_id = wc_get_page_id('shop');
				$this->purge_post($shop_id);
			}
		}
	}


	/**
	 * Purge cache by options.
	 *
	 * @param int  $post_id Post id.
	 * @param int  $blog_id Blog id.
	 * @param bool $_purge_page Purge page or not.
	 * @param bool $_purge_archive Purge archive or not.
	 * @param bool $_purge_custom_taxa Purge taxonomy or not.
	 */
	private function _purge_by_options($post_id, $blog_id, $_purge_page, $_purge_archive, $_purge_custom_taxa)
	{

		$_post_type = get_post_type($post_id);

		if ($_purge_page) {

			if ('post' === $_post_type || 'page' === $_post_type) {
				$this->log('Purging ' . $_post_type . ' ( id ' . $post_id . ', blog id ' . $blog_id . ' ) ');
			} else {
				$this->log("Purging custom post type '" . $_post_type . "' ( id " . $post_id . ', blog id ' . $blog_id . ' )');
			}

			$post_status = get_post_status($post_id);

			if ('publish' !== $post_status) {

				if (! function_exists('get_sample_permalink')) {
					require_once ABSPATH . '/wp-admin/includes/post.php';
				}

				$url = get_sample_permalink($post_id);


				if (! empty($url[0]) && ! empty($url[1])) {
					$url_changed = str_replace('%pagename%', $url[1], $url[0]);
					$url_changed = str_replace('%postname%', $url[1], $url_changed);
				} else {
					$url_changed = '';
				}

				$url = $url_changed;
			} else {
				$url = get_permalink($post_id);
			}

			if (empty($url) && ! is_array($url)) {
				return;
			}

			if ('trash' === get_post_status($post_id)) {
				$url = str_replace('__trashed', '', $url);
			}


			$this->purge_url($url);
		}

		if ($_purge_archive) {

			$_post_type_archive_link = get_post_type_archive_link($_post_type);

			if (function_exists('get_post_type_archive_link') && $_post_type_archive_link) {

				$this->log('Purging post type archive ( ' . $_post_type . ' )');
				$this->purge_url($_post_type_archive_link);
			}

			if ('post' === $_post_type) {

				$this->log('Purging date');

				$day   = get_the_time('d', $post_id);
				$month = get_the_time('m', $post_id);
				$year  = get_the_time('Y', $post_id);

				if ($year) {

					$this->purge_url(get_year_link($year));

					if ($month) {

						$this->purge_url(get_month_link($year, $month));

						if ($day) {
							$this->purge_url(get_day_link($year, $month, $day));
						}
					}
				}
			}

			$categories = wp_get_post_categories($post_id);

			if (! is_wp_error($categories)) {

				$this->log('Purging category archives');

				foreach ($categories as $category_id) {

					$this->log('Purging category ' . $category_id);
					$this->purge_url(get_category_link($category_id));
				}
			}

			$tags = get_the_tags($post_id);

			if (! is_wp_error($tags) && ! empty($tags)) {

				$this->log('Purging tag archives');

				foreach ($tags as $tag) {

					$this->log('Purging tag ' . $tag->term_id);
					$this->purge_url(get_tag_link($tag->term_id));
				}
			}

			$author_id = get_post($post_id)->post_author;

			if (! empty($author_id)) {

				$this->log('Purging author archive');
				$this->purge_url(get_author_posts_url($author_id));
			}
		}

		if ($_purge_custom_taxa) {

			$custom_taxonomies = get_taxonomies(
				array(
					'public'   => true,
					'_builtin' => false,
				)
			);

			if (! empty($custom_taxonomies)) {

				$this->log('Purging custom taxonomies related');

				foreach ($custom_taxonomies as $taxon) {

					if (! in_array($taxon, array('category', 'post_tag', 'link_category'), true)) {

						$terms = get_the_terms($post_id, $taxon);

						if (! is_wp_error($terms) && ! empty($terms)) {

							foreach ($terms as $term) {
								$this->purge_url(get_term_link($term, $taxon));
							}
						}
					} else {
						$this->log("Your built-in taxonomy '" . $taxon . "' has param '_builtin' set to false.", 'WARNING');
					}
				}
			}
		}
	}

	/**
	 * Deletes local cache files without a 3rd party nginx module.
	 * Does not require any other modules. Requires that the cache be stored on the same server as WordPress. You must also be using the default nginx cache options (levels=1:2) and (fastcgi_cache_key "$scheme$request_method$host$request_uri").
	 * Read more on how this works here:
	 * https://www.digitalocean.com/community/tutorials/how-to-setup-fastcgi-caching-with-nginx-on-your-vps#purging-the-cache
	 *
	 * @param string $url URL to purge.
	 *
	 * @return bool
	 */
	protected function delete_cache_file_for($url)
	{

		// Verify cache path is set.
		if (! defined('RT_WP_LWS_CACHE_CACHE_PATH')) {

			$this->log('Error purging because RT_WP_LWS_CACHE_CACHE_PATH was not defined. URL: ' . $url, 'ERROR');
			return false;
		}

		// Verify URL is valid.
		$url_data = wp_parse_url($url);
		if (! $url_data) {

			$this->log('Error purging because specified URL did not appear to be valid. URL: ' . $url, 'ERROR');
			return false;
		}

		// Build a hash of the URL.
		$hash = md5($url_data['scheme'] . 'GET' . $url_data['host'] . $url_data['path']);

		// Ensure trailing slash.
		$cache_path = RT_WP_LWS_CACHE_CACHE_PATH;
		$cache_path = ('/' === substr($cache_path, -1)) ? $cache_path : $cache_path . '/';

		// Set path to cached file.
		$cached_file = $cache_path . substr($hash, -1) . '/' . substr($hash, -3, 2) . '/' . $hash;

		/**
		 * Filters the cached file name.
		 *
		 * @since 1.0
		 *
		 * @param string $cached_file Cached file name.
		 */
		$cached_file = apply_filters('rt_lws_cache_purge_cached_file', $cached_file);

		// Verify cached file exists.
		if (! file_exists($cached_file)) {

			$this->log('- - ' . $url . ' is currently not cached ( checked for file: ' . $cached_file . ' )');
			return false;
		}

		// Delete the cached file.
		if (unlink($cached_file)) {
			$this->log('- - ' . $url . ' *** PURGED ***');

			/**
			 * Fire an action after deleting file from cache.
			 *
			 * @since 1.0
			 *
			 * @param string $url         URL to be purged.
			 * @param string $cached_file Cached file name.
			 */
			do_action('rt_lws_cache_purged_file', $url, $cached_file);
		} else {
			$this->log('- - An error occurred deleting the cache file. Check the server logs for a PHP warning.', 'ERROR');
		}
	}

	/**
	 * Remote get data from url.
	 *
	 * @param string $url URL to do remote request.
	 */
	protected function do_remote_get($url)
	{
		/**
		 * Filters the URL to be purged.
		 *
		 * @since 1.0
		 *
		 * @param string $url URL to be purged.
		 */
		$url = apply_filters('rt_lws_cache_remote_purge_url', $url);

		/**
		 * Fire an action before purging URL.
		 *
		 * @since 1.0
		 *
		 * @param string $url URL to be purged.
		 */
		do_action('rt_lws_cache_before_remote_purge_url', $url);

		$response = wp_remote_get($url, array('headers' => array('lwsapitoken' => getenv('lwsapitoken')), 'sslverify' => false));

		if (is_object($response) && get_class($response) == 'WP_Error')
			$this->log("$url : Erreur lors de l'execution de la requete\n");
		else
			$this->log("$url : {$response['body']}\n");

		if (is_wp_error($response)) {

			$_errors_str = implode(' - ', $response->get_error_messages());
			$this->log('Error while purging URL. ' . $_errors_str, 'ERROR');
		} else {

			if ($response['response']['code']) {

				switch ($response['response']['code']) {

					case 200:
						$this->log('- - ' . $url . ' *** PURGED ***');
						break;
					case 404:
						$this->log('- - ' . $url . ' is currently not cached');
						break;
					default:
						$this->log('- - ' . $url . ' not found ( ' . $response['response']['code'] . ' )', 'WARNING');
				}
			}

			/**
			 * Fire an action after remote purge request.
			 *
			 * @since 1.0
			 *
			 * @param string $url      URL to be purged.
			 * @param array  $response Array of results including HTTP headers.
			 */
			do_action('rt_lws_cache_after_remote_purge_url', $url, $response);
		}
	}

	/**
	 * Check http connection.
	 *
	 * @return string
	 */
	public function check_http_connection()
	{

		$purge_url = plugins_url('nginx-manager/check-proxy.php');
		$response  = wp_remote_get($purge_url);

		if (! is_wp_error($response) && ('HTTP Connection OK' === $response['body'])) {
			return 'OK';
		}

		return 'KO';
	}

	/**
	 * Log file.
	 *
	 * @param string $msg Message to log.
	 * @param string $level Level.
	 *
	 * @return bool|void
	 */
	public function log($msg, $level = 'INFO')
	{

		global $lws_cache_admin;

		if (! $lws_cache_admin->options['enable_log']) {
			return false;
		}

		$log_levels = array(
			'INFO'    => 0,
			'WARNING' => 1,
			'ERROR'   => 2,
			'NONE'    => 3,
		);

		if (! isset($lws_cache_admin->options['log_level']))
			$lws_cache_admin->options['log_level'] = 'INFO';

		if ($log_levels[$level] >= $log_levels[$lws_cache_admin->options['log_level']]) {

			if (file_exists($lws_cache_admin->functional_asset_path() . 'nginx.log')) {
				$fp = fopen($lws_cache_admin->functional_asset_path() . 'nginx.log', 'a+');
				if ($fp) {

					fwrite($fp, "\n" . gmdate('Y-m-d H:i:s ') . ' | ' . $level . ' | ' . $msg);
					fclose($fp);
				}
			}
		}

		return true;
	}

	/**
	 * Check and truncate log file.
	 */
	public function check_and_truncate_log_file()
	{

		global $lws_cache_admin;

		if (! $lws_cache_admin->options['enable_log']) {
			return;
		}

		$nginx_asset_path = $lws_cache_admin->functional_asset_path() . 'nginx.log';

		if (! file_exists($nginx_asset_path)) {
			return;
		}

		$max_size_allowed = (is_numeric($lws_cache_admin->options['log_filesize'])) ? $lws_cache_admin->options['log_filesize'] * 1048576 : 5242880;

		$file_size = filesize($nginx_asset_path);

		if ($file_size > $max_size_allowed) {

			$offset       = $file_size - $max_size_allowed;
			$file_content = file_get_contents($nginx_asset_path, null, null, $offset);
			$file_content = empty($file_content) ? '' : strstr($file_content, "\n");

			if (file_exists($nginx_asset_path)) {
				$fp = fopen($nginx_asset_path, 'w+');
				if ($file_content && $fp) {

					fwrite($fp, $file_content);
					fclose($fp);
				}
			}
		}
	}

	/**
	 * Purge image on edit.
	 *
	 * @param int $attachment_id Attachment id.
	 */
	public function purge_image_on_edit($attachment_id)
	{

		$this->log('Purging media on edit BEGIN ===');

		if (wp_attachment_is_image($attachment_id)) {

			$this->purge_url(wp_get_attachment_url($attachment_id), false);
			$attachment = wp_get_attachment_metadata($attachment_id);

			if (! empty($attachment['sizes']) && is_array($attachment['sizes'])) {

				foreach (array_keys($attachment['sizes']) as $size_name) {

					$resize_image = wp_get_attachment_image_src($attachment_id, $size_name);

					if ($resize_image) {
						$this->purge_url($resize_image[0], false);
					}
				}
			}

			$this->purge_url(get_attachment_link($attachment_id));
		} else {
			$this->log('Media ( id ' . $attachment_id . ') edited: no image', 'WARNING');
		}

		$this->log('Purging media on edit END ^^^');
	}

	/**
	 * Purge cache on post moved to trash.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post Post object.
	 *
	 * @return bool|void
	 */
	public function purge_on_post_moved_to_trash($new_status, $old_status, $post)
	{

		global $blog_id;

		if ('trash' === $new_status) {

			$this->log('# # # # #');
			$this->log("# Post '$post->post_title' (  id " . $post->ID . ' ) moved to the trash.');
			$this->log('# # # # #');
			$this->log('Function purge_on_post_moved_to_trash ( post id ' . $post->ID . ' ) BEGIN ===');

			$this->_purge_homepage();

			$this->_purge_by_options(
				$post->ID,
				$blog_id,
				true,
				true,
				true
			);

			$this->log('Function purge_on_post_moved_to_trash ( post id ' . $post->ID . ' ) END ===');
		}

		return true;
	}

	/**
	 * Purge cache of homepage.
	 *
	 * @return bool
	 */
	private function _purge_homepage()
	{

		// WPML installetd?.
		if (function_exists('icl_get_home_url')) {

			$homepage_url = trailingslashit(icl_get_home_url());
			$this->log(sprintf(__('Purging homepage (WPML) ', 'lwscache') . '%s', $homepage_url));
		} else {

			$homepage_url = trailingslashit(home_url());
			$this->log(sprintf(__('Purging homepage ', 'lwscache') . '%s', $homepage_url));
		}

		$this->purge_url($homepage_url);

		return true;
	}

	/**
	 * Purge personal urls.
	 *
	 * @return bool
	 */
	private function _purge_personal_urls()
	{

		global $lws_cache_admin;

		$this->log(__('Purging personal urls', 'lwscache'));

		if (isset($lws_cache_admin->options['purgeable_url']['urls'])) {

			foreach ($lws_cache_admin->options['purgeable_url']['urls'] as $url) {
				$this->purge_url($url, false);
			}
		} else {
			$this->log('- ' . __('No personal urls available', 'lwscache'));
		}

		return true;
	}

	/**
	 * Purge post categories.
	 *
	 * @param int $_post_id Post id.
	 *
	 * @return bool
	 */
	private function _purge_post_categories($_post_id)
	{

		$this->log(__('Purging category archives', 'lwscache'));

		$categories = wp_get_post_categories($_post_id);

		if (! is_wp_error($categories) && ! empty($categories)) {

			foreach ($categories as $category_id) {

				// translators: %d: Category ID.
				$this->log(sprintf(__("Purging category '%d'", 'lwscache'), $category_id));
				$this->purge_url(get_category_link($category_id));
			}
		}

		return true;
	}

	/**
	 * Purge post tags.
	 *
	 * @param int $_post_id Post id.
	 *
	 * @return bool
	 */
	private function _purge_post_tags($_post_id)
	{

		$this->log(__('Purging tags archives', 'lwscache'));

		$tags = get_the_tags($_post_id);

		if (! is_wp_error($tags) && ! empty($tags)) {

			foreach ($tags as $tag) {

				$this->log(sprintf(__("Purging tag '%1\$s' ( id %2\$d )", 'lwscache'), $tag->name, $tag->term_id));
				$this->purge_url(get_tag_link($tag->term_id));
			}
		}

		return true;
	}

	/**
	 * Purge post custom taxonomy data.
	 *
	 * @param int $_post_id Post id.
	 *
	 * @return bool
	 */
	private function _purge_post_custom_taxa($_post_id)
	{

		$this->log(__('Purging post custom taxonomies related', 'lwscache'));

		$custom_taxonomies = get_taxonomies(
			array(
				'public'   => true,
				'_builtin' => false,
			)
		);

		if (! empty($custom_taxonomies)) {

			foreach ($custom_taxonomies as $taxon) {

				// translators: %s: Post taxonomy name.
				$this->log(sprintf('+ ' . __("Purging custom taxonomy '%s'", 'lwscache'), $taxon));

				if (! in_array($taxon, array('category', 'post_tag', 'link_category'), true)) {

					$terms = get_the_terms($_post_id, $taxon);

					if (! is_wp_error($terms) && ! empty($terms) && is_array($terms)) {

						foreach ($terms as $term) {
							$this->purge_url(get_term_link($term, $taxon));
						}
					}
				} else {
					// translators: %s: Post taxonomy name.
					$this->log(sprintf('- ' . __("Your built-in taxonomy '%s' has param '_builtin' set to false.", 'lwscache'), $taxon), 'WARNING');
				}
			}
		} else {
			$this->log('- ' . __('No custom taxonomies', 'lwscache'));
		}

		return true;
	}

	/**
	 * Purge all categories.
	 *
	 * @return bool
	 */
	private function _purge_all_categories()
	{

		$this->log(__('Purging all categories', 'lwscache'));

		$_categories = get_categories();

		if (! empty($_categories)) {

			foreach ($_categories as $c) {

				$this->log(sprintf(__("Purging category '%1\$s' ( id %2\$d )", 'lwscache'), $c->name, $c->term_id));
				$this->purge_url(get_category_link($c->term_id));
			}
		} else {

			$this->log(__('No categories archives', 'lwscache'));
		}

		return true;
	}

	/**
	 * Purge all posttags cache.
	 *
	 * @return bool
	 */
	private function _purge_all_posttags()
	{

		$this->log(__('Purging all tags', 'lwscache'));

		$_posttags = get_tags();

		if (! empty($_posttags)) {

			foreach ($_posttags as $t) {

				$this->log(sprintf(__("Purging tag '%1\$s' ( id %2\$d )", 'lwscache'), $t->name, $t->term_id));
				$this->purge_url(get_tag_link($t->term_id));
			}
		} else {
			$this->log(__('No tags archives', 'lwscache'));
		}

		return true;
	}

	/**
	 * Purge all custom taxonomy data.
	 *
	 * @return bool
	 */
	private function _purge_all_customtaxa()
	{

		$this->log(__('Purging all custom taxonomies', 'lwscache'));

		$custom_taxonomies = get_taxonomies(
			array(
				'public'   => true,
				'_builtin' => false,
			)
		);

		if (! empty($custom_taxonomies)) {

			foreach ($custom_taxonomies as $taxon) {

				// translators: %s: Taxonomy name.
				$this->log(sprintf('+ ' . __("Purging custom taxonomy '%s'", 'lwscache'), $taxon));

				if (! in_array($taxon, array('category', 'post_tag', 'link_category'), true)) {

					$terms = get_terms($taxon);

					if (! is_wp_error($terms) && ! empty($terms)) {

						foreach ($terms as $term) {

							$this->purge_url(get_term_link($term, $taxon));
						}
					}
				} else {
					// translators: %s: Taxonomy name.
					$this->log(sprintf('- ' . esc_html__("Your built-in taxonomy '%s' has param '_builtin' set to false.", 'lwscache'), $taxon), 'WARNING');
				}
			}
		} else {
			$this->log('- ' . __('No custom taxonomies', 'lwscache'));
		}

		return true;
	}

	/**
	 * Purge all taxonomies.
	 *
	 * @return bool
	 */
	private function _purge_all_taxonomies()
	{

		$this->_purge_all_categories();
		$this->_purge_all_posttags();
		$this->_purge_all_customtaxa();

		return true;
	}

	/**
	 * Purge all posts cache.
	 *
	 * @return bool
	 */
	private function _purge_all_posts()
	{

		$this->log(__('Purging all posts, pages and custom post types.', 'lwscache'));

		$args = array(
			'posts_per_page' => 0,
			'post_type'      => 'any',
			'post_status'    => 'publish',
		);

		$get_posts = new WP_Query();
		$_posts    = $get_posts->query($args);

		if (! empty($_posts)) {

			foreach ($_posts as $p) {

				$this->log(sprintf('+ ' . __("Purging post id '%1\$d' ( post type '%2\$s' )", 'lwscache'), $p->ID, $p->post_type));
				$this->purge_url(get_permalink($p->ID));
			}
		} else {
			$this->log('- ' . __('No posts', 'lwscache'));
		}

		return true;
	}

	/**
	 * Purge all archives.
	 *
	 * @return bool
	 */
	private function _purge_all_date_archives()
	{

		$this->log(__('Purging all date-based archives.', 'lwscache'));

		$this->_purge_all_daily_archives();
		$this->_purge_all_monthly_archives();
		$this->_purge_all_yearly_archives();

		return true;
	}

	/**
	 * Purge daily archives cache.
	 */
	private function _purge_all_daily_archives()
	{

		global $wpdb;

		$this->log(__('Purging all daily archives.', 'lwscache'));

		$_query_daily_archives = $wpdb->prepare(
			"SELECT YEAR(post_date) AS %s, MONTH(post_date) AS %s, DAYOFMONTH(post_date) AS %s, count(ID) as posts
			FROM $wpdb->posts
			WHERE post_type = %s AND post_status = %s
			GROUP BY YEAR(post_date), MONTH(post_date), DAYOFMONTH(post_date)
			ORDER BY post_date DESC",
			'year',
			'month',
			'dayofmonth',
			'post',
			'publish'
		);

		$_daily_archives = $wpdb->get_results($_query_daily_archives); // phpcs:ignore

		if (! empty($_daily_archives)) {

			foreach ($_daily_archives as $_da) {

				$this->log(
					sprintf(
						'+ ' . __("Purging daily archive '%1\$s/%2\$s/%3\$s'", 'lwscache'),
						$_da->year,
						$_da->month,
						$_da->dayofmonth
					)
				);

				$this->purge_url(get_day_link($_da->year, $_da->month, $_da->dayofmonth));
			}
		} else {
			$this->log('- ' . __('No daily archives', 'lwscache'));
		}
	}

	/**
	 * Purge all monthly archives.
	 */
	private function _purge_all_monthly_archives()
	{

		global $wpdb;

		$this->log(__('Purging all monthly archives.', 'lwscache'));

		$_monthly_archives = wp_cache_get('lws_cache_monthly_archives', 'lws-cache');

		if (empty($_monthly_archives)) {

			$_query_monthly_archives = $wpdb->prepare(
				"SELECT YEAR(post_date) AS %s, MONTH(post_date) AS %s, count(ID) as posts
				FROM $wpdb->posts
				WHERE post_type = %s AND post_status = %s
				GROUP BY YEAR(post_date), MONTH(post_date)
				ORDER BY post_date DESC",
				'year',
				'month',
				'post',
				'publish'
			);

			$_monthly_archives = $wpdb->get_results($_query_monthly_archives); // phpcs:ignore

			wp_cache_set('lws_cache_monthly_archives', $_monthly_archives, 'lws_cache', 24 * 60 * 60);
		}

		if (! empty($_monthly_archives)) {

			foreach ($_monthly_archives as $_ma) {

				$this->log(sprintf('+ ' . __("Purging monthly archive '%1\$s/%2\$s'", 'lwscache'), $_ma->year, $_ma->month));
				$this->purge_url(get_month_link($_ma->year, $_ma->month));
			}
		} else {
			$this->log('- ' . __('No monthly archives', 'lwscache'));
		}
	}

	/**
	 * Purge all yearly archive cache.
	 */
	private function _purge_all_yearly_archives()
	{

		global $wpdb;

		$this->log(__('Purging all yearly archives.', 'lwscache'));

		$_yearly_archives = wp_cache_get('lws_cache_yearly_archives', 'lws_cache');

		if (empty($_yearly_archives)) {

			$_query_yearly_archives = $wpdb->prepare(
				"SELECT YEAR(post_date) AS %s, count(ID) as posts
				FROM $wpdb->posts
				WHERE post_type = %s AND post_status = %s
				GROUP BY YEAR(post_date)
				ORDER BY post_date DESC",
				'year',
				'post',
				'publish'
			);

			$_yearly_archives = $wpdb->get_results($_query_yearly_archives); // phpcs:ignore

			wp_cache_set('lws_cache_yearly_archives', $_yearly_archives, 'lws_cache', 24 * 60 * 60);
		}

		if (! empty($_yearly_archives)) {

			foreach ($_yearly_archives as $_ya) {

				// translators: %s: Year to purge cache.
				$this->log(sprintf('+ ' . esc_html__("Purging yearly archive '%s'", 'lwscache'), $_ya->year));
				$this->purge_url(get_year_link($_ya->year));
			}
		} else {
			$this->log('- ' . __('No yearly archives', 'lwscache'));
		}
	}

	/**
	 * Purge all cache.
	 *
	 * @return bool
	 */
	public function purge_them_all()
	{

		$this->log(__("Let's purge everything!", 'lwscache'));
		$this->_purge_homepage();
		$this->_purge_personal_urls();
		$this->_purge_all_posts();
		$this->_purge_all_taxonomies();
		$this->_purge_all_date_archives();
		$this->log(__('Everything purged!', 'lwscache'));

		return true;
	}

	/**
	 * Purge cache on term edited.
	 *
	 * @param int    $term_id Term id.
	 * @param int    $tt_id Taxonomy id.
	 * @param string $taxon Taxonomy.
	 *
	 * @return bool
	 */
	public function purge_on_term_taxonomy_edited($term_id, $tt_id, $taxon)
	{
		$this->log(__('Term taxonomy edited or deleted', 'lwscache'));

		$term           = get_term($term_id, $taxon);
		$current_filter = current_filter();

		if ('edit_term' === $current_filter && ! is_wp_error($term) && ! empty($term)) {

			$this->log(sprintf(__("Term taxonomy '%1\$s' edited, (tt_id '%2\$d', term_id '%3\$d', taxonomy '%4\$s')", 'lwscache'), $term->name, $tt_id, $term_id, $taxon));
		} else if ('delete_term' === $current_filter) {

			$this->log(sprintf(__("A term taxonomy has been deleted from taxonomy '%1\$s', (tt_id '%2\$d', term_id '%3\$d')", 'lwscache'), $taxon, $term_id, $tt_id));
		}

		$this->_purge_homepage();

		return true;
	}

	/**
	 * Check ajax referrer on purge.
	 *
	 * @param string $action The Ajax nonce action.
	 *
	 * @return bool
	 */
	public function purge_on_check_ajax_referer($action)
	{

		switch ($action) {

			case 'save-sidebar-widgets':
				$this->log(__('Widget saved, moved or removed in a sidebar', 'lwscache'));
				$this->_purge_homepage();
				break;

			default:
				break;
		}

		return true;
	}

	/**
	 * Unlink file recursively.
	 * Source - http://stackoverflow.com/a/1360437/156336
	 *
	 * @param string $dir Directory.
	 * @param bool   $delete_root_too Delete root or not.
	 *
	 * @return void
	 */
	public function unlink_recursive($dir, $delete_root_too)
	{

		if (! is_dir($dir)) {
			return;
		}

		$dh = opendir($dir);

		if (! $dh) {
			return;
		}

		// phpcs:ignore -- WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Variable assignment required for recursion.
		while (false !== ($obj = readdir($dh))) {

			if ('.' === $obj || '..' === $obj) {
				continue;
			}

			if (! @unlink($dir . '/' . $obj)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$this->unlink_recursive($dir . '/' . $obj, false);
			}
		}

		if ($delete_root_too) {
			rmdir($dir);
		}

		closedir($dh);
	}
}