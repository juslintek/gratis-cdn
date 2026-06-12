<?php declare(strict_types=1);
namespace Gratis\CDN;

/**
 * CDN cache purge API.
 *
 * Supports one-click "purge everything" against the common edge CDNs
 * (Cloudflare, BunnyCDN, KeyCDN) plus a generic webhook for anything else.
 * Also exposes an authenticated REST endpoint so other Gratis plugins
 * (and external automations) can trigger a purge, and bridges the
 * suite-wide `gratis_cache_purge_all` action so clearing the page cache
 * also flushes the edge.
 */
final class PurgeManager {

    public const REST_NAMESPACE = 'gratis-cdn/v1';

    public static function init(): void {
        // When the page cache is purged suite-wide, flush the CDN edge too.
        add_action('gratis_cache_purge_all', [__CLASS__, 'purgeAll']);

        // Manual purge from the settings screen.
        add_action('admin_post_gratis_cdn_purge', [__CLASS__, 'handlePurgeAction']);

        add_action('rest_api_init', [__CLASS__, 'registerRoutes']);
    }

    public static function registerRoutes(): void {
        register_rest_route(self::REST_NAMESPACE, '/purge', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'restPurge'],
            'permission_callback' => [__CLASS__, 'authenticate'],
            'args'                => [
                'urls' => [
                    'required' => false,
                    'type'     => 'array',
                ],
            ],
        ]);
    }

    /** Authenticate a REST purge request via the shared CDN purge key. */
    public static function authenticate(\WP_REST_Request $request): bool {
        // Logged-in admins can always purge.
        if (current_user_can('manage_options')) {
            return true;
        }
        $key    = (string) $request->get_header('X-CDN-Purge-Key');
        $stored = (string) get_option('gratis_cdn_purge_key', '');
        return '' !== $stored && '' !== $key && hash_equals($stored, $key);
    }

    public static function restPurge(\WP_REST_Request $request): \WP_REST_Response {
        $urls = (array) $request->get_param('urls');
        $urls = array_values(array_filter(array_map('esc_url_raw', $urls)));

        $result = empty($urls) ? self::purgeAll() : self::purgeUrls($urls);

        return new \WP_REST_Response($result, $result['success'] ? 200 : 502);
    }

    /** Handle the admin-post purge button (nonce + capability gated). */
    public static function handlePurgeAction(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'gratis-cdn'));
        }
        check_admin_referer('gratis_cdn_purge');

        $result = self::purgeAll();
        $flag   = $result['success'] ? 'purged' : 'purge_failed';

        wp_safe_redirect(add_query_arg('gratis_cdn_msg', $flag, wp_get_referer() ?: admin_url('options-general.php?page=gratis-cdn')));
        exit;
    }

    /** Purge the entire CDN cache for the configured provider. */
    public static function purgeAll(): array {
        $provider = (string) get_option('gratis_cdn_provider', 'none');

        $result = match ($provider) {
            'cloudflare' => self::purgeCloudflare(true),
            'bunny'      => self::purgeBunny(),
            'keycdn'     => self::purgeKeyCDN(),
            'webhook'    => self::purgeWebhook([]),
            default      => ['success' => true, 'provider' => 'none', 'message' => 'No CDN provider configured; nothing to purge.'],
        };

        /**
         * Fires after a full CDN purge is attempted.
         *
         * @param array $result Purge result payload.
         */
        do_action('gratis_cdn_purged', $result);
        return $result;
    }

    /** Purge a specific list of URLs (falls back to full purge when unsupported). */
    public static function purgeUrls(array $urls): array {
        $provider = (string) get_option('gratis_cdn_provider', 'none');

        return match ($provider) {
            'cloudflare' => self::purgeCloudflare(false, $urls),
            'webhook'    => self::purgeWebhook($urls),
            // BunnyCDN/KeyCDN per-file purge differs by zone; full purge is the safe default.
            'bunny', 'keycdn' => self::purgeAll(),
            default      => ['success' => true, 'provider' => 'none', 'message' => 'No CDN provider configured.'],
        };
    }

    private static function purgeCloudflare(bool $everything, array $urls = []): array {
        $zone  = (string) get_option('gratis_cdn_cf_zone', '');
        $token = (string) get_option('gratis_cdn_cf_token', '');
        if ('' === $zone || '' === $token) {
            return ['success' => false, 'provider' => 'cloudflare', 'error' => 'Missing Cloudflare zone ID or API token.'];
        }

        $body = $everything || empty($urls) ? ['purge_everything' => true] : ['files' => array_values($urls)];

        $response = wp_remote_post(
            'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone) . '/purge_cache',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($body),
                'timeout' => 15,
            ]
        );

        return self::interpret('cloudflare', $response);
    }

    private static function purgeBunny(): array {
        $zone = (string) get_option('gratis_cdn_bunny_zone', '');
        $key  = (string) get_option('gratis_cdn_bunny_key', '');
        if ('' === $zone || '' === $key) {
            return ['success' => false, 'provider' => 'bunny', 'error' => 'Missing BunnyCDN pull zone ID or API key.'];
        }

        $response = wp_remote_post(
            'https://api.bunny.net/pullzone/' . rawurlencode($zone) . '/purgeCache',
            [
                'headers' => [
                    'AccessKey' => $key,
                    'Accept'    => 'application/json',
                ],
                'timeout' => 15,
            ]
        );

        return self::interpret('bunny', $response);
    }

    private static function purgeKeyCDN(): array {
        $zone = (string) get_option('gratis_cdn_keycdn_zone', '');
        $key  = (string) get_option('gratis_cdn_keycdn_key', '');
        if ('' === $zone || '' === $key) {
            return ['success' => false, 'provider' => 'keycdn', 'error' => 'Missing KeyCDN zone ID or API key.'];
        }

        $response = wp_remote_get(
            'https://api.keycdn.com/zones/purge/' . rawurlencode($zone) . '.json',
            [
                'headers' => ['Authorization' => 'Basic ' . base64_encode($key . ':')], // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth credential transport, not obfuscation.
                'timeout' => 15,
            ]
        );

        return self::interpret('keycdn', $response);
    }

    private static function purgeWebhook(array $urls): array {
        $endpoint = (string) get_option('gratis_cdn_webhook_url', '');
        if ('' === $endpoint) {
            return ['success' => false, 'provider' => 'webhook', 'error' => 'No purge webhook URL configured.'];
        }

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['site' => home_url('/'), 'urls' => array_values($urls)]),
                'timeout' => 15,
            ]
        );

        return self::interpret('webhook', $response);
    }

    /** Normalise an HTTP API response into a success/error payload. */
    private static function interpret(string $provider, $response): array {
        if (is_wp_error($response)) {
            return ['success' => false, 'provider' => $provider, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'provider' => $provider, 'message' => 'CDN cache purged.'];
        }

        return [
            'success'  => false,
            'provider' => $provider,
            'error'    => sprintf('CDN API returned HTTP %d.', $code),
        ];
    }
}
