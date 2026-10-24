<x-layouts.app title="Your recovery code">
    <div class="mx-auto max-w-lg">
        <div class="card p-6 sm:p-8" x-data="{ copied: false }">
            <h1 class="text-2xl font-semibold text-brand-900">Save your recovery code</h1>
            <p class="mt-2 text-sm text-slate-600">There is no email on your account, so this code is the <strong>only</strong> way to reset a forgotten password. It is shown once — store it somewhere safe (password manager, note, screenshot).</p>
            <div class="mt-5 flex items-center justify-between gap-3 rounded-lg border-2 border-dashed border-brand-200 bg-brand-50 px-4 py-4">
                <code class="font-mono text-lg font-semibold tracking-wider text-brand-900 sm:text-xl">{{ $code }}</code>
                <button type="button" class="btn-ghost" @click="navigator.clipboard.writeText(@js($code)); copied = true">
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
            <p class="mt-3 text-xs text-slate-500">Using the code gives you a new one. You can also create a new code from your account page.</p>
            <a href="{{ route('dashboard') }}" class="btn-primary mt-6 w-full">I saved it — continue</a>
        </div>
    </div>
</x-layouts.app>
