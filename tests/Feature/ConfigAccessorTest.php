<?php

declare(strict_types=1);

use Darvis\Nuki\Support\NukiConfig;

/**
 * NukiConfig is the one place that reads the package config. These tests guard the two things
 * that go wrong once a default is written down twice: an accessor that disagrees with the config
 * file, and a caller that reaches past the accessor and keeps its own stale fallback.
 */
function nukiPackageRoot(string $path = ''): string
{
    return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
}

it('returns the values the config file ships', function () {
    $config = require nukiPackageRoot('config/nuki.php');

    expect(NukiConfig::baseUrl())->toBe($config['base_url'])
        ->and(NukiConfig::webUrl())->toBe($config['web_url'])
        ->and(NukiConfig::oauthCachePrefix())->toBe($config['oauth']['cache_prefix'])
        ->and(NukiConfig::oauthTokenStore())->toBe($config['oauth']['token_store'])
        ->and(NukiConfig::webhookRoute())->toBe($config['webhook']['route'])
        ->and(NukiConfig::webhookMiddleware())->toBe($config['webhook']['middleware'])
        ->and(NukiConfig::webhookSignatureHeader())->toBe($config['webhook']['signature_header'])
        ->and(NukiConfig::webhookDedupTtl())->toBe($config['webhook']['dedup_ttl'])
        ->and(NukiConfig::uiBrand())->toBe($config['ui']['brand'])
        ->and(NukiConfig::uiLayout())->toBe($config['ui']['layout'])
        ->and(NukiConfig::uiPrefix())->toBe($config['ui']['prefix'])
        ->and(NukiConfig::uiMiddleware())->toBe($config['ui']['middleware'])
        ->and(NukiConfig::uiLocales())->toBe($config['ui']['locales'])
        ->and(NukiConfig::authRoutePrefix())->toBe($config['auth_users']['routes']['prefix'])
        ->and(NukiConfig::authRouteMiddleware())->toBe($config['auth_users']['routes']['middleware'])
        ->and(NukiConfig::otpLength())->toBe($config['auth_users']['otp']['length'])
        ->and(NukiConfig::otpExpiryMinutes())->toBe($config['auth_users']['otp']['expiry_minutes'])
        ->and(NukiConfig::redirectAfterLogin())->toBe($config['auth_users']['redirect_after_login'])
        ->and(NukiConfig::redirectAfterLogout())->toBe($config['auth_users']['redirect_after_logout']);
});

it('follows a changed setting', function () {
    config([
        'nuki.ui.prefix' => 'locks',
        'nuki.webhook.dedup_ttl' => 30,
        'nuki.auth_users.otp.length' => 8,
    ]);

    expect(NukiConfig::uiPrefix())->toBe('locks')
        ->and(NukiConfig::webhookDedupTtl())->toBe(30)
        ->and(NukiConfig::otpLength())->toBe(8);
});

it('reports an empty api token as none at all', function () {
    config(['nuki.token' => '']);

    expect(NukiConfig::apiToken())->toBeNull();

    config(['nuki.token' => 'abc123']);

    expect(NukiConfig::apiToken())->toBe('abc123');
});

it('falls back to the application locale when no default locale is set', function () {
    config(['app.locale' => 'nl', 'nuki.ui.default_locale' => null]);

    expect(NukiConfig::uiDefaultLocale())->toBe('nl');

    config(['nuki.ui.default_locale' => 'de']);

    expect(NukiConfig::uiDefaultLocale())->toBe('de');
});

it('leaves a middleware list empty when that is what the config says', function () {
    config([
        'nuki.ui.middleware' => [],
        'nuki.webhook.middleware' => [],
        'nuki.auth_users.routes.middleware' => [],
    ]);

    expect(NukiConfig::uiMiddleware())->toBe([])
        ->and(NukiConfig::webhookMiddleware())->toBe([])
        ->and(NukiConfig::authRouteMiddleware())->toBe([]);

    config(['nuki.ui.middleware' => null]);

    expect(NukiConfig::uiMiddleware())->toBe(['web']);
});

it('always offers at least one locale', function () {
    config(['nuki.ui.locales' => []]);

    expect(NukiConfig::uiLocales())->toBe(['en' => 'English']);
});

it('only switches off the signature check when it is set to false', function () {
    expect(NukiConfig::webhookVerifySignature())->toBeTrue();

    config(['nuki.webhook.verify_signature' => 'no']);

    expect(NukiConfig::webhookVerifySignature())->toBeTrue();

    config(['nuki.webhook.verify_signature' => false]);

    expect(NukiConfig::webhookVerifySignature())->toBeFalse();
});

it('is the only place in the package that reads the config', function () {
    $offenders = [];

    foreach (['src', 'resources', 'routes', 'database'] as $directory) {
        $path = nukiPackageRoot($directory);

        if (! is_dir($path)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if (! in_array($file->getExtension(), ['php'], true)) {
                continue;
            }

            $relative = str_replace(nukiPackageRoot('').'/', '', $file->getPathname());

            // NukiConfig is where the reading happens, and the Boost guideline quotes the call
            // it tells you not to write.
            if (str_contains($relative, 'NukiConfig.php') || str_starts_with($relative, 'resources/boost/')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            $contents = preg_replace('#/\*.*?\*/#s', '', $contents) ?? $contents;

            if (preg_match("/config\(['\"]nuki\./", $contents)) {
                $offenders[] = $relative;
            }
        }
    }

    expect($offenders)->toBe([], 'these read the config directly instead of through NukiConfig');
});

it('keeps the config keys in alphabetical order, at every level', function () {
    $walk = function (array $config, string $trail) use (&$walk): void {
        $keys = array_keys($config);

        if ($keys !== array_filter($keys, 'is_string')) {
            return;
        }

        $sorted = $keys;
        sort($sorted);

        expect($keys)->toBe($sorted, "the keys in '{$trail}' are not in alphabetical order");

        foreach ($config as $key => $value) {
            if (is_array($value) && $value !== []) {
                $walk($value, $trail.'.'.$key);
            }
        }
    };

    $walk(require nukiPackageRoot('config/nuki.php'), 'nuki');
});
