<?php declare(strict_types=1);
namespace Gratis\CDN;

final class AssetRewriter
{
    public static function init(): void
    {
        $cdnUrl = get_option('gratis_cdn_url');
        if (!$cdnUrl) return;

        // Rewrite static assets to CDN domain
        add_filter('style_loader_src', fn($src) => self::rewrite($src, $cdnUrl));
        add_filter('script_loader_src', fn($src) => self::rewrite($src, $cdnUrl));
        add_filter('wp_get_attachment_url', fn($url) => self::rewrite($url, $cdnUrl));
        add_filter('the_content', fn($content) => self::rewriteContent($content, $cdnUrl));

        // Add fingerprinting (cache-busting via content hash)
        if (get_option('gratis_cdn_fingerprint', true)) {
            add_filter('style_loader_src', [__CLASS__, 'fingerprint'], 20);
            add_filter('script_loader_src', [__CLASS__, 'fingerprint'], 20);
        }

        // Preload hints
        add_action('wp_head', [__CLASS__, 'preloadHints'], 1);
    }

    private static function rewrite(string $url, string $cdnUrl): string
    {
        $siteUrl = site_url();
        if (str_starts_with($url, $siteUrl)) {
            return str_replace($siteUrl, rtrim($cdnUrl, '/'), $url);
        }
        return $url;
    }

    private static function rewriteContent(string $content, string $cdnUrl): string
    {
        $uploadDir = wp_get_upload_dir();
        $uploadUrl = $uploadDir['baseurl'];
        return str_replace($uploadUrl, rtrim($cdnUrl, '/') . '/wp-content/uploads', $content);
    }

    public static function fingerprint(string $src): string
    {
        if (!str_contains($src, site_url())) return $src;
        $path = str_replace(site_url(), ABSPATH, parse_url($src, PHP_URL_PATH));
        if (file_exists($path)) {
            $hash = substr(md5_file($path), 0, 8);
            $src = add_query_arg('v', $hash, $src);
        }
        return $src;
    }

    public static function preloadHints(): void
    {
        // Preconnect to CDN domain
        $cdnUrl = get_option('gratis_cdn_url');
        if ($cdnUrl) {
            echo '<link rel="preconnect" href="' . esc_url($cdnUrl) . '" crossorigin>' . "\n";
            echo '<link rel="dns-prefetch" href="' . esc_url($cdnUrl) . '">' . "\n";
        }
    }

    /** Get stats about rewritable assets. */
    public static function getStats(): array
    {
        $uploadDir = wp_get_upload_dir();
        $size = 0; $count = 0;
        if (is_dir($uploadDir['basedir'])) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploadDir['basedir'], \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) { $size += $f->getSize(); $count++; }
        }
        return ['files' => $count, 'size_mb' => round($size / 1048576, 1), 'cdn_url' => get_option('gratis_cdn_url', 'not configured')];
    }
}
