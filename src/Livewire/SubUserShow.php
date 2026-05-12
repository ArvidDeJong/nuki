<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Carbon\CarbonImmutable;
use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Models\NukiAccount;
use Darvis\Nuki\Models\NukiUser;
use Darvis\Nuki\Models\NukiUserSmartlockAccess;
use Darvis\Nuki\Support\WeekdayBitmask;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SubUserShow extends Component
{
    public int $id;

    public bool $showModal = false;

    public ?int $editingAccessId = null;

    public ?int $accountId = null;

    public ?int $smartlockId = null;

    public bool $canLock = true;

    public bool $canUnlock = true;

    public bool $canViewLogs = false;

    public bool $canManageAuths = false;

    public ?string $allowedFrom = null;

    public ?string $allowedUntil = null;

    /** @var array<int, string> */
    public array $weekdays = [];

    public bool $isActive = true;

    public function mount(int $id): void
    {
        $parent = $this->parent();
        if ($parent === null || ! $parent->isMain()) {
            abort(403);
        }

        $sub = $parent->subUsers()->where('id', $id)->first();
        if ($sub === null) {
            abort(404);
        }

        $this->id = $id;
    }

    public function render(): View
    {
        return view('nuki::livewire.sub-user-show')
            ->layout(config('nuki.ui.layout', 'nuki::layouts.app'));
    }

    #[Computed]
    public function sub(): ?NukiUser
    {
        return $this->parent()?->subUsers()->where('id', $this->id)->first();
    }

    #[Computed]
    public function accessRows(): Collection
    {
        $sub = $this->sub;
        if ($sub === null) {
            return collect();
        }

        return $sub->smartlockAccess()->with('account')->orderBy('id')->get();
    }

    #[Computed]
    public function accounts(): Collection
    {
        return NukiAccount::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, array{id: int, name: string}> */
    public function smartlocksForAccount(int $accountId): Collection
    {
        $account = NukiAccount::find($accountId);
        if ($account === null) {
            return collect();
        }

        try {
            return Nuki::as($account->account_key)->smartlocks()->all()
                ->map(fn ($lock) => ['id' => $lock->smartlockId, 'name' => $lock->name]);
        } catch (\Throwable) {
            return collect();
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $accessId): void
    {
        $row = $this->accessRows->firstWhere('id', $accessId);
        if ($row === null) {
            return;
        }

        $this->editingAccessId = $row->id;
        $this->accountId = (int) $row->nuki_account_id;
        $this->smartlockId = (int) $row->smartlock_id;
        $this->canLock = (bool) $row->can_lock;
        $this->canUnlock = (bool) $row->can_unlock;
        $this->canViewLogs = (bool) $row->can_view_logs;
        $this->canManageAuths = (bool) $row->can_manage_auths;
        $this->allowedFrom = $row->allowed_from?->format('Y-m-d\TH:i');
        $this->allowedUntil = $row->allowed_until?->format('Y-m-d\TH:i');
        $this->weekdays = WeekdayBitmask::toDays($row->allowed_weekdays);
        $this->isActive = (bool) $row->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $sub = $this->sub;
        if ($sub === null) {
            return;
        }

        $this->validate([
            'accountId' => 'required|integer|exists:nuki_accounts,id',
            'smartlockId' => 'required|integer|min:1',
            'allowedFrom' => 'nullable|date',
            'allowedUntil' => 'nullable|date|after_or_equal:allowedFrom',
            'weekdays' => 'array',
            'weekdays.*' => 'string|in:ma,di,wo,do,vr,za,zo',
        ]);

        $attributes = [
            'nuki_user_id' => $sub->id,
            'nuki_account_id' => $this->accountId,
            'smartlock_id' => $this->smartlockId,
            'can_lock' => $this->canLock,
            'can_unlock' => $this->canUnlock,
            'can_view_logs' => $this->canViewLogs,
            'can_manage_auths' => $this->canManageAuths,
            'allowed_from' => filled($this->allowedFrom) ? CarbonImmutable::parse($this->allowedFrom) : null,
            'allowed_until' => filled($this->allowedUntil) ? CarbonImmutable::parse($this->allowedUntil) : null,
            'allowed_weekdays' => WeekdayBitmask::fromDays($this->weekdays),
            'is_active' => $this->isActive,
        ];

        if ($this->editingAccessId === null) {
            NukiUserSmartlockAccess::updateOrCreate(
                [
                    'nuki_user_id' => $sub->id,
                    'nuki_account_id' => $this->accountId,
                    'smartlock_id' => $this->smartlockId,
                ],
                $attributes,
            );
            session()->flash('status', __('nuki::nuki.flash.access_added'));
        } else {
            NukiUserSmartlockAccess::where('id', $this->editingAccessId)->update($attributes);
            session()->flash('status', __('nuki::nuki.flash.access_updated'));
        }

        $this->showModal = false;
        $this->resetForm();
        unset($this->accessRows);
    }

    public function delete(int $accessId): void
    {
        $sub = $this->sub;
        if ($sub === null) {
            return;
        }

        $sub->smartlockAccess()->where('id', $accessId)->delete();
        session()->flash('status', __('nuki::nuki.flash.access_deleted'));
        unset($this->accessRows);
    }

    private function parent(): ?NukiUser
    {
        /** @var NukiUser|null $user */
        $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();

        return $user;
    }

    private function resetForm(): void
    {
        $this->editingAccessId = null;
        $this->accountId = $this->accounts->first()?->id;
        $this->smartlockId = null;
        $this->canLock = true;
        $this->canUnlock = true;
        $this->canViewLogs = false;
        $this->canManageAuths = false;
        $this->allowedFrom = null;
        $this->allowedUntil = null;
        $this->weekdays = [];
        $this->isActive = true;
        $this->resetErrorBag();
    }
}
