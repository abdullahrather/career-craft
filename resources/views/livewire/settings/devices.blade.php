<section class="card">
    <h2 class="card-title">{{ __('Signed-in devices') }}</h2>

    <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
        @foreach ($devices as $device)
            <li class="flex items-center justify-between gap-4 py-3">
                <div>
                    <p class="font-medium">{{ $device['device'] }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $device['ip'] }} · {{ __('Last active :time', ['time' => $device['lastActive']->diffForHumans()]) }}
                    </p>
                </div>
                @if ($device['current'])
                    <span class="badge">{{ __('This device') }}</span>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($signedOut)
        <p class="mt-4 text-sm text-green-700 dark:text-green-400" role="status">{{ __('Every other device has been signed out.') }}</p>
    @endif

    @if ($hasPassword && count($devices) > 1)
        <form wire:submit="signOutOthers" class="mt-6 flex flex-wrap items-end gap-3">
            <label class="field grow">
                <span>{{ __('Password') }}</span>
                <input type="password" wire:model="password" autocomplete="current-password" required>
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <button type="submit" class="button">{{ __('Sign out all other devices') }}</button>
        </form>
    @endif
</section>
