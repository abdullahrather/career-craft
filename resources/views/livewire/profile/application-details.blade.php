<section id="application" class="card scroll-mt-6" aria-labelledby="application-title">
    <h2 id="application-title" class="card-title">{{ __('Application details') }}</h2>
    <p class="muted mt-1 text-sm">{{ __('What application forms ask besides your CV. It never appears on a CV; the apply queue shows it ready to copy.') }}</p>

    <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
        <x-wire.input model="salaryExpectation" type="number" :label="__('Salary expectation')" :hint="__('Euros a year, gross.')" />
        <x-wire.input model="earliestStart" type="date" :label="__('Earliest start')" />
        <x-wire.input model="noticePeriod" :label="__('Notice period')" :hint="__('Such as “3 months to the end of a month”.')" />
        <x-wire.input model="workPermit" :label="__('Work permit')" :hint="__('Such as “EU citizen, no permit needed”.')" />

        <div class="flex items-center gap-3 sm:col-span-2">
            <button type="submit" class="button button-primary">{{ __('Save') }}</button>
            @if ($saved)
                <span class="text-sm text-brand-700 dark:text-brand-300" role="status">{{ __('Saved.') }}</span>
            @endif
        </div>
    </form>
</section>
