<?php declare(strict_types=1);
namespace Gratis\CDN\CLI;

use Gratis\CDN\AssetRewriter;

final class CDNCommand
{
    /**
     * Configure CDN domain.
     * <url> : CDN URL (e.g., https://cdn.example.com).
     * @when after_wp_load
     */
    public function setup($args, $assoc_args): void
    {
        update_option('gratis_cdn_url', rtrim($args[0], '/'));
        \WP_CLI::success("CDN configured: {$args[0]}");
        \WP_CLI::log("All static assets will be served from this domain.");
    }

    /**
     * Show CDN stats.
     * @when after_wp_load
     */
    public function stats($args, $assoc_args): void
    {
        $stats = AssetRewriter::getStats();
        \WP_CLI::log("CDN URL: {$stats['cdn_url']}");
        \WP_CLI::log("Upload files: {$stats['files']} ({$stats['size_mb']} MB)");
        \WP_CLI::log("Fingerprinting: " . (get_option('gratis_cdn_fingerprint', true) ? 'enabled' : 'disabled'));
    }

    /**
     * Disable CDN rewriting.
     * @when after_wp_load
     */
    public function disable($args, $assoc_args): void
    {
        delete_option('gratis_cdn_url');
        \WP_CLI::success("CDN disabled. Assets served from origin.");
    }
}
