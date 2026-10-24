<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registers_with_username_only_and_sees_a_recovery_code(): void
    {
        $this->get('/register');
        $this->travel(3)->seconds();

        $response = $this->post('/register', [
            'username' => 'Rahim_01',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('recovery-code.show'));
        $this->assertAuthenticated();

        $user = User::firstWhere('username', 'rahim_01');
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin, 'The first account becomes admin.');
        $this->assertTrue(Hash::check(session('recovery_code'), $user->recovery_code));
    }

    public function test_bots_filling_the_honeypot_are_rejected(): void
    {
        $this->get('/register');
        $this->travel(3)->seconds();

        $this->post('/register', [
            'username' => 'bot',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'website' => 'http://spam.example',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $this->get('/register');
        $this->travel(3)->seconds();

        $this->post('/register', [
            'username' => 'karim',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_login_is_case_insensitive_on_username(): void
    {
        User::factory()->create(['username' => 'karim', 'password' => 'secret123']);

        $this->post('/login', ['username' => 'KARIM', 'password' => 'secret123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_password_can_be_reset_with_the_recovery_code_and_code_rotates(): void
    {
        $code = AuthController::newRecoveryCode();
        $user = User::factory()->create(['username' => 'karim', 'recovery_code' => $code]);

        $this->post('/recover', [
            'username' => 'karim',
            'recovery_code' => strtolower($code),
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('recovery-code.show'));

        $user->refresh();
        $this->assertTrue(Hash::check('newpass123', $user->password));
        $this->assertFalse(Hash::check($code, $user->recovery_code), 'Old code must stop working.');
    }

    public function test_wrong_recovery_code_is_rejected(): void
    {
        User::factory()->create(['username' => 'karim']);

        $this->post('/recover', [
            'username' => 'karim',
            'recovery_code' => 'AAAA-BBBB-CCCC-DDDD',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHasErrors('recovery_code');
    }
}
