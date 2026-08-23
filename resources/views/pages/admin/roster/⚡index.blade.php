<?php

use App\Services\RosterImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Roster import')] class extends Component {
    use WithFileUploads;

    /** @var mixed */
    public $file = null;

    /** @var array{created: list<string>, updated: list<string>, failedRows: list<array{row: int, matric_no: string|null, messages: list<string>}>}|null */
    public ?array $report = null;

    /**
     * Run the roster CSV import.
     */
    public function import(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [], [
            'file' => __('CSV file'),
        ]);

        /** @var UploadedFile $file */
        $file = $this->file;

        try {
            $result = app(RosterImporter::class)->import($file->get());

            $this->report = [
                'created' => $result->created,
                'updated' => $result->updated,
                'failedRows' => $result->failedRows,
            ];
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('file', $message);
                }
            }

            return;
        }

        $this->reset('file');
    }

    /**
     * Reset the report for a fresh run.
     */
    public function resetReport(): void
    {
        $this->reset('report', 'file');
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Roster import') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Upload the student roster as a CSV file. New students receive an email with an activation link; known matric numbers are updated.') }}
        </flux:text>
    </div>

    <flux:card>
        <form wire:submit="import" class="flex flex-col gap-5">
            <flux:field>
                <flux:label>{{ __('CSV file') }}</flux:label>

                <input type="file" wire:model="file" accept=".csv,text/csv"
                    class="block w-full cursor-pointer rounded-lg border border-zinc-300 bg-white text-sm text-zinc-600 shadow-sm transition file:me-3 file:border-0 file:border-e file:border-zinc-200 file:bg-zinc-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold hover:file:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:file:border-zinc-600 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
                    data-test="roster-file" />

                <flux:error name="file" />
                @if ($file instanceof Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Selected') }}: {{ $file->getClientOriginalName() }}</p>
                @endif
            </flux:field>

            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/60">
                <p class="font-semibold">{{ __('Expected columns (first row)') }}</p>
                <code class="text-xs">matric_no, name, email, programme, phone</code>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('matric_no, name and email are required. Maximum :n rows per file.', ['n' => RosterImporter::MAX_ROWS]) }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit" :disabled="! $file" wire:loading.attr="disabled" data-test="roster-import-button">
                    {{ __('Import roster') }}
                </flux:button>
                <span wire:loading class="text-sm text-zinc-500">{{ __('Importing…') }}</span>
            </div>
        </form>
    </flux:card>

    @if ($report !== null)
        <flux:card data-test="roster-report">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4">
                <flux:heading>{{ __('Import report') }}</flux:heading>
                <flux:button size="sm" variant="ghost" wire:click="resetReport">{{ __('Clear') }}</flux:button>
            </div>

            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-test="roster-report-counts">
                <div class="rounded-xl bg-emerald-500/10 p-4 ring-1 ring-inset ring-emerald-500/20">
                    <dd class="text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ count($report['created']) }}</dd>
                    <dt class="mt-1 text-xs font-semibold tracking-wide uppercase">{{ __('Created & invited by email') }}</dt>
                </div>
                <div class="rounded-xl bg-sky-500/10 p-4 ring-1 ring-inset ring-sky-500/20">
                    <dd class="text-3xl font-bold tabular-nums text-sky-600 dark:text-sky-400">{{ count($report['updated']) }}</dd>
                    <dt class="mt-1 text-xs font-semibold tracking-wide uppercase">{{ __('Existing profiles updated') }}</dt>
                </div>
                <div class="rounded-xl bg-red-500/10 p-4 ring-1 ring-inset ring-red-500/20">
                    <dd class="text-3xl font-bold tabular-nums text-red-600 dark:text-red-400">{{ count($report['failedRows']) }}</dd>
                    <dt class="mt-1 text-xs font-semibold tracking-wide uppercase">{{ __('Rows rejected') }}</dt>
                </div>
            </dl>

            @if (filled($report['failedRows']))
                <div class="mt-6 space-y-3" data-test="roster-report-errors">
                    <flux:heading>{{ __('Rejected rows') }}</flux:heading>
                    <ul class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                        @foreach ($report['failedRows'] as $failure)
                            <li class="flex flex-col gap-1 px-4 py-3">
                                <span class="text-sm font-semibold">
                                    {{ __('Row :row — :matric', [
                                        'row' => $failure['row'],
                                        'matric' => $failure['matric_no'] ?? __('missing'),
                                    ]) }}
                                </span>
                                <ul class="list-inside list-disc space-y-0.5 text-sm text-red-600 dark:text-red-400">
                                    @foreach ($failure['messages'] as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="pt-4 text-sm text-emerald-600 dark:text-emerald-400">
                    {{ __('All rows imported cleanly. Activation emails are on their way to the new students.') }}
                </p>
            @endif
        </flux:card>
    @endif
</section>
