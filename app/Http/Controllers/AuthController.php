<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Username + password accounts with no email. A one-time recovery code (shown once,
 * stored hashed) replaces email-based password reset.
 */
class AuthController extends Controller
{
    public function showRegister(Request $request): View
    {
        $request->session()->put('register_form_at', now()->timestamp);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $this->rejectBots($request, 'register_form_at');

        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9][a-z0-9_.-]*$/', Rule::unique('users', 'username')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'username.regex' => 'Use only letters, numbers, dot, dash or underscore.',
        ]);

        $recoveryCode = self::newRecoveryCode();

        $user = User::create([
            'name' => $data['name'] ?? null,
            'username' => $data['username'],
            'password' => $data['password'],
            'recovery_code' => $recoveryCode,
        ]);

        if (User::count() === 1) {
            $user->forceFill(['is_admin' => true])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('register_form_at');

        return redirect()->route('recovery-code.show')->with('recovery_code', $recoveryCode);
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:72'],
        ]);

        $credentials['username'] = Str::lower(trim($credentials['username']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['username' => 'Username or password is incorrect.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showRecover(): View
    {
        return view('auth.recover');
    }

    public function recover(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'recovery_code' => ['required', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::where('username', Str::lower(trim($data['username'])))->first();
        $code = Str::upper(trim($data['recovery_code']));

        if (! $user || ! Hash::check($code, $user->recovery_code)) {
            throw ValidationException::withMessages(['recovery_code' => 'Username or recovery code is incorrect.']);
        }

        $newCode = self::newRecoveryCode();
        $user->forceFill([
            'password' => $data['password'],
            'recovery_code' => $newCode,
            'remember_token' => Str::random(60),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('recovery-code.show')->with('recovery_code', $newCode);
    }

    public function showRecoveryCode(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('recovery_code')) {
            return redirect()->route('dashboard');
        }

        return view('auth.recovery-code', ['code' => $request->session()->get('recovery_code')]);
    }

    public static function newRecoveryCode(): string
    {
        return implode('-', str_split(Str::upper(Str::random(16)), 4));
    }

    /**
     * Cheap bot defence without email or CAPTCHA: a hidden honeypot field and a minimum fill time.
     */
    private function rejectBots(Request $request, string $timestampKey): void
    {
        $shownAt = (int) $request->session()->get($timestampKey, 0);

        if ($request->filled('website') || $shownAt === 0 || now()->timestamp - $shownAt < 2) {
            throw ValidationException::withMessages(['username' => 'Please wait a moment and submit the form again.']);
        }
    }
}
