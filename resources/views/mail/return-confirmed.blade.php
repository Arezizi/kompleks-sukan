@component('mail::message')
# {{ __('Return Confirmed') }}

{{ __('Hello :name,', ['name' => $loan->user->name]) }}

{{ __('The sports counter has confirmed the return of your borrowed equipment. Student card deposit returned.') }}

@component('mail::button', ['url' => route('dashboard')])
{{ __('Dashboard') }}
@endcomponent

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
@endcomponent
