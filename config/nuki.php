<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NUKI
|--------------------------------------------------------------------------
|
| Keys are sorted alphabetically within every group, so a setting is found
| by name instead of by history. Read them through
| Darvis\Nuki\Support\NukiConfig, never with config() directly.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication method
    |--------------------------------------------------------------------------
    |
    | Supported: "token" (personal API token, single account) and "oauth"
    | (OAuth 2.0 Authorization Code flow, multi-account).
    |
    */

    'auth' => env('NUKI_AUTH', 'token'),

    /*
    |--------------------------------------------------------------------------
    | Package user authentication
    |--------------------------------------------------------------------------
    |
    | Optional self-contained user auth for the package itself. When enabled,
    | the package registers a `darvis-nuki` Laravel auth guard (eloquent
    | provider, NukiUser model), publishes a Livewire login / OTP / register /
    | password-reset UI and gates all bundled UI routes behind the guard.
    |
    | Main users (parent_id NULL) can manage sub-users and assign them to
    | individual smartlocks with permissions and validity windows. No data is
    | synced to the NUKI Web API — these permissions are local to your app.
    |
    */

    'auth_users' => [
        'email_verification' => [
            'enabled' => true,
            'link_lifetime_minutes' => 60,
        ],
        'enabled' => env('NUKI_AUTH_USERS_ENABLED', false),
        'mail' => [
            'from' => [
                'address' => env('NUKI_AUTH_USERS_MAIL_FROM_ADDRESS'),
                'name' => env('NUKI_AUTH_USERS_MAIL_FROM_NAME'),
            ],
        ],
        'otp' => [
            'enabled' => true,
            'expiry_minutes' => 5,
            'length' => 6,
            'rate_limit' => [
                'max_per_window' => 5,
                'window_minutes' => 15,
            ],
        ],
        'password_reset' => [
            'enabled' => true,
            'token_lifetime_minutes' => 60,
        ],
        'redirect_after_login' => '/nuki',
        'redirect_after_logout' => '/nuki/login',
        'register_enabled' => true,
        'routes' => [
            'middleware' => ['web'],
            'prefix' => 'nuki',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | NUKI Web API base URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the NUKI Web API. You generally never need to change this.
    |
    */

    'base_url' => env('NUKI_BASE_URL', 'https://api.nuki.io'),

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | When enabled, all outbound calls to the NUKI Web API are intercepted by
    | `Darvis\Nuki\Support\DemoFixtures` and answered with canned, plausible
    | data — smartlocks, activity logs, authorizations, webhook subscriptions.
    | Combined with the `Darvis\Nuki\Database\Seeders\NukiDemoSeeder`, this
    | gives you a fully populated UI suitable for screenshots and recording
    | walk-through videos without touching a real NUKI account.
    |
    | NEVER enable this in production.
    |
    */

    'demo' => [
        'enabled' => env('NUKI_DEMO', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    |
    | Tuning for the underlying Laravel HTTP client. Retries kick in on
    | connection errors and HTTP 429 (rate limit) responses with exponential
    | backoff (`retry_sleep` milliseconds, doubled per attempt).
    |
    */

    'http' => [
        'retries' => 3,
        'retry_sleep' => 200,
        'timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0
    |--------------------------------------------------------------------------
    |
    | Register your application on https://developer.nuki.io/ to obtain a
    | client id, client secret and configure the redirect URL.
    |
    | `scopes` controls the OAuth scopes requested at authorization time.
    | `token_store` selects where issued tokens are persisted:
    |   - "cache":    Laravel cache (default, fine for single-account)
    |   - "database": dedicated `nuki_oauth_tokens` table (multi-account)
    |
    */

    'oauth' => [
        'authorize_url' => env('NUKI_OAUTH_AUTHORIZE_URL', 'https://api.nuki.io/oauth/authorize'),
        'cache_prefix' => 'nuki:oauth:',
        'cache_store' => env('NUKI_TOKEN_CACHE_STORE'),
        'client_id' => env('NUKI_OAUTH_CLIENT_ID'),
        'client_secret' => env('NUKI_OAUTH_CLIENT_SECRET'),
        'redirect_url' => env('NUKI_OAUTH_REDIRECT_URL'),
        'scopes' => [
            'account',
            'notification',
            'smartlock',
            'smartlock.action',
            'smartlock.auth',
            'smartlock.readOnly',
        ],
        'token_store' => env('NUKI_TOKEN_STORE', 'cache'),
        'token_url' => env('NUKI_OAUTH_TOKEN_URL', 'https://api.nuki.io/oauth/token'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API token (Bearer)
    |--------------------------------------------------------------------------
    |
    | When `auth` = "token", the package needs to know how to look up a
    | per-account API token. Two resolvers ship with the package:
    |
    |   - "config":   single-account mode, uses the `token` value below for
    |                 the `default` account key only.
    |   - "database": multi-account mode, looks up the token on the
    |                 `nuki_accounts` table by `account_key`. Falls back to
    |                 the `token` value below for the `default` account if
    |                 no matching row exists.
    |
    | Generate personal API tokens in the NUKI Web account
    | (https://web.nuki.io/) of each customer under "API".
    |
    */

    'token' => env('NUKI_API_TOKEN'),

    'token_resolver' => env('NUKI_TOKEN_RESOLVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | UI (Livewire pages)
    |--------------------------------------------------------------------------
    |
    | When `enabled` is true, the package registers Livewire-powered pages
    | under `prefix` for managing smartlocks, viewing logs, managing
    | authorizations, webhook subscriptions and the OAuth connection.
    |
    | `layout` controls which Blade layout the pages extend; the package
    | provides `nuki::layouts.app` but you can swap in your own. The view
    | should yield `slot` (Livewire default) and call `@fluxScripts`.
    |
    */

    'ui' => [
        'auth_panel' => [
            'enabled' => env('NUKI_UI_AUTH_PANEL', true),
        ],
        'brand' => env('NUKI_UI_BRAND', 'NUKI'),
        'default_locale' => env('NUKI_DEFAULT_LOCALE', 'en'),
        'enabled' => env('NUKI_UI_ENABLED', true),
        'footer' => [
            // Array of ['label' => 'Privacy', 'url' => '/privacy']. Empty = none.
            'links' => [],
        ],
        'layout' => 'nuki::layouts.app',
        'locales' => [
            'de' => 'Deutsch',
            'en' => 'English',
            'es' => 'Español',
            'nl' => 'Nederlands',
        ],
        'logo' => [
            // Path/URL to SVG/PNG, swapped via dark mode. Null = icon fallback.
            'dark' => env('NUKI_UI_LOGO_DARK'),
            'light' => env('NUKI_UI_LOGO_LIGHT'),
        ],
        'middleware' => ['web'],
        'prefix' => env('NUKI_UI_PREFIX', 'nuki'),
        'tagline' => env('NUKI_UI_TAGLINE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | NUKI Web URL
    |--------------------------------------------------------------------------
    |
    | Public web portal where customers manage their account and generate
    | personal API tokens. Used in the UI to deep-link the user to the right
    | place when adding a new customer.
    |
    */

    'web_url' => env('NUKI_WEB_URL', 'https://web.nuki.io'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | When `enabled` is true, the package registers a POST route at `route`
    | that accepts NUKI callbacks, verifies the HMAC signature against
    | `secret`, deduplicates by event id for `dedup_ttl` seconds and
    | dispatches the `NukiWebhookReceived` event.
    |
    | Without a `secret` every request is rejected. Set `verify_signature`
    | to false to accept unsigned requests, for example behind a proxy that
    | already authenticates the caller.
    |
    */

    'webhook' => [
        'dedup_ttl' => 600,
        'enabled' => env('NUKI_WEBHOOK_ENABLED', false),
        'middleware' => ['api'],
        'route' => env('NUKI_WEBHOOK_ROUTE', '/nuki/webhook'),
        'secret' => env('NUKI_WEBHOOK_SECRET'),
        'signature_header' => env('NUKI_WEBHOOK_SIGNATURE_HEADER', 'X-Nuki-Signature'),
        'verify_signature' => env('NUKI_WEBHOOK_VERIFY_SIGNATURE', true),
    ],

];
