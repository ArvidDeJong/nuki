<?php

declare(strict_types=1);

namespace Darvis\Nuki;

use Darvis\Nuki\Auth\CacheTokenStore;
use Darvis\Nuki\Auth\ConfigApiTokenResolver;
use Darvis\Nuki\Auth\DatabaseApiTokenResolver;
use Darvis\Nuki\Auth\DatabaseTokenStore;
use Darvis\Nuki\Auth\OAuthAuthenticator;
use Darvis\Nuki\Auth\TokenAuthenticator;
use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Console\Commands\NukiOAuthAuthorizeCommand;
use Darvis\Nuki\Console\Commands\NukiUserCreateCommand;
use Darvis\Nuki\Console\Commands\NukiWebhookRegisterCommand;
use Darvis\Nuki\Contracts\ApiTokenResolver;
use Darvis\Nuki\Contracts\Authenticator;
use Darvis\Nuki\Contracts\TokenStore;
use Darvis\Nuki\Exceptions\NukiException;
use Darvis\Nuki\Http\HttpClient;
use Darvis\Nuki\Livewire\AccountsIndex;
use Darvis\Nuki\Livewire\AccountSwitcher;
use Darvis\Nuki\Livewire\ActivityTimeline;
use Darvis\Nuki\Livewire\Auth\ForgotPasswordPage;
use Darvis\Nuki\Livewire\Auth\LoginOtpPage;
use Darvis\Nuki\Livewire\Auth\LoginPage;
use Darvis\Nuki\Livewire\Auth\RegisterPage;
use Darvis\Nuki\Livewire\Auth\ResetPasswordPage;
use Darvis\Nuki\Livewire\Dashboard;
use Darvis\Nuki\Livewire\OAuthConnect;
use Darvis\Nuki\Livewire\ProfilePage;
use Darvis\Nuki\Livewire\SmartlockShow;
use Darvis\Nuki\Livewire\SmartlocksIndex;
use Darvis\Nuki\Livewire\SubUserShow;
use Darvis\Nuki\Livewire\SubUsersIndex;
use Darvis\Nuki\Livewire\WebhooksIndex;
use Darvis\Nuki\Support\DemoFixtures;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class NukiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nuki.php', 'nuki');

        // In demo mode the HTTP layer is faked, but the bearer authenticator
        // still needs *some* token to attach. Stub one when missing so a fresh
        // installer only has to flip `NUKI_DEMO=true` in .env.
        if (config('nuki.demo.enabled') === true && empty(config('nuki.token'))) {
            config(['nuki.token' => 'demo-token']);
        }

        if (config('nuki.auth_users.enabled') === true) {
            AuthConfigRegistrar::register($this->app->make(Repository::class));
        }

        $this->app->singleton(TokenStore::class, function (Application $app): TokenStore {
            $driver = (string) config('nuki.oauth.token_store', 'cache');

            return match ($driver) {
                'database' => new DatabaseTokenStore($app->make(DatabaseManager::class)->connection()),
                'cache' => new CacheTokenStore(
                    $app->make(CacheFactory::class)->store(config('nuki.oauth.cache_store')),
                    (string) config('nuki.oauth.cache_prefix', 'nuki:oauth:'),
                ),
                default => throw new NukiException("Unknown nuki.oauth.token_store driver: {$driver}"),
            };
        });

        $this->app->singleton(ApiTokenResolver::class, function (): ApiTokenResolver {
            $driver = (string) config('nuki.token_resolver', 'database');
            $defaultToken = config('nuki.token');

            return match ($driver) {
                'database' => new DatabaseApiTokenResolver($defaultToken),
                'config' => new ConfigApiTokenResolver($defaultToken),
                default => throw new NukiException("Unknown nuki.token_resolver driver: {$driver}"),
            };
        });

        $this->app->singleton(Authenticator::class, function (Application $app): Authenticator {
            $mode = (string) config('nuki.auth', 'token');

            return match ($mode) {
                'token' => new TokenAuthenticator($app->make(ApiTokenResolver::class)),
                'oauth' => new OAuthAuthenticator(
                    $app->make(TokenStore::class),
                    (array) config('nuki.oauth'),
                ),
                default => throw new NukiException("Unknown nuki.auth mode: {$mode}"),
            };
        });

        $this->app->singleton(HttpClient::class, function (Application $app): HttpClient {
            return new HttpClient(
                baseUrl: (string) config('nuki.base_url'),
                authenticator: $app->make(Authenticator::class),
                httpConfig: (array) config('nuki.http', []),
            );
        });

        $this->app->singleton('nuki', function (Application $app): Nuki {
            return new Nuki(
                http: $app->make(HttpClient::class),
                tokens: $app->make(TokenStore::class),
                oauthConfig: (array) config('nuki.oauth'),
            );
        });

        $this->app->alias('nuki', Nuki::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nuki');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'nuki');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nuki.php' => config_path('nuki.php'),
            ], 'nuki-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'nuki-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/nuki'),
            ], 'nuki-views');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/nuki'),
            ], 'nuki-lang');

            $this->publishes([
                __DIR__.'/Database/Seeders/NukiDemoSeeder.php' => database_path('seeders/NukiDemoSeeder.php'),
            ], 'nuki-seeders');

            $this->commands([
                NukiOAuthAuthorizeCommand::class,
                NukiUserCreateCommand::class,
                NukiWebhookRegisterCommand::class,
            ]);
        }

        if (config('nuki.webhook.enabled') === true) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        }

        if (config('nuki.ui.enabled') === true) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            $this->registerLivewireComponents();
        }

        if (config('nuki.auth_users.enabled') === true) {
            $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');
            $this->registerAuthLivewireComponents();
        }

        if (config('nuki.demo.enabled') === true) {
            DemoFixtures::register();
        }
    }

    private function registerLivewireComponents(): void
    {
        if (! $this->app->bound('livewire')) {
            return;
        }

        Livewire::component('nuki.dashboard', Dashboard::class);
        Livewire::component('nuki.activity-timeline', ActivityTimeline::class);
        Livewire::component('nuki.smartlocks-index', SmartlocksIndex::class);
        Livewire::component('nuki.smartlock-show', SmartlockShow::class);
        Livewire::component('nuki.webhooks-index', WebhooksIndex::class);
        Livewire::component('nuki.oauth-connect', OAuthConnect::class);
        Livewire::component('nuki.accounts-index', AccountsIndex::class);
        Livewire::component('nuki.account-switcher', AccountSwitcher::class);
    }

    private function registerAuthLivewireComponents(): void
    {
        if (! $this->app->bound('livewire')) {
            return;
        }

        Livewire::component('nuki.auth.login', LoginPage::class);
        Livewire::component('nuki.auth.otp', LoginOtpPage::class);
        Livewire::component('nuki.auth.register', RegisterPage::class);
        Livewire::component('nuki.auth.forgot-password', ForgotPasswordPage::class);
        Livewire::component('nuki.auth.reset-password', ResetPasswordPage::class);
        Livewire::component('nuki.profile', ProfilePage::class);
        Livewire::component('nuki.sub-users-index', SubUsersIndex::class);
        Livewire::component('nuki.sub-user-show', SubUserShow::class);
    }
}
