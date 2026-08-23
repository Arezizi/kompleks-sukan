<x-mail::message>
# {{ __('Welcome to :app', ['app' => config('app.name')]) }}

{{ __('A student account has been created for you by the sports complex office.') }}

{{ __('Matric no') }}: **{{ $user->matric_no }}**

{{ __('Set your own password using the button below to activate your account:') }}

<x-mail::button :url="$activationUrl">
{{ __('Set my password') }}
</x-mail::button>

{{ __('If you have any trouble, visit us at the sports counter in the sports complex.') }}

{{ __('Thanks') }},
{{ config('app.name') }}
</x-mail::message>
