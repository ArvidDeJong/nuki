<?php

declare(strict_types=1);

namespace Darvis\Nuki\Livewire;

use Darvis\Nuki\Concerns\UsesNukiAccount;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class WebhooksIndex extends Component
{
    use UsesNukiAccount;

    public ?string $error = null;

    public bool $showSubscribeModal = false;

    #[Validate('required|url')]
    public string $callbackUrl = '';

    /** @var array<int, string> */
    public array $events = ['DEVICE_STATUS', 'DEVICE_LOGS'];

    public function mount(): void
    {
        $this->callbackUrl = rtrim((string) config('app.url'), '/').'/'
            .ltrim((string) config('nuki.webhook.route', '/nuki/webhook'), '/');
    }

    public function render(): View
    {
        return view('nuki::livewire.webhooks-index')
            ->layout(config('nuki.ui.layout', 'nuki::layouts.app'));
    }

    #[Computed]
    public function webhooks(): Collection
    {
        try {
            return Nuki::as($this->accountKey)->webhooks()->all();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return collect();
        }
    }

    public function openSubscribe(): void
    {
        $this->resetErrorBag();
        $this->showSubscribeModal = true;
    }

    public function subscribe(): void
    {
        $this->validate();

        if (empty($this->events)) {
            $this->addError('events', __('nuki::nuki.webhooks.select_event_required'));

            return;
        }

        try {
            Nuki::as($this->accountKey)->webhooks()->subscribe($this->callbackUrl, $this->events);
            session()->flash('status', __('nuki::nuki.flash.webhook_registered'));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->showSubscribeModal = false;
        unset($this->webhooks);
    }

    public function unsubscribe(string $id): void
    {
        try {
            Nuki::as($this->accountKey)->webhooks()->unsubscribe($id);
            session()->flash('status', __('nuki::nuki.flash.webhook_deleted'));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->webhooks);
    }

    #[On('nuki-account-changed')]
    public function handleAccountChanged(string $accountKey): void
    {
        $this->accountKey = $accountKey;
        $this->error = null;
        unset($this->webhooks);
    }
}
