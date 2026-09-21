<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Concerns\AuthorizesMainUser;
use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Support\NukiConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class OAuthConnect extends Component
{
    use AuthorizesMainUser;
    use UsesNukiAccount;

    public ?string $authorizeUrl = null;

    public ?string $error = null;

    public function render(): View
    {
        return view('nuki::livewire.oauth-connect')
            ->layout(NukiConfig::uiLayout());
    }

    #[Computed]
    public function authMode(): string
    {
        return NukiConfig::authMethod();
    }

    #[Computed]
    public function tokenInfo(): ?array
    {
        try {
            $token = Nuki::oauth()->token($this->accountKey);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return null;
        }

        if ($token === null) {
            return null;
        }

        return [
            'expires_at' => $token->expiresAt->toDateTimeString(),
            'is_expired' => $token->isExpired(),
            'scope' => $token->scope,
        ];
    }

    public function generateAuthorizationUrl(): void
    {
        try {
            $this->authorizeUrl = Nuki::oauth()->authorizationUrl(state: Str::random(32));
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function disconnect(): void
    {
        try {
            Nuki::oauth()->revoke($this->accountKey);
            session()->flash('status', __('nuki::nuki.flash.oauth_token_deleted', ['key' => $this->accountKey]));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->tokenInfo);
    }

    #[On('nuki-account-changed')]
    public function handleAccountChanged(string $accountKey): void
    {
        $this->accountKey = $this->authorizedAccountKey($accountKey);
        $this->error = null;
        $this->authorizeUrl = null;
        unset($this->tokenInfo);
    }
}
