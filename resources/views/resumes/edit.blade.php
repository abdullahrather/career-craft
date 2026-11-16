<x-layouts.app :title="$resume->name">
    <a href="{{ route('resumes') }}" class="link text-sm">← {{ __('CVs') }}</a>

    <div class="mt-3">
        @if ($resume->job_post_id !== null || $resume->changes()->exists())
            <livewire:resumes.tailoring :resume-id="$resume->id" />
        @endif
        <livewire:resumes.editor :resume-id="$resume->id" />
    </div>
</x-layouts.app>
