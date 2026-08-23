<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Menu')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="clipboard-document-list" :href="route('reservations.index')" :current="request()->routeIs('reservations.*')" wire:navigate>
                        {{ __('My bookings') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.*')" wire:navigate>
                        {{ __('Settings') }}
                    </flux:sidebar.item>

                    @if (auth()->user()->isJskkArea())
                        <flux:sidebar.group :heading="__('Counter')" class="mt-2">
                            <flux:sidebar.item icon="arrow-right-start-on-rectangle" :href="route('counter.dashboard')" :current="request()->routeIs('counter.dashboard')" wire:navigate>
                                {{ __('Counter desk') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="qr-code" :href="route('counter.checkout')" :current="request()->routeIs('counter.checkout')" wire:navigate>
                                {{ __('Check out') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="arrow-uturn-left" :href="route('counter.returns')" :current="request()->routeIs('counter.returns')" wire:navigate>
                                {{ __('Returns') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="hand-raised" :href="route('counter.penalties')" :current="request()->routeIs('counter.penalties')" wire:navigate>
                                {{ __('Penalties') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>

                        <flux:sidebar.group :heading="__('Administration')" class="mt-2">
                            <flux:sidebar.item icon="users" :href="route('admin.roster.index')" :current="request()->routeIs('admin.roster.*')" wire:navigate>
                                {{ __('Roster import') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="cube" :href="route('admin.inventory.index')" :current="request()->routeIs('admin.inventory.*')" wire:navigate>
                                {{ __('Inventory') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="building-office-2" :href="route('admin.facilities.index')" :current="request()->routeIs('admin.facilities.*')" wire:navigate>
                                {{ __('Facility status') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif

                    @if (auth()->user()->canLogIncidents())
                        <flux:sidebar.item icon="exclamation-triangle" :href="route('admin.incidents.index')" :current="request()->routeIs('admin.incidents.*')" wire:navigate>
                            {{ __('Incident log') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="arrow-left" :href="route('home')" wire:navigate>
                    {{ __('Back to Home') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
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
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
