<?php

declare(strict_types=1);

namespace Darvis\Nuki\Console\Commands;

use Darvis\Nuki\Facades\Nuki;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class NukiOAuthAuthorizeCommand extends Command
{
    protected $signature = 'nuki:oauth-authorize
                            {--account=default : Account key to store the resulting token under}
                            {--code= : Authorization code to exchange (skips interactive step)}';

    protected $description = 'Start the NUKI OAuth authorization flow and store the resulting token.';

    public function handle(): int
    {
        $accountKey = (string) $this->option('account');
        $code = $this->option('code');

        if ($code === null) {
            $state = Str::random(32);
            $url = Nuki::oauth()->authorizationUrl(state: $state);

            $this->info((string) __('nuki::nuki.console.oauth_authorize.open_url'));
            $this->line($url);
            $this->newLine();
            $this->comment((string) __('nuki::nuki.console.oauth_authorize.expected_state', ['state' => $state]));
            $this->newLine();

            $code = $this->ask((string) __('nuki::nuki.console.oauth_authorize.paste_code'));
        }

        if (empty($code)) {
            $this->error((string) __('nuki::nuki.console.oauth_authorize.no_code'));

            return self::FAILURE;
        }

        try {
            $token = Nuki::oauth()->exchangeCode((string) $code, $accountKey);
        } catch (\Throwable $e) {
            $this->error((string) __('nuki::nuki.console.oauth_authorize.token_failed', ['error' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->info((string) __('nuki::nuki.console.oauth_authorize.token_stored', ['key' => $accountKey]));
        $this->line((string) __('nuki::nuki.console.oauth_authorize.expires_at', [
            'date' => $token->expiresAt->toIso8601String(),
        ]));

        return self::SUCCESS;
    }
}
