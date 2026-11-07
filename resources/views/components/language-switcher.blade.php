{{-- One button per language. The current one is marked, so screen readers announce it. --}}
<form method="POST" action="{{ route('locale') }}" class="flex gap-0.5 rounded-full border border-stone-300 p-0.5 dark:border-stone-700">
    @csrf
    @foreach (config('app.locales') as $code => $name)
        <button
            type="submit"
            name="locale"
            value="{{ $code }}"
            lang="{{ $code }}"
            title="{{ $name }}"
            @if ($code === app()->getLocale()) aria-current="true" @endif
            class="rounded-full px-2.5 py-1 text-xs font-bold uppercase text-stone-600 hover:text-stone-900 aria-[current]:bg-brand-700 aria-[current]:text-white dark:text-stone-400 dark:hover:text-stone-100 dark:aria-[current]:bg-brand-500 dark:aria-[current]:text-brand-950"
        ><span class="sr-only">{{ $name }}</span><span aria-hidden="true">{{ $code }}</span></button>
    @endforeach
</form>
