<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('nuki.ui.brand', 'NUKI') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
    <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <flux:brand href="{{ route('nuki.dashboard') }}" name="{{ config('nuki.ui.brand', 'NUKI') }}" />

        <flux:navbar class="ms-8 hidden md:flex">
            <flux:navbar.item icon="squares-2x2" href="{{ route('nuki.dashboard') }}"
                              :current="request()->routeIs('nuki.dashboard')">
                {{ __('nuki::nuki.nav.dashboard') }}
            </flux:navbar.item>
            <flux:navbar.item icon="lock-closed" href="{{ route('nuki.smartlocks.index') }}"
                              :current="request()->routeIs('nuki.smartlocks.*')">
                {{ __('nuki::nuki.nav.smartlocks') }}
            </flux:navbar.item>
            <flux:navbar.item icon="clock" href="{{ route('nuki.activity.index') }}"
                              :current="request()->routeIs('nuki.activity.*')">
                {{ __('nuki::nuki.nav.activity') }}
            </flux:navbar.item>
            <flux:navbar.item icon="users" href="{{ route('nuki.accounts.index') }}"
                              :current="request()->routeIs('nuki.accounts.*')">
                {{ __('nuki::nuki.nav.accounts') }}
            </flux:navbar.item>
            @php($navUser = config('nuki.auth_users.enabled') ? auth('darvis-nuki')->user() : null)
            @if ($navUser && $navUser->isMain())
                <flux:navbar.item icon="user-group" href="{{ route('nuki.sub-users.index') }}"
                                  :current="request()->routeIs('nuki.sub-users.*')">
                    {{ __('nuki::nuki.nav.sub_users') }}
                </flux:navbar.item>
            @endif
            <flux:navbar.item icon="bell" href="{{ route('nuki.webhooks.index') }}"
                              :current="request()->routeIs('nuki.webhooks.*')">
                {{ __('nuki::nuki.nav.webhooks') }}
            </flux:navbar.item>
            <flux:navbar.item icon="key" href="{{ route('nuki.oauth.connect') }}"
                              :current="request()->routeIs('nuki.oauth.*')">
                {{ __('nuki::nuki.nav.connection') }}
            </flux:navbar.item>
        </flux:navbar>

        <flux:spacer />

        <livewire:nuki.account-switcher />

        @php($authUser = config('nuki.auth_users.enabled') ? auth('darvis-nuki')->user() : null)

        <flux:dropdown position="bottom" align="end">
            <flux:button variant="ghost" icon="ellipsis-horizontal" icon:variant="outline" />
            <flux:menu>
                @if ($authUser)
                    <flux:menu.item icon="user" href="{{ route('nuki.profile') }}">
                        {{ $authUser->name }}
                    </flux:menu.item>
                    @if ($authUser->isMain())
                        <flux:menu.item icon="users" href="{{ route('nuki.sub-users.index') }}">
                            {{ __('nuki::nuki.nav.sub_users') }}
                        </flux:menu.item>
                    @endif
                    <flux:menu.separator />
                @endif
                <flux:menu.item icon="arrow-top-right-on-square"
                                href="{{ config('nuki.web_url') }}" target="_blank">
                    {{ __('nuki::nuki.nav.nuki_web') }}
                </flux:menu.item>
                <flux:menu.item icon="document-text"
                                href="https://developer.nuki.io" target="_blank">
                    {{ __('nuki::nuki.nav.api_docs') }}
                </flux:menu.item>
                @if ($authUser)
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('nuki.auth.logout') }}">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-left-on-rectangle">
                            {{ __('nuki::nuki.nav.logout') }}
                        </flux:menu.item>
                    </form>
                @endif
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main container class="py-8">
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" class="mb-6">
                {{ session('status') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-6">
                {{ session('error') }}
            </flux:callout>
        @endif

        {{ $slot }}
    </flux:main>

    @fluxScripts
</body>
</html>
