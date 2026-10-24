<x-layouts.app title="Create account">
    <div class="mx-auto max-w-md">
        <div class="card p-6 sm:p-8">
            <h1 class="text-2xl font-semibold text-brand-900">Create your account</h1>
            <p class="mt-1 text-sm text-slate-500">No email needed — just pick a username and a password.</p>

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf
                {{-- Honeypot: humans never see or fill this. --}}
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div>
                    <label class="label" for="username">Username</label>
                    <input id="username" name="username" value="{{ old('username') }}" class="input" required minlength="3" maxlength="32" autocomplete="username" autofocus pattern="[A-Za-z0-9][A-Za-z0-9_.\-]*">
                    <p class="mt-1 text-xs text-slate-500">Letters, numbers, dot, dash, underscore. Others use it to share lists with you.</p>
                    @error('username')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="name">Display name <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="name" name="name" value="{{ old('name') }}" class="input" maxlength="80" autocomplete="name">
                    @error('name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" type="password" name="password" class="input" required minlength="8" maxlength="72" autocomplete="new-password">
                    <p class="mt-1 text-xs text-slate-500">At least 8 characters with letters and numbers.</p>
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
                </div>
                <button class="btn-primary w-full">Create account</button>
            </form>
            <p class="mt-5 text-center text-sm text-slate-500">Already registered? <a href="{{ route('login') }}" class="font-medium text-brand-700">Log in</a></p>
        </div>
    </div>
</x-layouts.app>
