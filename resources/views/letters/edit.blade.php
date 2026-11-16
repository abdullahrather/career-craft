<x-layouts.app :title="$letter->language === 'de' ? __('Cover letter (German)') : __('Cover letter (English)')">
    @if ($letter->job_post_id !== null)
        <a href="{{ route('jobs.show', ['id' => $letter->job_post_id]) }}" class="link text-sm">← {{ __('Job post') }}</a>
    @else
        <a href="{{ route('jobs') }}" class="link text-sm">← {{ __('Jobs') }}</a>
    @endif

    @if (session('error'))
        <p class="alert mt-3" role="alert">{{ session('error') }}</p>
    @endif

    <div class="mt-3">
        <livewire:letters.editor :letter-id="$letter->id" />
    </div>
</x-layouts.app>
