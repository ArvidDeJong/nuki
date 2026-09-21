<?php

declare(strict_types=1);

namespace Darvis\Nuki\Console\Commands;

use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class NukiUserCreateCommand extends Command
{
    protected $signature = 'nuki:user-create
                            {--email= : Email of the new main user}
                            {--name= : Display name}
                            {--password= : Plain password (will be hashed)}
                            {--no-2fa : Disable email OTP for this user}
                            {--account=* : Key of an existing account to attach the user to as owner (repeatable)}';

    protected $description = 'Create a main NukiUser account for the package auth guard.';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask((string) __('nuki::nuki.console.user_create.email')));
        $name = (string) ($this->option('name') ?: $this->ask((string) __('nuki::nuki.console.user_create.name')));
        $password = (string) ($this->option('password') ?: $this->secret((string) __('nuki::nuki.console.user_create.password')));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => 'required|email|max:255|unique:nuki_users,email',
                'name' => 'required|string|max:120',
                'password' => 'required|string|min:8|max:255',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // Before the user exists: an unknown key must leave nothing behind.
        $keys = array_values(array_unique(array_filter(array_map('strval', (array) $this->option('account')))));
        $accounts = NukiAccount::query()->whereIn('account_key', $keys)->get();
        $unknown = array_values(array_diff($keys, $accounts->pluck('account_key')->all()));

        if ($unknown !== []) {
            $this->error((string) __('nuki::nuki.console.user_create.unknown_account', [
                'keys' => implode(', ', $unknown),
            ]));

            return self::FAILURE;
        }

        $user = NukiUser::create([
            'parent_id' => null,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'two_factor_enabled' => ! $this->option('no-2fa'),
            'is_active' => true,
        ]);

        if ($accounts->isNotEmpty()) {
            $user->accounts()->syncWithoutDetaching(
                $accounts->mapWithKeys(fn (NukiAccount $account): array => [
                    $account->id => ['role' => NukiAccount::ROLE_OWNER],
                ])->all(),
            );
        }

        $this->info((string) __('nuki::nuki.console.user_create.created', [
            'email' => $user->email,
            'id' => $user->id,
        ]));

        return self::SUCCESS;
    }
}
