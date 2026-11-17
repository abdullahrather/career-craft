<section class="card">
    <h2 class="card-title">{{ __('Reminders') }}</h2>
    <form wire:submit="save" class="mt-4 flex flex-col gap-4">
        <x-wire.input model="followUpDays" type="number" :label="__('Days before a follow-up')" :hint="__('An application in Applied with no news for this many days, and no open task, is suggested for a follow-up.')" />
        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" class="mt-1" wire:model="reminderEmails">
            <span>{{ __('Email me each morning when tasks are due, an interview is coming up, or an application needs a follow-up') }}</span>
        </label>
        @if (session('reminders-saved'))
            <p class="notice" role="status">{{ session('reminders-saved') }}</p>
        @endif
        <div><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save') }}</button></div>
    </form>
</section>
