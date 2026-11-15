<x-layouts.app :title="$post->title !== '' ? $post->title : __('Job post')">
    <a href="{{ route('jobs') }}" class="link text-sm">← {{ __('Jobs') }}</a>

    <div class="mt-3">
        <livewire:jobs.show :id="$post->id" />
    </div>
</x-layouts.app>
