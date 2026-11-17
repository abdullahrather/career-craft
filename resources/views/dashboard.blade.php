@php($completeness = auth()->user()->careerProfile()->completeness())

<x-layouts.app :title="__('Dashboard')">
    <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('Welcome, :name', ['name' => auth()->user()?->name]) }}</h1>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        @php($follow = new \App\Applications\FollowThrough(auth()->user(), now()))
        @php($dueTasks = $follow->dueTasks())
        @php($interviews = $follow->interviews(7))
        @php($quiet = $follow->quiet())
        <section class="card lg:col-span-2" aria-labelledby="coming-up">
            <h2 id="coming-up" class="card-title">{{ __('Your applications') }}</h2>
            @if ($dueTasks->isEmpty() && $interviews->isEmpty() && $quiet->isEmpty())
                <p class="muted mt-2">{{ auth()->user()->applications()->exists() ? __('Nothing due, no interviews this week, and nothing waiting for a follow-up.') : __('Track the jobs you go for, from saved to an offer, with the CV and letter you sent.') }}</p>
            @else
                <div class="mt-4 grid gap-6 md:grid-cols-3">
                    @if ($dueTasks->isNotEmpty())
                        <div>
                            <h3 class="font-semibold">{{ __('Tasks due') }}</h3>
                            <ul class="mt-2 flex flex-col gap-2 text-sm">
                                @foreach ($dueTasks as $task)
                                    <li class="wrap-break-word">
                                        {{ $task->title }}
                                        <span class="block text-xs">
                                            <a href="{{ route('applications.show', ['id' => $task->application_id]) }}" class="link">{{ $task->application->title }}</a>
                                            @if ($task->due_on->lt(today()))
                                                · <span class="font-semibold text-red-700 dark:text-red-400">{{ __('Overdue since :date', ['date' => $task->due_on->translatedFormat('j M Y')]) }}</span>
                                            @else
                                                · <span class="muted">{{ __('Due today') }}</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($interviews->isNotEmpty())
                        <div>
                            <h3 class="font-semibold">{{ __('Interviews this week') }}</h3>
                            <ul class="mt-2 flex flex-col gap-2 text-sm">
                                @foreach ($interviews as $interview)
                                    <li class="wrap-break-word">
                                        {{ $interview->starts_at->translatedFormat('l, j M, H:i') }}
                                        <span class="block text-xs"><a href="{{ route('applications.show', ['id' => $interview->application_id]) }}" class="link">{{ $interview->application->title }}</a>@if ($interview->application->company !== '') <span class="muted">· {{ $interview->application->company }}</span>@endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($quiet->isNotEmpty())
                        <div>
                            <h3 class="font-semibold">{{ __('Time to follow up') }}</h3>
                            <ul class="mt-2 flex flex-col gap-2 text-sm">
                                @foreach ($quiet as $item)
                                    <li class="wrap-break-word">
                                        <a href="{{ route('applications.show', ['id' => $item['application']->id]) }}" class="link">{{ $item['application']->title }}</a>@if ($item['application']->company !== '') <span class="muted">· {{ $item['application']->company }}</span>@endif
                                        <span class="muted block text-xs">{{ trans_choice('{1} No news for a day|[2,*] No news for :count days', (int) $item['since']->copy()->startOfDay()->diffInDays(today())) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
            <a href="{{ route('applications') }}" class="button mt-5">{{ __('Open the board') }}</a>
        </section>

        <section class="card">
            <h2 class="card-title">{{ __('Your career profile') }}</h2>
            <p class="muted mt-2">
                @if ($completeness['score'] === 100)
                    {{ __('Complete. Every CV you make starts from a strong profile.') }}
                @else
                    {{ __('Your profile is :score% complete.', ['score' => $completeness['score']]) }}
                @endif
            </p>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800" role="progressbar" aria-valuenow="{{ $completeness['score'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Profile strength') }}">
                <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ $completeness['score'] }}%"></div>
            </div>
            @if ($completeness['next'] !== [])
                <p class="mt-4 text-sm">{{ $completeness['next'][0]['text'] }}</p>
            @endif
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('profile') }}" class="button button-primary">{{ __('Open your profile') }}</a>
                @if ($completeness['score'] < 100)
                    <a href="{{ route('profile.import') }}" class="button">{{ __('Import a CV or LinkedIn') }}</a>
                @endif
            </div>
        </section>

        <section class="card">
            <h2 class="card-title">{{ __('Your CVs') }}</h2>
            <p class="muted mt-2">{{ __('Turn your profile into a CV in English or German, with a template that suits the role.') }}</p>
            <a href="{{ route('resumes') }}" class="button mt-5">{{ __('Go to your CVs') }}</a>
        </section>

        <section class="card">
            <h2 class="card-title">{{ __('Keep your account safe') }}</h2>
            <p class="muted mt-2">{{ __('Turn on two-factor login, so a stolen password alone is not enough to get in.') }}</p>
            <a href="{{ route('settings') }}" class="button mt-5">{{ __('Go to settings') }}</a>
        </section>
    </div>
</x-layouts.app>
