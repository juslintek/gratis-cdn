<?php
/**
 * Plugin Name: Gratis CDN
 * Description: Asset fingerprinting, cookie-free domain, image CDN rewrite, Brotli hints. Replaces CDN plugins ($10-50/mo).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';
add_action('plugins_loaded', function () {
    \Gratis\CDN\AssetRewriter::init();
    if (defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('gratis-cdn', \Gratis\CDN\CLI\CDNCommand::class);
});
