@component('mail::message')
# {{ __('Equipment Due Soon') }}

{{ __('Hello :name,', ['name' => $loan->user->name]) }}

{{ __('This is a reminder that your active loan is due back at the sports counter by :time.', ['time' => $loan->due_at->format('g:i A')]) }}

@component('mail::button', ['url' => route('dashboard')])
{{ __('Check Dashboard') }}
@endcomponent

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
@endcomponent
