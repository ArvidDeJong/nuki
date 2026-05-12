<div>
    <flux:dropdown align="end">
        <flux:button variant="ghost" icon="user-group" icon:trailing="chevron-down" size="sm">
            <span class="hidden sm:inline">{{ $this->currentLabel }}</span>
        </flux:button>

        <flux:menu>
            <flux:menu.item
                icon="{{ $accountKey === 'default' ? 'check' : 'user' }}"
                wire:click="switchTo('default')"
            >
                {{ __('nuki::nuki.account_switcher.default') }}
                <span class="ms-2 font-mono text-xs text-zinc-500">.env</span>
            </flux:menu.item>

            @if ($this->accounts->isNotEmpty())
                <flux:menu.separator />

                @foreach ($this->accounts as $account)
                    @php($nukiName = $this->nukiName($account->account_key))
                    <flux:menu.item
                        icon="{{ $accountKey === $account->account_key ? 'check' : 'user' }}"
                        wire:click="switchTo('{{ $account->account_key }}')"
                    >
                        {{ $account->name }}
                        @if ($nukiName && $nukiName !== $account->name)
                            <span class="ms-2 text-xs text-zinc-500">→ {{ $nukiName }}</span>
                        @endif
                    </flux:menu.item>
                @endforeach
            @endif

            <flux:menu.separator />

            <flux:menu.item icon="users" href="{{ route('nuki.accounts.index') }}">
                {{ __('nuki::nuki.account_switcher.manage') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
