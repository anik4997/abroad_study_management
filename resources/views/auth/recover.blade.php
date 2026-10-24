<x-layouts.app title="Reset password">
    <div class="mx-auto max-w-md">
        <div class="card p-6 sm:p-8">
            <h1 class="text-2xl font-semibold text-brand-900">Reset password</h1>
            <p class="mt-1 text-sm text-slate-500">Use the recovery code you saved when you created your account.</p>
            <form method="POST" action="{{ route('recover') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="username">Username</label>
                    <input id="username" name="username" value="{{ old('username') }}" class="input" required autocomplete="username">
                    @error('username')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="recovery_code">Recovery code</label>
                    <input id="recovery_code" name="recovery_code" class="input font-mono uppercase" required placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                    @error('recovery_code')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password">New password</label>
                    <input id="password" type="password" name="password" class="input" required minlength="8" autocomplete="new-password">
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
                </div>
                <button class="btn-primary w-full">Reset password</button>
            </form>
        </div>
    </div>
</x-layouts.app>
