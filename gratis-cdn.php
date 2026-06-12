<?php
/**
 * Plugin Name: Gratis CDN
 * Plugin URI: https://github.com/juslintek/gratis-cdn
 * Description: Asset URL rewriting, content-hash cache busting, and a one-click CDN purge API (Cloudflare, BunnyCDN, KeyCDN, or webhook). Replaces CDN plugins ($10-50/mo).
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-cdn
 */

defined('ABSPATH') || exit;

define('GRATIS_CDN_VERSION', '1.0.0');
define('GRATIS_CDN_FILE', __FILE__);
define('GRATIS_CDN_DIR', plugin_dir_path(__FILE__));
define('GRATIS_CDN_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('plugins_loaded', function () {
    \Gratis\CDN\AssetRewriter::init();
    \Gratis\CDN\PurgeManager::init();

    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-cdn', \Gratis\CDN\CLI\CDNCommand::class);
    }
});

add_action('admin_menu', [\Gratis\CDN\Settings::class, 'registerMenu']);
add_action('admin_init', [\Gratis\CDN\Settings::class, 'registerSettings']);
