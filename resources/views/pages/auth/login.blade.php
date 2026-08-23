<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Use your matric number or email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Matric Number / Email -->
            <flux:input
                name="email"
                :label="__('Matric no / email')"
                :value="old('email')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="PMS123456"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-y-1 text-sm text-center text-zinc-600 dark:text-zinc-400">
            <p>{{ __('First time here? Check your email for the activation link from the sports complex office.') }}</p>
            <flux:link :href="route('activate')" wire:navigate>{{ __('Activate your account') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
