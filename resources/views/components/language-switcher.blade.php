{{-- One button per language. The current one is marked, so screen readers announce it. --}}
<form method="POST" action="{{ route('locale') }}" class="flex gap-1">
    @csrf
    @foreach (config('app.locales') as $code => $name)
        <button
            type="submit"
            name="locale"
            value="{{ $code }}"
            lang="{{ $code }}"
            @if ($code === app()->getLocale()) aria-current="true" @endif
            class="rounded-md px-2 py-1 text-sm font-medium aria-[current]:bg-zinc-900 aria-[current]:text-white hover:bg-zinc-100 aria-[current]:hover:bg-zinc-900"
        >{{ $name }}</button>
    @endforeach
</form>
