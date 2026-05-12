<?php

declare(strict_types=1);

namespace Darvis\Nuki\Console\Commands;

use Darvis\Nuki\Facades\Nuki;
use Illuminate\Console\Command;

class NukiWebhookRegisterCommand extends Command
{
    protected $signature = 'nuki:webhook-register
                            {url? : Public callback URL (defaults to APP_URL + config(nuki.webhook.route))}
                            {--account=default : Account key (for OAuth multi-account mode)}
                            {--events=* : Webhook feature flags (default: DEVICE_STATUS, DEVICE_CONFIG, DEVICE_LOGS, ACCOUNT_USER)}';

    protected $description = 'Register a webhook callback URL with the NUKI Web API.';

    public function handle(): int
    {
        $accountKey = (string) $this->option('account');
        $url = (string) ($this->argument('url')
            ?? rtrim((string) config('app.url'), '/').(string) config('nuki.webhook.route'));

        $events = $this->option('events');
        if (empty($events)) {
            $events = ['DEVICE_STATUS', 'DEVICE_CONFIG', 'DEVICE_LOGS', 'ACCOUNT_USER'];
        }

        try {
            $subscription = Nuki::as($accountKey)->webhooks()->subscribe($url, $events);
        } catch (\Throwable $e) {
            $this->error((string) __('nuki::nuki.console.webhook_register.failed', ['error' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->info((string) __('nuki::nuki.console.webhook_register.registered', ['url' => $url]));
        if ($subscription->id !== '') {
            $this->line((string) __('nuki::nuki.console.webhook_register.subscription_id', ['id' => $subscription->id]));
        }

        return self::SUCCESS;
    }
}
