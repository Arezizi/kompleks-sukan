<?php

use Illuminate\View\ComponentAttributeBag;

/**
 * Camera QR scanner shown inside a Flux modal.
 *
 * Progressive enhancement: uses the native BarcodeDetector API where
 * available and falls back to a friendly message elsewhere. Extracts the
 * `code` / `loan` query parameter from scanned URLs before writing to the
 * Livewire target property.
 *
 * @var ComponentAttributeBag $attributes
 * @var string $name
 * @var string $target
 * @var string|null $action
 */
?>
<flux:modal wire:model="showScanner" name="{{ $name }}" class="w-full max-w-sm">
    <flux:heading>{{ __('Scan QR code') }}</flux:heading>
    <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Point the camera at the student’s QR code.') }}
    </flux:text>

    <div
        x-data="{
            supported: 'BarcodeDetector' in window,
            failed: false,
            stream: null,
            async start() {
                if (! this.supported || this.stream !== null) {
                    return;
                }

                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                    });
                    this.failed = false;
                    this.$refs.video.srcObject = this.stream;
                    await this.$refs.video.play();

                    const detector = new BarcodeDetector({ formats: ['qr_code'] });

                    const tick = async () => {
                        if (this.stream === null || this.$refs.video === undefined) {
                            return;
                        }

                        try {
                            const codes = await detector.detect(this.$refs.video);

                            if (codes.length > 0) {
                                let value = codes[0].rawValue;

                                try {
                                    const url = new URL(value, window.location.origin);
                                    value = url.searchParams.get('code')
                                        ?? url.searchParams.get('loan')
                                        ?? value;
                                } catch (error) {
                                    //
                                }

                                this.stop();
                                $wire.set('{{ $target }}', value);

                                @if (filled($action ?? null))
                                    $wire.call('{{ $action }}');
                                @endif

                                return;
                            }
                        } catch (error) {
                            //
                        }

                        requestAnimationFrame(() => tick());
                    };

                    tick();
                } catch (error) {
                    this.failed = true;
                    this.stop();
                }
            },
            stop() {
                if (this.stream !== null) {
                    this.stream.getTracks().forEach((track) => track.stop());
                    this.stream = null;
                }
            },
        }"
        class="mt-4"
    >
        <div
            x-show="supported && ! failed"
            x-cloak
            class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-900 dark:border-zinc-700"
        >
            <video x-ref="video" playsinline muted class="aspect-square w-full object-cover" x-effect="$wire.showScanner ? start() : stop()"></video>
        </div>

        <p x-show="! supported" x-cloak class="rounded-xl border border-dashed border-zinc-300 p-4 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            {{ __('Camera scanning is not supported in this browser — type or paste the code instead.') }}
        </p>

        <p x-show="failed" x-cloak class="mt-2 text-sm text-red-600 dark:text-red-400">
            {{ __('Could not access the camera. Check permissions and try again.') }}
        </p>
    </div>
</flux:modal>
