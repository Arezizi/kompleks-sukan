<?php

use App\Models\Loan;
use App\Services\LoanService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Returns')] class extends Component {
    /**
     * Search term matched against student name and matric number.
     */
    public string $search = '';

    /**
     * Loan QR token pasted or scanned by staff (prefilled from ?loan=).
     */
    #[Url(as: 'loan')]
    public string $loanToken = '';

    /**
     * Loan matched by its QR token.
     */
    public ?int $foundLoanId = null;

    /**
     * Loan currently being returned (condition note entry).
     */
    public ?int $returningId = null;

    public string $conditionNote = '';

    public bool $cardReturned = true;

    /**
     * Whether the QR scanner modal is open.
     */
    public bool $showScanner = false;

    public function mount(): void
    {
        if ($this->loanToken !== '') {
            $this->findLoan();
        }
    }

    /**
     * Pull up a single active loan by its return QR token.
     */
    public function findLoan(): void
    {
        $this->validate(['loanToken' => ['required', 'string']], [], ['loanToken' => __('QR code')]);

        $this->reset('foundLoanId', 'returningId');

        /** @var Loan|null $loan */
        $loan = Loan::query()
            ->active()
            ->where('token', trim($this->loanToken))
            ->first();

        if ($loan === null) {
            $this->addError('loanToken', __('No active loan matches this code.'));

            return;
        }

        $this->foundLoanId = $loan->id;
        $this->returningId = $loan->id;
        $this->reset('search');
    }

    /**
     * Clear the token match and go back to the full list.
     */
    public function clearFoundLoan(): void
    {
        $this->reset('foundLoanId', 'loanToken', 'returningId');
    }

    /**
     * Receive the loan and close it out.
     */
    public function confirmReturn(int $loanId): void
    {
        $loan = Loan::query()->active()->findOrFail($loanId);

        $service = app(LoanService::class);
        $returned = $service->returnLoan(
            $loan,
            auth()->user(),
            filled($this->conditionNote) ? $this->conditionNote : null,
            $this->cardReturned,
        );

        $this->reset('returningId', 'conditionNote', 'cardReturned', 'foundLoanId', 'loanToken');

        session()->flash('status', $returned->penalties()->exists()
            ? __('Returned late — a penalty has been recorded.')
            : __('Return recorded. Deposit card handed back.'));
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Returns') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Check gear back in. Late returns automatically start the penalty clock.') }}
        </flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" data-test="flash-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($errors->has('return'))
        <flux:callout variant="danger" icon="exclamation-triangle" data-test="return-error">
            <flux:callout.text>{{ $errors->first('return') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($errors->has('loanToken'))
        <p class="text-sm text-red-600 dark:text-red-400" data-test="token-error">{{ $errors->first('loanToken') }}</p>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end" data-test="returns-search">
        <form wire:submit="findLoan" class="flex flex-wrap items-end gap-2">
            <div class="w-full sm:w-72">
                <flux:input wire:model="loanToken" :label="__('Scan loan QR')" :placeholder="__('Paste or scan the loan’s QR code')" data-test="loan-token-input" />
            </div>
            <flux:button variant="subtle" type="submit" class="mb-[3px]" data-test="find-loan">{{ __('Find') }}</flux:button>
            <flux:button variant="subtle" icon="qr-code" class="mb-[3px]" wire:click="$set('showScanner', true)" data-test="open-scanner">
                {{ __('Scan') }}
            </flux:button>
        </form>

        <div class="w-full sm:w-auto sm:ml-auto">
            <flux:input wire:model.live.debounce.300ms="search" type="search"
                :label="__('Or search by student')" :placeholder="__('Name or matric number…')"
            />
        </div>
    </div>

    @if ($foundLoanId !== null)
        <div class="flex items-center justify-between gap-3 rounded-xl border border-indigo-500/30 bg-indigo-500/5 px-3 py-2" data-test="token-match">
            <flux:text size="sm">{{ __('Showing the loan matched by QR code.') }}</flux:text>
            <flux:button size="sm" variant="ghost" wire:click="clearFoundLoan" data-test="clear-token-match">
                {{ __('Show all') }}
            </flux:button>
        </div>
    @endif

    <div class="space-y-4" data-test="active-loans-list">
        @php
            $loans = Loan::query()
                ->active()
                ->with(['items.equipmentType', 'user'])
                ->when($this->foundLoanId !== null, fn ($query) => $query->whereKey($this->foundLoanId))
                ->when($this->foundLoanId === null && trim($this->search) !== '', fn ($query) => $query->whereHas('user', fn ($q) => $q
                    ->where('name', 'like', '%'.trim($this->search).'%')
                    ->orWhereRaw('lower(matric_no) like ?', ['%'.str(trim($this->search))->lower().'%'])))
                ->orderBy('due_at')
                ->limit(50)
                ->get();
        @endphp

    @forelse ($loans as $loan)
        <div class="rounded-2xl border p-4 {{ $loan->due_at->isPast() ? 'border-red-500/40 bg-red-500/5' : 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900' }}"
            wire:key="return-{{ $loan->id }}" data-test="active-loan-row"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="truncate text-sm font-semibold">
                        {{ $loan->user->name }}
                        <span class="font-normal text-zinc-500">({{ $loan->user->matric_no ?? '—' }})</span>
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $loan->items->map(fn ($item) => $item->quantity.'× '.$item->equipmentType->name)->implode(', ') }}
                    </p>
                    <p class="text-xs {{ $loan->due_at->isPast() ? 'font-semibold text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                        {{ __('Checked out :at · due by :time', ['at' => $loan->checked_out_at->format('d M, g:i A'), 'time' => $loan->due_at->format('g:i A')]) }}
                        @if ($loan->due_at->isPast())
                            — {{ __('OVERDUE') }}
                        @endif
                    </p>
                </div>

                @if ($returningId === $loan->id)
                    <div class="w-full space-y-2 sm:w-auto sm:min-w-80" data-test="return-form">
                        <flux:input wire:model="conditionNote" :placeholder="__('Condition note (optional)')" />
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="cardReturned" class="accent-indigo-600" />
                            {{ __('Deposit card handed back') }}
                        </label>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="primary" wire:click="confirmReturn({{ $loan->id }})" data-test="confirm-return">
                                {{ __('Confirm return') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="$set('returningId', null)">{{ __('Cancel') }}</flux:button>
                        </div>
                    </div>
                @else
                    <flux:button size="sm" variant="subtle" wire:click="$set('returningId', {{ $loan->id }})" data-test="open-return-form">
                        {{ __('Return…') }}
                    </flux:button>
                @endif
            </div>
        </div>
    @empty
        <p class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400" data-test="no-active-loans">
            {{ __('No active loans match.') }}
        </p>
    @endforelse

    <x-qr-scanner name="returns-scanner" target="loanToken" action="findLoan" />
</section>
