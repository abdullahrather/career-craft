{{-- Open at first while nothing is followed; then it stays as the person leaves it. --}}
<details class="card" wire:ignore.self @if ($boards->isEmpty()) open @endif>
    <summary class="cursor-pointer font-semibold">{{ __('Companies you follow') }} ({{ $boards->count() }})</summary>
    <p class="muted mt-2 text-sm">{{ __('Follow a company that has its job board on Greenhouse, Lever or Ashby. Its jobs join the list, and are fetched again every day.') }}</p>

    <form wire:submit="follow" class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <x-wire.input model="link" type="url" :label="__('Job board link')" :hint="__('Such as https://jobs.lever.co/company, or a link to one of its jobs')" />
        <button type="submit" class="button" wire:loading.attr="disabled" wire:target="follow">
            <span wire:loading.remove wire:target="follow">{{ __('Follow') }}</span>
            <span wire:loading wire:target="follow">{{ __('Fetching jobs…') }}</span>
        </button>
    </form>

    @if ($boards->isNotEmpty())
        <ul class="mt-4 flex flex-col gap-2">
            @foreach ($boards as $board)
                <li wire:key="board-{{ $board->id }}" class="flex items-center justify-between gap-3">
                    <span class="min-w-0 wrap-break-word">
                        <a href="{{ route('discover', ['company' => $board->name]) }}" class="link" wire:navigate>{{ $board->name }}</a>
                        <span class="muted text-sm">· {{ \App\JobSources\CompanyBoards::NAMES[$board->board] ?? $board->board }}</span>
                    </span>
                    <button type="button" wire:click="unfollow({{ $board->id }})" class="button button-small shrink-0" aria-label="{{ __('Unfollow :name', ['name' => $board->name]) }}">{{ __('Unfollow') }}</button>
                </li>
            @endforeach
        </ul>
    @endif
</details>
