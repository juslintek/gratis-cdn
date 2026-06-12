<?php declare(strict_types=1);
namespace Gratis\CDN;

/**
 * Admin settings page for Gratis CDN.
 *
 * Configures the CDN domain, fingerprinting/cache-busting, the purge
 * provider + credentials, and exposes a one-click "Purge CDN cache" button
 * plus the REST purge key.
 */
final class Settings {

    private const PAGE = 'gratis-cdn';

    public static function registerMenu(): void {
        add_options_page(
            __('Gratis CDN', 'gratis-cdn'),
            __('Gratis CDN', 'gratis-cdn'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render']
        );
    }

    public static function registerSettings(): void {
        register_setting('gratis_cdn', 'gratis_cdn_url', [
            'type'              => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitizeUrl'],
            'default'           => '',
        ]);
        register_setting('gratis_cdn', 'gratis_cdn_fingerprint', [
            'type'              => 'boolean',
            'sanitize_callback' => [__CLASS__, 'sanitizeBool'],
            'default'           => true,
        ]);
        register_setting('gratis_cdn', 'gratis_cdn_provider', [
            'type'              => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitizeProvider'],
            'default'           => 'none',
        ]);

        foreach (self::credentialKeys() as $key) {
            $masked = in_array($key, self::maskedKeys(), true);
            register_setting('gratis_cdn', $key, [
                'type'              => 'string',
                'sanitize_callback' => $masked
                    ? static function ($value) use ($key): string {
                        $value = trim((string) $value);
                        // Masked sentinel means "keep the stored secret".
                        if ('********' === $value) {
                            return (string) get_option($key, '');
                        }
                        return sanitize_text_field($value);
                    }
                    : [__CLASS__, 'sanitizeSecret'],
                'default'           => '',
            ]);
        }
    }

    /** @return string[] Credential option keys that should be masked in the UI. */
    private static function maskedKeys(): array {
        return [
            'gratis_cdn_cf_token',
            'gratis_cdn_bunny_key',
            'gratis_cdn_keycdn_key',
        ];
    }

    /** @return string[] */
    private static function credentialKeys(): array {
        return [
            'gratis_cdn_cf_zone',
            'gratis_cdn_cf_token',
            'gratis_cdn_bunny_zone',
            'gratis_cdn_bunny_key',
            'gratis_cdn_keycdn_zone',
            'gratis_cdn_keycdn_key',
            'gratis_cdn_webhook_url',
        ];
    }

    public static function sanitizeUrl($value): string {
        return esc_url_raw(trim((string) $value));
    }

    public static function sanitizeBool($value): bool {
        return (bool) $value;
    }

    public static function sanitizeProvider($value): string {
        $value = sanitize_key((string) $value);
        $allowed = ['none', 'cloudflare', 'bunny', 'keycdn', 'webhook'];
        return in_array($value, $allowed, true) ? $value : 'none';
    }

    /**
     * Sanitise a non-masked credential / config string.
     */
    public static function sanitizeSecret($value): string {
        return sanitize_text_field(trim((string) $value));
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        $msg = isset($_GET['gratis_cdn_msg']) ? sanitize_key(wp_unslash($_GET['gratis_cdn_msg'])) : '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gratis CDN', 'gratis-cdn') . '</h1>';

        if ('purged' === $msg) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('CDN cache purged.', 'gratis-cdn') . '</p></div>';
        } elseif ('purge_failed' === $msg) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('CDN purge failed. Check your provider credentials.', 'gratis-cdn') . '</p></div>';
        }

        $provider = (string) get_option('gratis_cdn_provider', 'none');

        echo '<form method="post" action="options.php">';
        settings_fields('gratis_cdn');

        echo '<table class="form-table" role="presentation">';

        // CDN URL.
        echo '<tr><th scope="row"><label for="gratis_cdn_url">' . esc_html__('CDN URL', 'gratis-cdn') . '</label></th><td>';
        echo '<input type="url" id="gratis_cdn_url" name="gratis_cdn_url" class="regular-text" placeholder="https://cdn.example.com" value="' . esc_attr((string) get_option('gratis_cdn_url', '')) . '">';
        echo '<p class="description">' . esc_html__('Static assets and uploads are rewritten to this domain. Leave empty to disable rewriting.', 'gratis-cdn') . '</p>';
        echo '</td></tr>';

        // Fingerprinting.
        echo '<tr><th scope="row">' . esc_html__('Cache busting', 'gratis-cdn') . '</th><td>';
        echo '<label><input type="checkbox" name="gratis_cdn_fingerprint" value="1" ' . checked((bool) get_option('gratis_cdn_fingerprint', true), true, false) . '> ';
        echo esc_html__('Append a content hash (?v=…) to CSS/JS so updated files bust the cache automatically.', 'gratis-cdn') . '</label>';
        echo '</td></tr>';

        // Provider select.
        echo '<tr><th scope="row"><label for="gratis_cdn_provider">' . esc_html__('Purge provider', 'gratis-cdn') . '</label></th><td>';
        echo '<select id="gratis_cdn_provider" name="gratis_cdn_provider">';
        foreach (self::providerLabels() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($provider, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Used by the purge button, the REST purge API, and automatic purges when the page cache clears.', 'gratis-cdn') . '</p>';
        echo '</td></tr>';

        self::credentialRow('gratis_cdn_cf_zone', __('Cloudflare zone ID', 'gratis-cdn'), false);
        self::credentialRow('gratis_cdn_cf_token', __('Cloudflare API token', 'gratis-cdn'), true);
        self::credentialRow('gratis_cdn_bunny_zone', __('BunnyCDN pull-zone ID', 'gratis-cdn'), false);
        self::credentialRow('gratis_cdn_bunny_key', __('BunnyCDN API key', 'gratis-cdn'), true);
        self::credentialRow('gratis_cdn_keycdn_zone', __('KeyCDN zone ID', 'gratis-cdn'), false);
        self::credentialRow('gratis_cdn_keycdn_key', __('KeyCDN API key', 'gratis-cdn'), true);
        self::credentialRow('gratis_cdn_webhook_url', __('Purge webhook URL', 'gratis-cdn'), false);

        echo '</table>';
        submit_button();
        echo '</form>';

        // Purge action + REST key.
        echo '<hr><h2>' . esc_html__('Purge CDN cache', 'gratis-cdn') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="gratis_cdn_purge">';
        wp_nonce_field('gratis_cdn_purge');
        submit_button(__('Purge everything now', 'gratis-cdn'), 'secondary', 'submit', false);
        echo '</form>';

        $key = self::purgeKey();
        echo '<h2>' . esc_html__('REST purge API', 'gratis-cdn') . '</h2>';
        echo '<p>' . esc_html__('Trigger a purge from automation with:', 'gratis-cdn') . '</p>';
        echo '<pre style="background:#1e1e1e;color:#e2e8f0;padding:12px;border-radius:6px;overflow-x:auto">';
        echo esc_html('curl -X POST ' . rest_url(PurgeManager::REST_NAMESPACE . '/purge') . " \\\n  -H 'X-CDN-Purge-Key: " . $key . "'");
        echo '</pre>';

        echo '</div>';
    }

    /** @return array<string,string> */
    private static function providerLabels(): array {
        return [
            'none'       => __('None / manual', 'gratis-cdn'),
            'cloudflare' => 'Cloudflare',
            'bunny'      => 'BunnyCDN',
            'keycdn'     => 'KeyCDN',
            'webhook'    => __('Generic webhook', 'gratis-cdn'),
        ];
    }

    private static function credentialRow(string $option, string $label, bool $masked): void {
        $stored = (string) get_option($option, '');
        $value  = $masked && '' !== $stored ? '********' : $stored;
        $type   = $masked ? 'password' : 'text';

        echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($option) . '" name="' . esc_attr($option) . '" class="regular-text" autocomplete="new-password" value="' . esc_attr($value) . '">';
        echo '</td></tr>';
    }

    /** Get (and lazily generate) the REST purge key. */
    private static function purgeKey(): string {
        $key = (string) get_option('gratis_cdn_purge_key', '');
        if ('' === $key) {
            $key = wp_generate_password(32, false);
            update_option('gratis_cdn_purge_key', $key);
        }
        return $key;
    }
}
