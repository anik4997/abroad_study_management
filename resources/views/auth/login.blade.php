<x-layouts.app title="Log in">
    <div class="mx-auto max-w-md">
        <div class="card p-6 sm:p-8">
            <h1 class="text-2xl font-semibold text-brand-900">Welcome back</h1>
            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="username">Username</label>
                    <input id="username" name="username" value="{{ old('username') }}" class="input" required autocomplete="username" autofocus>
                    @error('username')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600"> Keep me logged in
                </label>
                <button class="btn-primary w-full">Log in</button>
            </form>
            <div class="mt-5 flex justify-between text-sm">
                <a href="{{ route('recover') }}" class="text-slate-500 hover:text-slate-800">Forgot password?</a>
                <a href="{{ route('register') }}" class="font-medium text-brand-700">Create account</a>
            </div>
        </div>
    </div>
</x-layouts.app>
