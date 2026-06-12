<?php declare(strict_types=1);
namespace Gratis\CDN\CLI;

use Gratis\CDN\AssetRewriter;
use Gratis\CDN\PurgeManager;

final class CDNCommand {

    /**
     * Configure CDN domain.
     *
     * ## OPTIONS
     * <url>
     * : CDN URL (e.g., https://cdn.example.com).
     *
     * @when after_wp_load
     */
    public function setup($args, $assoc_args): void {
        unset($assoc_args);
        update_option('gratis_cdn_url', rtrim($args[0], '/'));
        \WP_CLI::success("CDN configured: {$args[0]}");
        \WP_CLI::log('All static assets will be served from this domain.');
    }

    /**
     * Show CDN stats.
     *
     * @when after_wp_load
     */
    public function stats($args, $assoc_args): void {
        unset($args, $assoc_args);
        $stats = AssetRewriter::getStats();
        \WP_CLI::log("CDN URL: {$stats['cdn_url']}");
        \WP_CLI::log("Upload files: {$stats['files']} ({$stats['size_mb']} MB)");
        \WP_CLI::log('Fingerprinting: ' . (get_option('gratis_cdn_fingerprint', true) ? 'enabled' : 'disabled'));
        \WP_CLI::log('Purge provider: ' . get_option('gratis_cdn_provider', 'none'));
    }

    /**
     * Purge the CDN cache.
     *
     * @when after_wp_load
     */
    public function purge($args, $assoc_args): void {
        unset($args, $assoc_args);
        $result = PurgeManager::purgeAll();
        if ($result['success']) {
            \WP_CLI::success($result['message'] ?? 'CDN cache purged.');
        } else {
            \WP_CLI::error($result['error'] ?? 'Purge failed.');
        }
    }

    /**
     * Disable CDN rewriting.
     *
     * @when after_wp_load
     */
    public function disable($args, $assoc_args): void {
        unset($args, $assoc_args);
        delete_option('gratis_cdn_url');
        \WP_CLI::success('CDN disabled. Assets served from origin.');
    }
}
