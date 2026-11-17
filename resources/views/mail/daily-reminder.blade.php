<x-mail::message>
# {{ __('Your applications today') }}

@if ($interviews !== [])
## {{ __('Interviews today and tomorrow') }}

@foreach ($interviews as $interview)
**{{ \Illuminate\Support\Carbon::parse($interview['starts_at'])->translatedFormat('l, j M, H:i') }}** · [{{ addcslashes($interview['title'], '\\[]*_`') }}]({{ $interview['url'] }}){{ $interview['company'] !== '' ? ' · '.$interview['company'] : '' }}
@if ($interview['location'] !== '')
<br>{{ $interview['location'] }}
@endif

@endforeach
@endif
@if ($tasks !== [])
## {{ __('Tasks due') }}

@foreach ($tasks as $task)
- {{ $task['title'] }} · [{{ addcslashes($task['application'], '\\[]*_`') }}]({{ $task['url'] }}) · {{ $task['overdue'] ? __('Overdue since :date', ['date' => \Illuminate\Support\Carbon::parse($task['due_on'])->translatedFormat('j M Y')]) : __('Due today') }}
@endforeach

@endif
@if ($quiet !== [])
## {{ __('Time to follow up') }}

@foreach ($quiet as $item)
- [{{ addcslashes($item['title'], '\\[]*_`') }}]({{ $item['url'] }}){{ $item['company'] !== '' ? ' · '.$item['company'] : '' }} · {{ trans_choice('{1} No news for a day|[2,*] No news for :count days', $item['days']) }}
@endforeach

@endif
<x-mail::button :url="route('dashboard')">
{{ __('Open CareerCraft') }}
</x-mail::button>

{{ __('You can turn this email off under Reminders in your settings.') }}
</x-mail::message>
