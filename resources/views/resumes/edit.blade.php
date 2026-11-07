<x-layouts.app :title="$resume->name">
    <a href="{{ route('resumes') }}" class="link text-sm">← {{ __('CVs') }}</a>

    <div class="mt-3">
        <livewire:resumes.editor :resume-id="$resume->id" />
    </div>
</x-layouts.app>
