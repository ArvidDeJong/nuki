<?php

declare(strict_types=1);

/**
 * Locks down translation parity: every locale must define exactly the same
 * key set as the EN reference. Catches drift the moment a new key is added
 * to one file but not the others.
 */
function nuki_lang_path(string $locale, string $file): string
{
    return __DIR__.'/../../lang/'.$locale.'/'.$file;
}

function nuki_collect_keys(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $keys = array_merge($keys, nuki_collect_keys($value, $dotted));
        } else {
            $keys[] = $dotted;
        }
    }

    sort($keys);

    return $keys;
}

$locales = ['en', 'nl', 'de', 'es'];
$files = ['nuki.php', 'validation.php', 'mail.php'];

foreach ($files as $file) {
    test("translation key set is identical across locales for {$file}", function () use ($locales, $file) {
        $reference = nuki_collect_keys(require nuki_lang_path('en', $file));

        foreach ($locales as $locale) {
            $keys = nuki_collect_keys(require nuki_lang_path($locale, $file));

            $missing = array_diff($reference, $keys);
            $extra = array_diff($keys, $reference);

            expect($missing)->toBe([], "Locale [{$locale}] is missing keys in {$file}: ".implode(', ', $missing));
            expect($extra)->toBe([], "Locale [{$locale}] has extra keys in {$file}: ".implode(', ', $extra));
        }
    });
}

test('English translations exist and are loaded under the nuki namespace', function () {
    expect(trans('nuki::nuki.nav.dashboard'))->toBe('Dashboard');
    expect(trans('nuki::nuki.smartlocks.lock'))->toBe('Lock');
    expect(trans('nuki::mail.login_otp.title'))->toBe('Login code');
});

test('Dutch translations resolve when locale is switched', function () {
    app()->setLocale('nl');

    expect(trans('nuki::nuki.nav.dashboard'))->toBe('Dashboard');
    expect(trans('nuki::nuki.smartlocks.lock'))->toBe('Vergrendel');
    expect(trans('nuki::mail.login_otp.title'))->toBe('Inlogcode');

    app()->setLocale('en');
});

test('German translations resolve when locale is switched', function () {
    app()->setLocale('de');

    expect(trans('nuki::nuki.smartlocks.lock'))->toBe('Verriegeln');
    expect(trans('nuki::nuki.auth.login'))->toBe('Anmelden');

    app()->setLocale('en');
});

test('Spanish translations resolve when locale is switched', function () {
    app()->setLocale('es');

    expect(trans('nuki::nuki.smartlocks.lock'))->toBe('Bloquear');
    expect(trans('nuki::nuki.auth.login'))->toBe('Iniciar sesión');

    app()->setLocale('en');
});
