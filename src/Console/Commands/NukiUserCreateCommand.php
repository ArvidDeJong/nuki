<?php

declare(strict_types=1);

namespace Darvis\Nuki\Console\Commands;

use Darvis\Nuki\Models\NukiUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class NukiUserCreateCommand extends Command
{
    protected $signature = 'nuki:user-create
                            {--email= : Email of the new main user}
                            {--name= : Display name}
                            {--password= : Plain password (will be hashed)}
                            {--no-2fa : Disable email OTP for this user}';

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

        $user = NukiUser::create([
            'parent_id' => null,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'two_factor_enabled' => ! $this->option('no-2fa'),
            'is_active' => true,
        ]);

        $this->info((string) __('nuki::nuki.console.user_create.created', [
            'email' => $user->email,
            'id' => $user->id,
        ]));

        return self::SUCCESS;
    }
}
