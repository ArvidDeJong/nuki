<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Carbon\CarbonImmutable;
use Darvis\Nuki\Concerns\AuthorizesSmartlockAccess;
use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\DTOs\SmartLock;
use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Support\NukiConfig;
use Darvis\Nuki\Support\WeekdayBitmask;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SmartlockShow extends Component
{
    use AuthorizesSmartlockAccess;
    use UsesNukiAccount;

    /**
     * Locked: set from the route in mount(), never by the browser. Every permission check below
     * is about this id, so a writable id would make them all meaningless.
     */
    #[Locked]
    public int $smartlockId;

    public string $tab = 'logs';

    public ?string $error = null;

    public bool $showAuthModal = false;

    public bool $showRenameModal = false;

    #[Validate('required|string|max:64')]
    public string $renameValue = '';

    public ?string $editingAuthId = null;

    #[Validate('required|string|max:64')]
    public string $authName = '';

    #[Validate('nullable|integer|digits_between:6,8')]
    public ?int $authCode = null;

    #[Validate('required|integer')]
    public int $authType = 13;

    #[Validate('nullable|date')]
    public ?string $authAllowedFromDate = null;

    #[Validate('nullable|date|after_or_equal:authAllowedFromDate')]
    public ?string $authAllowedUntilDate = null;

    /** @var array<int, string> */
    public array $authWeekDays = [];

    public function mount(int $smartlockId): void
    {
        $this->smartlockId = $smartlockId;
        $this->mountUsesNukiAccount();

        $this->assertCanSeeSmartlock();

        // Start on a tab the user may read; a tab without permission stays empty in the view.
        if (! $this->canPerform('view_logs') && $this->canPerform('manage_auths')) {
            $this->tab = 'auths';
        }
    }

    /**
     * Plain access to the lock itself, whatever the permissions on it. Asked again wherever data
     * is loaded, because the account can change under a mounted page.
     */
    private function assertCanSeeSmartlock(): void
    {
        $allowed = $this->userAccessibleSmartlockIds($this->accountKey);

        if ($allowed !== null && ! in_array($this->smartlockId, $allowed, true)) {
            abort(403);
        }
    }

    public function canPerform(string $permission): bool
    {
        return $this->userCanAccessSmartlock($this->accountKey, $this->smartlockId, $permission);
    }

    public function render(): View
    {
        return view('nuki::livewire.smartlock-show')
            ->layout(NukiConfig::uiLayout());
    }

    #[Computed]
    public function smartlock(): ?SmartLock
    {
        // Outside the try: abort() throws, and the catch below would swallow it.
        $this->assertCanSeeSmartlock();

        try {
            return Nuki::as($this->accountKey)->smartlocks()->find($this->smartlockId);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return null;
        }
    }

    #[Computed]
    public function logs(): Collection
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'view_logs');

        try {
            return Nuki::as($this->accountKey)->logs()->forSmartlock($this->smartlockId, ['limit' => 50]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }
    }

    #[Computed]
    public function auths(): Collection
    {
        // Keypad codes are in here, so reading the list takes the same permission as changing it.
        $this->assertCan($this->accountKey, $this->smartlockId, 'manage_auths');

        try {
            return Nuki::as($this->accountKey)->auths()->forSmartlock($this->smartlockId);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['logs', 'auths'], true) ? $tab : 'logs';
    }

    public function lock(): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'lock');

        try {
            Nuki::as($this->accountKey)->smartlocks()->lock($this->smartlockId);
            session()->flash('status', __('nuki::nuki.flash.lock_sent'));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->smartlock);
    }

    public function unlock(): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'unlock');

        try {
            Nuki::as($this->accountKey)->smartlocks()->unlock($this->smartlockId);
            session()->flash('status', __('nuki::nuki.flash.unlock_sent'));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->smartlock);
    }

    public function lockAndGo(): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'unlock');

        try {
            Nuki::as($this->accountKey)->smartlocks()->lockAndGo($this->smartlockId);
            session()->flash('status', __('nuki::nuki.flash.lock_and_go_sent'));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->smartlock);
    }

    public function sync(): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'lock');

        try {
            Nuki::as($this->accountKey)->smartlocks()->sync($this->smartlockId);
            session()->flash('status', __('nuki::nuki.flash.sync_requested'));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->smartlock);
    }

    public function openRename(): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'manage_auths');
        $this->renameValue = $this->smartlock->name ?? '';
        $this->resetErrorBag('renameValue');
        $this->showRenameModal = true;
    }

    public function saveRename(): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'manage_auths');
        $this->validateOnly('renameValue');

        try {
            Nuki::as($this->accountKey)
                ->smartlocks()
                ->update($this->smartlockId, ['name' => $this->renameValue]);
            session()->flash('status', __('nuki::nuki.flash.name_updated'));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->showRenameModal = false;
        unset($this->smartlock);
    }

    /**
     * Snel-selectie voor de week-grid in de auth-modal.
     */
    public function toggleWeekDay(string $day): void
    {
        if (! array_key_exists($day, WeekdayBitmask::BITS)) {
            return;
        }

        $key = array_search($day, $this->authWeekDays, true);

        if ($key === false) {
            $this->authWeekDays[] = $day;
        } else {
            unset($this->authWeekDays[$key]);
            $this->authWeekDays = array_values($this->authWeekDays);
        }
    }

    public function setWeekDayPreset(string $preset): void
    {
        $this->authWeekDays = match ($preset) {
            'all' => ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'],
            'weekdays' => ['ma', 'di', 'wo', 'do', 'vr'],
            'weekend' => ['za', 'zo'],
            'none' => [],
            default => $this->authWeekDays,
        };
    }

    public function openCreateAuth(): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'manage_auths');
        $this->resetAuthForm();
        $this->showAuthModal = true;
    }

    public function openEditAuth(string $authId): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'manage_auths');
        $this->resetAuthForm();
        $auth = $this->auths->firstWhere('id', $authId);

        if ($auth === null) {
            return;
        }

        $this->editingAuthId = $authId;
        $this->authName = $auth->name;
        $this->authType = $auth->type;
        $this->authCode = $auth->code;
        $this->authAllowedFromDate = $auth->allowedFromDate?->format('Y-m-d\TH:i');
        $this->authAllowedUntilDate = $auth->allowedUntilDate?->format('Y-m-d\TH:i');
        $this->authWeekDays = WeekdayBitmask::toDays($auth->allowedWeekDays);
        $this->showAuthModal = true;
    }

    public function saveAuth(): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'manage_auths');
        $this->validate();

        $payload = array_filter([
            'name' => $this->authName,
            'type' => $this->authType,
            'code' => $this->authCode,
        ], fn ($v) => $v !== null && $v !== '');

        if (filled($this->authAllowedFromDate)) {
            $payload['allowedFromDate'] = CarbonImmutable::parse($this->authAllowedFromDate)->toIso8601String();
        }

        if (filled($this->authAllowedUntilDate)) {
            $payload['allowedUntilDate'] = CarbonImmutable::parse($this->authAllowedUntilDate)->toIso8601String();
        }

        $bitmask = WeekdayBitmask::fromDays($this->authWeekDays);
        if ($bitmask !== null) {
            $payload['allowedWeekDays'] = $bitmask;
        }

        try {
            if ($this->editingAuthId === null) {
                Nuki::as($this->accountKey)->auths()->create($this->smartlockId, $payload);
                session()->flash('status', __('nuki::nuki.flash.auth_created'));
            } else {
                Nuki::as($this->accountKey)->auths()->update($this->smartlockId, $this->editingAuthId, $payload);
                session()->flash('status', __('nuki::nuki.flash.auth_updated'));
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->showAuthModal = false;
        $this->resetAuthForm();
        unset($this->auths);
    }

    public function deleteAuth(string $authId): void
    {
        $this->assertCan($this->accountKey, $this->smartlockId, 'manage_auths');

        try {
            Nuki::as($this->accountKey)->auths()->delete($this->smartlockId, $authId);
            session()->flash('status', __('nuki::nuki.flash.auth_deleted'));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->auths);
    }

    private function resetAuthForm(): void
    {
        $this->editingAuthId = null;
        $this->authName = '';
        $this->authCode = null;
        $this->authType = 13;
        $this->authAllowedFromDate = null;
        $this->authAllowedUntilDate = null;
        $this->authWeekDays = [];
        $this->resetErrorBag();
    }

    #[On('nuki-account-changed')]
    public function handleAccountChanged(string $accountKey): void
    {
        $this->accountKey = $this->authorizedAccountKey($accountKey);
        $this->error = null;
        unset($this->smartlock, $this->logs, $this->auths);
    }
}
