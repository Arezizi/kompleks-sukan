<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Activate your account')] class extends Component {
    public string $token = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Initialize the token from the emailed activation link, if present.
     */
    public function mount(): void
    {
        $this->token = trim((string) request()->query('token', ''));
    }

    /**
     * The pending user matching the supplied activation code, if any.
     */
    #[Computed]
    public function candidate(): ?User
    {
        if (trim($this->token) === '') {
            return null;
        }

        return User::query()
            ->where('setup_token', User::hashSetupToken(trim($this->token)))
            ->first();
    }

    /**
     * Look up the pasted activation code and reveal the password form.
     */
    public function lookup(): void
    {
        $this->validate(['token' => ['required', 'string']]);

        $candidate = $this->candidate;

        if ($candidate === null || ! $candidate->isPendingSetup()) {
            throw ValidationException::withMessages([
                'token' => __('We could not find a pending account for this code.'),
            ]);
        }
    }

    /**
     * Set the student's own password and sign them in.
     */
    public function activate(): void
    {
        $candidate = $this->candidate;

        if ($candidate === null || ! $candidate->isPendingSetup()) {
            throw ValidationException::withMessages([
                'token' => __('This activation code is invalid or has already been used.'),
            ]);
        }

        $this->validate([
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ], [], [
            'password' => __('new password'),
        ]);

        $candidate->forceFill([
            'password' => $this->password,
            'setup_token' => null,
            'setup_completed_at' => now(),
        ])->save();

        Auth::login($candidate);

        session()->regenerate();

        $this->redirectIntended(route('dashboard', absolute: false));
    }
}; ?>

<div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Activate your account')"
            :description="$this->candidate?->isPendingSetup()
                ? __('Hi :name — choose a password to finish setting up your account.', ['name' => $this->candidate->name])
                : __('Paste the activation code from your invitation email, then choose a password.')"
        />

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-circle" heading="{{ __('Something went wrong') }}">
                <flux:callout.text>{{ $errors->first() }}</flux:callout.text>
            </flux:callout>
        @endif

        @if ($this->candidate && $this->candidate->isPendingSetup())
            <form wire:submit="activate" class="flex flex-col gap-6" data-test="activation-form">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/60">
                    <p class="font-semibold">{{ $this->candidate->name }}</p>
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('Matric no') }}: {{ $this->candidate->matric_no }}</p>
                </div>

                <flux:input
                    wire:model="password"
                    :label="__('New password')"
                    type="password"
                    required
                    autofocus
                    autocomplete="new-password"
                    viewable
                    data-test="activation-password"
                />

                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    viewable
                    data-test="activation-confirmation"
                />

                <flux:button variant="primary" type="submit" class="w-full" data-test="activation-submit">
                    {{ __('Set password & log in') }}
                </flux:button>
            </form>
        @elseif ($this->candidate !== null)
            <div class="space-y-4 text-center text-sm text-zinc-600 dark:text-zinc-400" data-test="already-active">
                <p>{{ __('This account is already activated.') }}</p>
                <flux:button variant="primary" :href="route('login')" wire:navigate>
                    {{ __('Go to login') }}
                </flux:button>
            </div>
        @else
            <form wire:submit="lookup" class="flex flex-col gap-6" data-test="token-form">
                <flux:input
                    wire:model="token"
                    :label="__('Activation code')"
                    :placeholder="__('Paste the code from your email')"
                    required
                    data-test="activation-token"
                />

                <flux:button variant="primary" type="submit" class="w-full" data-test="token-submit">
                    {{ __('Continue') }}
                </flux:button>
            </form>
        @endif

        <div class="text-center text-sm">
            <flux:link :href="route('login')" wire:navigate>{{ __('Back to login') }}</flux:link>
        </div>
    </div>
</div>
