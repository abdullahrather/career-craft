<x-layouts.app :title="__('Choose a new password')" narrow>
    <div class="card">
        <h1 class="text-2xl font-semibold">{{ __('Choose a new password') }}</h1>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 flex flex-col gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-field name="email" type="email" :label="__('Email')" :value="$request->string('email')->toString()" autocomplete="email" required />
            <x-field name="password" type="password" :label="__('New password (at least 8 characters)')" autocomplete="new-password" required autofocus />
            <x-field name="password_confirmation" type="password" :label="__('Password again')" autocomplete="new-password" required />
            <button type="submit" class="button button-primary">{{ __('Save new password') }}</button>
        </form>
    </div>
</x-layouts.app>
