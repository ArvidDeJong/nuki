<?php

declare(strict_types=1);

namespace Darvis\Nuki\Support;

/**
 * The one place that reads the package config. Callers ask this class, so a default is written
 * once and a caller cannot quietly disagree with config/nuki.php about what it is.
 *
 * Settings of the host application (app.locale, auth.*, cache.*) are not this package's, and are
 * read where they are needed.
 */
final class NukiConfig
{
    // ---------------------------------------------------------------- API and authentication

    /**
     * Base URL of the NUKI Web API.
     */
    public static function baseUrl(): string
    {
        return (string) config('nuki.base_url', 'https://api.nuki.io');
    }

    /**
     * Public NUKI portal, where a customer generates a personal API token.
     */
    public static function webUrl(): string
    {
        return (string) config('nuki.web_url', 'https://web.nuki.io');
    }

    /**
     * Authentication method: "token" or "oauth".
     */
    public static function authMethod(): string
    {
        return (string) config('nuki.auth', 'token');
    }

    /**
     * Where a token is looked up: "config" (single account) or "database" (multi account).
     */
    public static function tokenResolver(): string
    {
        return (string) config('nuki.token_resolver', 'database');
    }

    /**
     * The personal API token for the default account, or null when there is none.
     */
    public static function apiToken(): ?string
    {
        return self::string('nuki.token');
    }

    // ---------------------------------------------------------------- OAuth

    /**
     * The whole OAuth block, for the classes that take it as one array.
     *
     * @return array<string, mixed>
     */
    public static function oauth(): array
    {
        return (array) config('nuki.oauth', []);
    }

    /**
     * Where issued OAuth tokens are kept: "cache" or "database".
     */
    public static function oauthTokenStore(): string
    {
        return (string) config('nuki.oauth.token_store', 'cache');
    }

    /**
     * Cache store for OAuth tokens, or null for the application default.
     */
    public static function oauthCacheStore(): ?string
    {
        return self::string('nuki.oauth.cache_store');
    }

    /**
     * Key prefix for OAuth tokens in the cache.
     */
    public static function oauthCachePrefix(): string
    {
        return (string) config('nuki.oauth.cache_prefix', 'nuki:oauth:');
    }

    // ---------------------------------------------------------------- Webhooks

    /**
     * Whether the webhook route is registered.
     */
    public static function webhookEnabled(): bool
    {
        return config('nuki.webhook.enabled') === true;
    }

    /**
     * Path the NUKI callbacks arrive on.
     */
    public static function webhookRoute(): string
    {
        return (string) config('nuki.webhook.route', '/nuki/webhook');
    }

    /**
     * Middleware the webhook route runs through.
     *
     * @return list<string>
     */
    public static function webhookMiddleware(): array
    {
        return self::stringList('nuki.webhook.middleware', ['api']);
    }

    /**
     * Shared secret the HMAC signature is checked against, or null when there is none.
     */
    public static function webhookSecret(): ?string
    {
        return self::string('nuki.webhook.secret');
    }

    /**
     * Whether the HMAC signature is checked at all. Without a secret every call is refused;
     * set this to false to accept unsigned calls, for example behind your own gateway.
     * Only an explicit false turns the check off, so a typo leaves it on.
     */
    public static function webhookVerifySignature(): bool
    {
        return config('nuki.webhook.verify_signature', true) !== false;
    }

    /**
     * Header NUKI sends the signature in.
     */
    public static function webhookSignatureHeader(): string
    {
        return (string) config('nuki.webhook.signature_header', 'X-Nuki-Signature');
    }

    /**
     * Seconds an event id is remembered, so a repeat delivery is ignored.
     */
    public static function webhookDedupTtl(): int
    {
        return (int) config('nuki.webhook.dedup_ttl', 600);
    }

    // ---------------------------------------------------------------- UI

    /**
     * Whether the bundled Livewire pages are registered.
     */
    public static function uiEnabled(): bool
    {
        return config('nuki.ui.enabled') === true;
    }

    /**
     * Name shown in the UI.
     */
    public static function uiBrand(): string
    {
        return (string) config('nuki.ui.brand', 'NUKI');
    }

    /**
     * Line under the brand, or null for none.
     */
    public static function uiTagline(): ?string
    {
        return self::string('nuki.ui.tagline');
    }

    /**
     * Blade layout the pages extend.
     */
    public static function uiLayout(): string
    {
        return (string) config('nuki.ui.layout', 'nuki::layouts.app');
    }

    /**
     * URL prefix of the bundled pages.
     */
    public static function uiPrefix(): string
    {
        return (string) config('nuki.ui.prefix', 'nuki');
    }

    /**
     * Middleware the bundled pages run through.
     *
     * @return list<string>
     */
    public static function uiMiddleware(): array
    {
        return self::stringList('nuki.ui.middleware', ['web']);
    }

    /**
     * Locale the UI starts in, falling back to the application locale.
     */
    public static function uiDefaultLocale(): string
    {
        $locale = self::string('nuki.ui.default_locale');

        return $locale ?? (string) config('app.locale', 'en');
    }

    /**
     * The locales the UI offers, as code => label. Also the allow list for switching.
     * An empty list falls back to English, because a UI with no locale at all cannot render.
     *
     * @return array<string, string>
     */
    public static function uiLocales(): array
    {
        $locales = config('nuki.ui.locales');

        if (! is_array($locales) || $locales === []) {
            return ['en' => 'English'];
        }

        return array_map('strval', $locales);
    }

