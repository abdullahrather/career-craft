<x-layouts.app :title="__('Career profile')">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('Career profile') }}</h1>
        <p class="muted mt-2">{{ __('Everything you have done, in one place. Every CV and cover letter you make starts from here, so the more complete it is, the less you type later.') }}</p>
    </header>

    <div class="mt-8 grid items-start gap-6 xl:grid-cols-[1fr_18rem]">
        <div class="flex flex-col gap-6">
            <livewire:profile.personal-details />
            <livewire:profile.experiences />
            <livewire:profile.educations />
            <livewire:profile.skills />
            <livewire:profile.languages />
            <livewire:profile.projects />
            <livewire:profile.certifications />
            <livewire:profile.links />
        </div>

        <aside class="flex flex-col gap-6 xl:sticky xl:top-6">
            <livewire:profile.completeness />
            <nav class="card" aria-label="{{ __('Profile sections') }}">
                <ul class="space-y-1 text-sm">
                    @foreach ([
                        'personal' => __('About you'),
                        'experience' => __('Work experience'),
                        'education' => __('Education'),
                        'skills' => __('Skills'),
                        'languages' => __('Languages'),
                        'projects' => __('Projects'),
                        'certifications' => __('Certificates'),
                        'links' => __('Links'),
                    ] as $anchor => $label)
                        <li><a href="#{{ $anchor }}" class="nav-link py-1.5">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </aside>
    </div>
</x-layouts.app>
