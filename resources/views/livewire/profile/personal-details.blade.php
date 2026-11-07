<section id="personal" class="card scroll-mt-6" aria-labelledby="personal-title">
    <h2 id="personal-title" class="card-title">{{ __('About you') }}</h2>
    <p class="muted mt-1 text-sm">{{ __('The top of every CV: who you are and how to reach you.') }}</p>

    <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-wire.input model="headline" :label="__('Headline')" :hint="__('The role you do or want, such as “Backend developer, PHP and Laravel”.')" />
        </div>
        <div class="sm:col-span-2">
            <x-wire.textarea model="summary" :label="__('Summary')" :hint="__('Two to four sentences: what you do, what you are good at, and what you want next.')" rows="4" />
        </div>
        <x-wire.input model="email" type="email" :label="__('Email on your CV')" autocomplete="email" />
        <x-wire.input model="phone" type="tel" :label="__('Phone')" autocomplete="tel" />
        <x-wire.input model="city" :label="__('City')" autocomplete="address-level2" />
        <x-wire.select model="country" :label="__('Country')" :options="$countries" :placeholder="__('Choose a country')" />

        <div class="flex items-center gap-3 sm:col-span-2">
            <button type="submit" class="button button-primary">{{ __('Save') }}</button>
            @if ($saved)
                <span class="text-sm text-brand-700 dark:text-brand-300" role="status">{{ __('Saved.') }}</span>
            @endif
        </div>
    </form>
</section>
