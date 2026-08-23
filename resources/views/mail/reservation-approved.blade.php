@component('mail::message')
# {{ __('Reservation Approved') }}

{{ __('Hello :name,', ['name' => $reservation->user->name]) }}

{{ __('Your request for equipment has been approved by the JSKK counter staff. Collect it before your pickup deadline using your student ID card and pickup code:') }}

**{{ $reservation->token }}**

@component('mail::button', ['url' => route('reservations.index')])
{{ __('View Bookings') }}
@endcomponent

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
@endcomponent
