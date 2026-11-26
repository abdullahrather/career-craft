<x-mail::message>
# {{ $kind }}: {{ $title }}

{{ $company !== '' ? __(':from wrote about your application at :company: “:subject”', ['from' => $from, 'company' => $company, 'subject' => $subject]) : __(':from wrote about your application: “:subject”', ['from' => $from, 'subject' => $subject]) }}

@if ($movedTo !== null)
{{ __('CareerCraft moved the application to :status.', ['status' => $movedTo]) }}

@endif
@if ($interview !== null)
**{{ __('Interview') }}:** {{ $interview->starts_at->translatedFormat('l, j M Y, H:i') }}{{ $interview->location !== '' ? ' · '.$interview->location : '' }}

{{ __('It\'s on the application, and attached to this email for your calendar.') }}

@elseif ($invite)
{{ __('The email gives no fixed time yet. Add the interview on the application once it\'s set.') }}

@endif
<x-mail::button :url="$url">
{{ __('Open the application') }}
</x-mail::button>

{{ __('The AI can misread an email. If it got this one wrong, change the status on the application.') }}
</x-mail::message>
