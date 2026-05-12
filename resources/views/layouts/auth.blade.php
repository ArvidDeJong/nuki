@php
    $brand = config('nuki.ui.brand', 'NUKI');
    $logoLight = config('nuki.ui.logo.light');
    $logoDark = config('nuki.ui.logo.dark');
    $hasLogo = ! empty($logoLight) || ! empty($logoDark);
    $tagline = config('nuki.ui.tagline') ?: __('nuki::nuki.auth.panel.subheading', ['brand' => $brand]);
    $panelEnabled = config('nuki.ui.auth_panel.enabled', true) === true;
    $footerLinks = config('nuki.ui.footer.links', []);
    $panelFeatures = __('nuki::nuki.auth.panel.features');
    if (! is_array($panelFeatures)) {
        $panelFeatures = [];
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $brand }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-full bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="flex min-h-screen">
        {{-- Linkerkolom: form --}}
        <div class="flex flex-1 flex-col px-6 py-10 sm:px-10 lg:flex-none lg:px-16 xl:px-24 lg:w-[36rem]">
            {{-- Brand-mark --}}
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                    @if ($hasLogo)
                        @if ($logoLight)
                            <img src="{{ $logoLight }}" alt="{{ $brand }}"
                                 class="h-9 w-auto {{ $logoDark ? 'dark:hidden' : '' }}">
                        @endif
                        @if ($logoDark)
                            <img src="{{ $logoDark }}" alt="{{ $brand }}"
                                 class="h-9 w-auto {{ $logoLight ? 'hidden dark:block' : '' }}">
                        @endif
                    @else
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                            <flux:icon.lock-closed class="size-5" />
                        </span>
                        <span class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
                            {{ $brand }}
                        </span>
                    @endif
                </a>
            </div>

            {{-- Form-content --}}
            <div class="mx-auto mt-12 w-full max-w-sm lg:mt-16 lg:w-96 grow">
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
            </div>

            {{-- Footer --}}
            <footer class="mx-auto mt-12 w-full max-w-sm lg:w-96">
                <div class="flex flex-col gap-2 text-xs text-zinc-500 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ date('Y') }} {{ $brand }}</p>
                    @if (! empty($footerLinks))
                        <ul class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            @foreach ($footerLinks as $link)
                                <li>
                                    <a href="{{ $link['url'] ?? '#' }}"
                                       class="hover:text-zinc-900 dark:hover:text-zinc-100">
                                        {{ $link['label'] ?? '' }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </footer>
        </div>

        {{-- Rechterkolom: brand panel --}}
        @if ($panelEnabled)
            <div class="relative hidden flex-1 lg:flex">
                <div class="absolute inset-0 bg-linear-to-br from-zinc-900 via-zinc-800 to-zinc-700"></div>
                <div class="absolute inset-0 opacity-20"
                     style="background-image: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.18) 0, transparent 40%), radial-gradient(circle at 80% 70%, rgba(255,255,255,0.12) 0, transparent 45%);"></div>

                <div class="relative z-10 flex w-full flex-col justify-center px-16 py-20 text-white">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20 backdrop-blur">
                        <flux:icon.shield-check class="size-8 text-white" />
                    </span>

                    <h2 class="mt-8 text-3xl font-semibold tracking-tight">
                        {{ __('nuki::nuki.auth.panel.heading') }}
                    </h2>
                    <p class="mt-3 max-w-md text-base text-zinc-300">
                        {{ $tagline }}
                    </p>

                    @if (! empty($panelFeatures))
                        <ul class="mt-10 space-y-4 text-sm text-zinc-200">
                            @foreach ($panelFeatures as $feature)
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-5 w-5 flex-none items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                                        <flux:icon.check class="size-3.5 text-white" />
                                    </span>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @fluxScripts
</body>
</html>
