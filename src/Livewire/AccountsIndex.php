<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Facades\Nuki;
use Darvis\Nuki\Models\NukiAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AccountsIndex extends Component
{
    /** @var array<string, array{status: string, message: string}> */
    public array $verification = [];

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $accountKey = '';

    public string $apiToken = '';

    public string $description = '';

    public bool $isActive = true;

    public bool $autoKey = true;

    public function render(): View
    {
        return view('nuki::livewire.accounts-index')
            ->layout(config('nuki.ui.layout', 'nuki::layouts.app'));
    }

    #[Computed]
    public function accounts(): Collection
    {
        return NukiAccount::query()
            ->orderBy('name')
            ->get();
    }

    public function updatedName(string $value): void
    {
        if ($this->autoKey && $this->editingId === null) {
            $this->accountKey = Str::slug($value);
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $account = NukiAccount::find($id);

        if ($account === null) {
            return;
        }

        $this->editingId = $id;
        $this->name = $account->name;
        $this->accountKey = $account->account_key;
        $this->apiToken = '';
        $this->description = (string) $account->description;
        $this->isActive = (bool) $account->is_active;
        $this->autoKey = false;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => 'required|string|max:120',
            'accountKey' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9](?:[a-z0-9-_]*[a-z0-9])?$/',
                Rule::unique('nuki_accounts', 'account_key')->ignore($this->editingId),
            ],
            'apiToken' => $this->editingId === null ? 'required|string|min:8' : 'nullable|string|min:8',
            'description' => 'nullable|string|max:1000',
            'isActive' => 'boolean',
        ];

        $this->validate($rules);

        $attributes = [
            'name' => $this->name,
            'account_key' => $this->accountKey,
            'description' => $this->description ?: null,
            'is_active' => $this->isActive,
        ];

        if (filled($this->apiToken)) {
            $attributes['api_token'] = $this->apiToken;
        }

        if ($this->editingId === null) {
            NukiAccount::create($attributes);
            session()->flash('status', __('nuki::nuki.flash.account_created', ['key' => $this->accountKey]));
        } else {
            NukiAccount::where('id', $this->editingId)->update($attributes);
            session()->flash('status', __('nuki::nuki.flash.account_updated', ['key' => $this->accountKey]));
        }

        $this->showModal = false;
        $this->resetForm();
        unset($this->accounts);
    }

    public function testConnection(int $id): void
    {
        $account = NukiAccount::find($id);

        if ($account === null) {
            return;
        }

        try {
            $info = Nuki::as($account->account_key)->account()->info(fresh: true);
        } catch (\Throwable $e) {
            $this->verification[$account->account_key] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];

            return;
        }

        if ($info === null) {
            $this->verification[$account->account_key] = [
                'status' => 'error',
                'message' => (string) __('nuki::nuki.accounts.no_account_info'),
            ];

            return;
        }

        $this->verification[$account->account_key] = [
            'status' => 'ok',
            'message' => $info->displayName().' ('.($info->email ?? '–').')',
        ];
    }

    public function toggleActive(int $id): void
    {
        $account = NukiAccount::find($id);

        if ($account === null) {
            return;
        }

        $account->is_active = ! $account->is_active;
        $account->save();
        unset($this->accounts);
    }

    public function delete(int $id): void
    {
        $account = NukiAccount::find($id);

        if ($account === null) {
            return;
        }

        if (session('nuki.current_account') === $account->account_key) {
            session()->forget('nuki.current_account');
        }

        $account->delete();
        session()->flash('status', __('nuki::nuki.flash.account_deleted', ['key' => $account->account_key]));
        unset($this->accounts);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->accountKey = '';
        $this->apiToken = '';
        $this->description = '';
        $this->isActive = true;
        $this->autoKey = true;
        $this->resetErrorBag();
    }
}
