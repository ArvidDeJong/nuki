<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Darvis\Nuki\Models\NukiUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SubUsersIndex extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $twoFactorEnabled = true;

    public bool $isActive = true;

    public function mount(): void
    {
        if (! $this->parent()?->isMain()) {
            abort(403);
        }
    }

    public function render(): View
    {
        return view('nuki::livewire.sub-users-index')
            ->layout(config('nuki.ui.layout', 'nuki::layouts.app'));
    }

    #[Computed]
    public function subUsers(): Collection
    {
        $parent = $this->parent();
        if ($parent === null) {
            return collect();
        }

        return $parent->subUsers()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $sub = $this->parent()?->subUsers()->where('id', $id)->first();
        if ($sub === null) {
            return;
        }

        $this->editingId = $id;
        $this->name = $sub->name;
        $this->email = $sub->email;
        $this->password = '';
        $this->twoFactorEnabled = (bool) $sub->two_factor_enabled;
        $this->isActive = (bool) $sub->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $parent = $this->parent();
        if ($parent === null) {
            return;
        }

        $rules = [
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('nuki_users', 'email')->ignore($this->editingId)],
            'password' => $this->editingId === null ? 'required|string|min:8|max:255' : 'nullable|string|min:8|max:255',
            'twoFactorEnabled' => 'boolean',
            'isActive' => 'boolean',
        ];

        $this->validate($rules);

        $attributes = [
            'parent_id' => $parent->id,
            'name' => $this->name,
            'email' => $this->email,
            'two_factor_enabled' => $this->twoFactorEnabled,
            'is_active' => $this->isActive,
        ];

        if (filled($this->password)) {
            $attributes['password'] = $this->password;
        }

        if ($this->editingId === null) {
            NukiUser::create($attributes);
            session()->flash('status', __('nuki::nuki.flash.sub_user_created', ['email' => $this->email]));
        } else {
            $sub = $parent->subUsers()->where('id', $this->editingId)->first();
            if ($sub !== null) {
                $sub->fill($attributes)->save();
                session()->flash('status', __('nuki::nuki.flash.sub_user_updated', ['email' => $this->email]));
            }
        }

        $this->showModal = false;
        $this->resetForm();
        unset($this->subUsers);
    }

    public function delete(int $id): void
    {
        $sub = $this->parent()?->subUsers()->where('id', $id)->first();
        if ($sub === null) {
            return;
        }

        $email = $sub->email;
        $sub->delete();
        session()->flash('status', __('nuki::nuki.flash.sub_user_deleted', ['email' => $email]));
        unset($this->subUsers);
    }

    public function toggleActive(int $id): void
    {
        $sub = $this->parent()?->subUsers()->where('id', $id)->first();
        if ($sub === null) {
            return;
        }

        $sub->is_active = ! $sub->is_active;
        $sub->save();
        unset($this->subUsers);
    }

    private function parent(): ?NukiUser
    {
        /** @var NukiUser|null $user */
        $user = Auth::guard(AuthConfigRegistrar::GUARD)->user();

        return $user;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->twoFactorEnabled = true;
        $this->isActive = true;
        $this->resetErrorBag();
    }
}
