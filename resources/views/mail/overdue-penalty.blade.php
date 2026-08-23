@component('mail::message')
# {{ __('Overdue Notice & Penalty') }}

{{ __('Hello :name,', ['name' => $loan->user->name]) }}

{{ __('Your borrowed equipment was due back by :due, but has not been returned. A 14-day borrowing ban has been placed on your account until :ends.', [
    'due' => $loan->due_at->format('d M, g:i A'),
    'ends' => $penalty->ends_at->format('d M Y'),
]) }}

{{ __('Please return all items to the counter immediately.') }}

@component('mail::button', ['url' => route('dashboard')])
{{ __('View Status') }}
@endcomponent

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
@endcomponent
