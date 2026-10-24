<?php

namespace App\Http\Controllers;

use App\Services\CatalogSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(): View
    {
        return view('account.show', ['user' => Auth::user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:80']]);
        $request->user()->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();

        return back()->with('status', 'Password changed. Other devices were signed out.');
    }

    public function regenerateRecoveryCode(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $code = AuthController::newRecoveryCode();
        $request->user()->update(['recovery_code' => $code]);

        return redirect()->route('recovery-code.show')->with('recovery_code', $code);
    }

    public function destroy(Request $request, CatalogSync $catalog): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();

        DB::transaction(function () use ($user, $catalog): void {
            foreach ($user->studyLists as $list) {
                $catalog->deleteList($list);
            }

            $user->delete();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
