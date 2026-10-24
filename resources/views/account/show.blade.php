<x-layouts.app title="Account">
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-semibold text-brand-900">Account</h1>

        <section class="card p-6">
            <h2 class="font-semibold">Profile</h2>
            <p class="mt-1 text-sm text-slate-500">Username: <span class="font-mono">{{ $user->username }}</span></p>
            <form method="POST" action="{{ route('account.profile') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf @method('PUT')
                <div class="flex-1">
                    <label class="label" for="name">Display name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" class="input" maxlength="80">
                </div>
                <button class="btn-primary">Save</button>
            </form>
        </section>

        <section class="card p-6">
            <h2 class="font-semibold">Change password</h2>
            <form method="POST" action="{{ route('account.password') }}" class="mt-4 grid gap-3 sm:grid-cols-3">
                @csrf @method('PUT')
                <div>
                    <label class="label" for="current_password">Current</label>
                    <input id="current_password" type="password" name="current_password" class="input" required autocomplete="current-password">
                    @error('current_password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password">New</label>
                    <input id="password" type="password" name="password" class="input" required autocomplete="new-password">
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirm</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
                </div>
                <div class="sm:col-span-3"><button class="btn-primary">Change password</button></div>
            </form>
        </section>

        <section class="card p-6">
            <h2 class="font-semibold">Recovery code</h2>
            <p class="mt-1 text-sm text-slate-500">Lost your code? Create a new one. The old code stops working.</p>
            <form method="POST" action="{{ route('account.recovery') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <label class="label" for="recovery_password">Current password</label>
                    <input id="recovery_password" type="password" name="current_password" class="input" required autocomplete="current-password">
                </div>
                <button class="btn-ghost">New recovery code</button>
            </form>
        </section>

        <section class="card border-red-200 p-6">
            <h2 class="font-semibold text-red-700">Delete account</h2>
            <p class="mt-1 text-sm text-slate-500">Deletes your account and all your lists. This cannot be undone.</p>
            <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" onsubmit="return confirm('Delete your account and all lists permanently?')">
                @csrf @method('DELETE')
                <div class="flex-1">
                    <label class="label" for="delete_password">Current password</label>
                    <input id="delete_password" type="password" name="current_password" class="input" required autocomplete="current-password">
                </div>
                <button class="btn-danger">Delete account</button>
            </form>
        </section>
    </div>
</x-layouts.app>
