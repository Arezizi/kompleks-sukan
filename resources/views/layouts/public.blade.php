<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="h-1 w-full bg-gradient-to-r from-indigo-600 via-sky-500 to-emerald-500" aria-hidden="true"></div>

        <flux:header sticky container class="z-20 border-b border-zinc-200/80 bg-white/85 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/85">
            <x-app-logo href="{{ route('home') }}" wire:navigate />

            <nav class="ml-4 hidden items-center gap-1 md:flex">
                <flux:button variant="ghost" size="sm" href="{{ route('home') }}#status-board">
                    {{ __('Status board') }}
                </flux:button>

                <flux:button variant="ghost" size="sm" :href="route('gear.index')" wire:navigate>
                    {{ __('Gear') }}
                </flux:button>

                <flux:button variant="ghost" size="sm" :href="route('rules.index')" wire:navigate>
                    {{ __('Rules') }}
                </flux:button>
            </nav>

            <flux:spacer />

            @guest
                <flux:button variant="primary" href="{{ route('login') }}" wire:navigate data-test="login-link">
                    {{ __('Log in') }}
                </flux:button>
            @else
                <flux:dropdown position="bottom" align="end">
                    <flux:profile
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevron-down"
                        data-test="navbar-user-menu"
                    />

                    <flux:menu>
                        <flux:menu.radio.group>
                            <flux:menu.item :href="route('dashboard')" icon="home" wire:navigate>
                                {{ __('Dashboard') }}
                            </flux:menu.item>

                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                {{ __('Settings') }}
                            </flux:menu.item>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                                data-test="logout-button"
                            >
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @endguest
        </flux:header>

        <x-weather-banner />

        <flux:main container class="py-10">
            {{ $slot }}
        </flux:main>

        <footer class="mt-8 border-t border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-4 px-6 py-8 sm:flex-row lg:px-8">
                <div class="flex items-center gap-2.5">
                    <span class="grid size-7 place-items-center rounded-md bg-accent-content text-accent-foreground">
                        <x-app-logo-icon class="size-4 fill-current" />
                    </span>
                    <span class="text-sm font-semibold">{{ config('app.name', 'Laravel') }}</span>
                    <span class="text-sm text-zinc-400 dark:text-zinc-500">— {{ __('Sports Complex & Equipment Hub') }}</span>
                </div>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __(':year :app. All rights reserved.', ['year' => date('Y'), 'app' => config('app.name', 'Laravel')]) }}
                </p>
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