    /**
     * Logo for light mode, or null for the icon fallback.
     */
    public static function uiLogoLight(): ?string
    {
        return self::string('nuki.ui.logo.light');
    }

    /**
     * Logo for dark mode, or null for the icon fallback.
     */
    public static function uiLogoDark(): ?string
    {
        return self::string('nuki.ui.logo.dark');
    }

    /**
     * Footer links, each ['label' => ..., 'url' => ...].
     *
     * @return array<int, array<string, string>>
     */
    public static function uiFooterLinks(): array
    {
        return array_values(array_filter((array) config('nuki.ui.footer.links', []), 'is_array'));
    }

    /**
     * Whether the account panel is shown in the UI.
     */
    public static function uiAuthPanelEnabled(): bool
    {
        return (bool) config('nuki.ui.auth_panel.enabled', true);
    }

    // ---------------------------------------------------------------- Package users

    /**
     * Whether the package brings its own users, guard and permissions.
     */
    public static function authUsersEnabled(): bool
    {
        return config('nuki.auth_users.enabled') === true;
    }

    /**
     * Whether visitors may register an account themselves.
     */
    public static function registerEnabled(): bool
    {
        return config('nuki.auth_users.register_enabled', true) === true;
    }

    /**
     * Where a user lands after signing in.
     */
    public static function redirectAfterLogin(): string
    {
        return (string) config('nuki.auth_users.redirect_after_login', '/nuki');
    }

    /**
     * Where a user lands after signing out.
     */
    public static function redirectAfterLogout(): string
    {
        return (string) config('nuki.auth_users.redirect_after_logout', '/nuki/login');
    }

    /**
     * URL prefix of the auth pages.
     */
    public static function authRoutePrefix(): string
    {
        return (string) config('nuki.auth_users.routes.prefix', 'nuki');
    }

    /**
     * Middleware the auth pages run through.
     *
     * @return list<string>
     */
    public static function authRouteMiddleware(): array
    {
        return self::stringList('nuki.auth_users.routes.middleware', ['web']);
    }

    /**
     * From address of the package's own mail, or null to use the application default.
     */
    public static function mailFromAddress(): ?string
    {
        return self::string('nuki.auth_users.mail.from.address');
    }

    /**
     * From name of the package's own mail, or null to use the application default.
     */
    public static function mailFromName(): ?string
    {
        return self::string('nuki.auth_users.mail.from.name');
    }

    /**
     * Whether a login code is mailed as a second step.
     */
    public static function otpEnabled(): bool
    {
        return config('nuki.auth_users.otp.enabled', true) === true;
    }

    /**
     * Minutes a login code stays valid.
     */
    public static function otpExpiryMinutes(): int
    {
        return (int) config('nuki.auth_users.otp.expiry_minutes', 5);
    }

    /**
     * Number of digits in a login code.
     */
    public static function otpLength(): int
    {
        return (int) config('nuki.auth_users.otp.length', 6);
    }

    /**
     * How many login codes may be requested per window.
     */
    public static function otpMaxPerWindow(): int
    {
        return (int) config('nuki.auth_users.otp.rate_limit.max_per_window', 5);
    }

    /**
     * Length of that window, in minutes.
     */
    public static function otpWindowMinutes(): int
    {
        return (int) config('nuki.auth_users.otp.rate_limit.window_minutes', 15);
    }

    /**
     * Whether a new account has to confirm its email address before signing in.
     */
    public static function emailVerificationEnabled(): bool
    {
        return config('nuki.auth_users.email_verification.enabled', true) === true;
    }

    /**
     * Minutes a verification link stays valid.
     */
    public static function emailVerificationLifetimeMinutes(): int
    {
        return (int) config('nuki.auth_users.email_verification.link_lifetime_minutes', 60);
    }

    /**
     * Whether the forgot password flow is offered.
     */
    public static function passwordResetEnabled(): bool
    {
        return config('nuki.auth_users.password_reset.enabled', true) === true;
    }

    /**
     * Minutes a password reset token stays valid.
     */
    public static function passwordResetLifetimeMinutes(): int
    {
        return (int) config('nuki.auth_users.password_reset.token_lifetime_minutes', 60);
    }

    // ---------------------------------------------------------------- HTTP client and demo

    /**
     * The whole HTTP block, for the client that takes it as one array.
     *
     * @return array<string, mixed>
     */
    public static function http(): array
    {
        return (array) config('nuki.http', []);
    }

    /**
     * Whether the API is faked with the bundled fixtures. Never true in production.
     */
    public static function demoEnabled(): bool
    {
        return config('nuki.demo.enabled') === true;
    }

    // ---------------------------------------------------------------- helpers

    /**
     * A configured string, with "not set" and "empty" treated the same.
     */
    private static function string(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A configured list of strings, or the given fallback when the key holds no list at all.
     * An explicit empty list is a choice and is returned as it is.
     *
     * @param  list<string>  $default
     * @return list<string>
     */
    private static function stringList(string $key, array $default): array
    {
        $value = config($key);

        if (! is_array($value)) {
            return $default;
        }

        return array_values(array_map('strval', $value));
    }
}
