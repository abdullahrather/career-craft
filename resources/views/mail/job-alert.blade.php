<x-mail::message>
# {{ trans_choice('{1} One new job for “:search”|[2,*] :count new jobs for “:search”', $total, ['search' => $search]) }}

@foreach ($jobs as $job)
**[{{ addcslashes($job['title'], '\\[]*_`') }}]({{ str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $job['url']) }})**<br>
{{ implode(' · ', array_filter([$job['company'], $job['location']])) }}
@if ($job['skills'] !== [])
<br>{{ __('Your skills: :skills', ['skills' => implode(', ', $job['skills'])]) }}
@endif

@endforeach
@if ($total > count($jobs))
{{ trans_choice('{1} And one more.|[2,*] And :count more.', $total - count($jobs)) }}

@endif
<x-mail::button :url="$url">
{{ __('See them on CareerCraft') }}
</x-mail::button>

{{ $frequency === 'weekly' ? __('You get this email every week because you saved this search. Change or delete it under Saved searches on Find jobs.') : __('You get this email every day because you saved this search. Change or delete it under Saved searches on Find jobs.') }}
</x-mail::message>
