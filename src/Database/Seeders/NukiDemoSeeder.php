<?php

declare(strict_types=1);

namespace Darvis\Nuki\Database\Seeders;

use Darvis\Nuki\Models\NukiAccount;
use Illuminate\Database\Seeder;

/**
 * Seeds the `nuki_accounts` table with a handful of realistic demo accounts.
 *
 * Run with:
 *   php artisan db:seed --class="Darvis\\Nuki\\Database\\Seeders\\NukiDemoSeeder"
 *
 * Combine with `NUKI_DEMO=true` in `.env` to also fake the NUKI Web API
 * responses, giving you a fully populated UI without a real account.
 */
class NukiDemoSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'account_key' => 'default',
                'name' => 'Darvis Hoofdkantoor',
                'description' => 'Hoofdvestiging Julianadorp — demo account',
                'api_token' => 'demo-token-do-not-use',
                'is_active' => true,
            ],
            [
                'account_key' => 'werkplaats',
                'name' => 'Werkplaats Julianadorp',
                'description' => 'Magazijn en garage — demo account',
                'api_token' => 'demo-token-do-not-use',
                'is_active' => true,
            ],
            [
                'account_key' => 'vakantiehuis',
                'name' => 'Vakantiewoning Texel',
                'description' => 'Verhuur via Airbnb — demo account',
                'api_token' => 'demo-token-do-not-use',
                'is_active' => true,
            ],
            [
                'account_key' => 'klant-bakkerij',
                'name' => 'Bakkerij De Jong',
                'description' => 'Klantaccount — demo',
                'api_token' => 'demo-token-do-not-use',
                'is_active' => false,
            ],
        ];

        foreach ($accounts as $row) {
            NukiAccount::updateOrCreate(
                ['account_key' => $row['account_key']],
                $row,
            );
        }

        $this->command?->info(sprintf('Seeded %d demo NUKI accounts.', count($accounts)));
        $this->command?->line('Tip: set <comment>NUKI_DEMO=true</comment> in your .env to also fake the NUKI Web API.');
    }
}
