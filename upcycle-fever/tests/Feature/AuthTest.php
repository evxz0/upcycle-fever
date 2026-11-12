<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\GuestSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_player(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Test Player',
            'email'                 => 'player@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('game.lobby'));
        $this->assertAuthenticated();

        $user = User::where('email', 'player@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('player', $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_user_can_login_and_redirect_based_on_role(): void
    {
        $player = User::create([
            'name'      => 'Player One',
            'email'     => 'player@test.com',
            'password'  => 'password123',
            'role'      => 'player',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email'    => 'player@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('game.lobby'));
        $this->assertAuthenticatedAs($player);

        $admin = User::create([
            'name'      => 'Admin User',
            'email'     => 'admin@test.com',
            'password'  => 'password123',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->post('/logout');

        $response = $this->post('/login', [
            'email'    => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'name'      => 'Inactive Player',
            'email'     => 'inactive@test.com',
            'password'  => 'password123',
            'role'      => 'player',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email'    => 'inactive@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_player_cannot_access_admin_dashboard(): void
    {
        $player = User::create([
            'name'      => 'Regular Player',
            'email'     => 'player@test.com',
            'password'  => 'password123',
            'role'      => 'player',
            'is_active' => true,
        ]);

        $response = $this->actingAs($player)->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    public function test_guest_can_start_trial_and_complete_one_stage(): void
    {
        $response = $this->get('/guest/start');
        $response->assertRedirect(route('game.trial'));
        $response->assertSessionHas('guest_token');

        $token = session('guest_token');
        $this->assertNotNull($token);
        $this->assertDatabaseHas('guest_sessions', [
            'guest_token' => $token,
        ]);

        // check can play trial stage 1
        $check = $this->get('/game/check-trial');
        $check->assertStatus(200);

        // complete stage 1
        $complete = $this->post('/game/trial/complete');
        $complete->assertStatus(200);

        // trying to play next stage should be forbidden with LOGIN_REQUIRED
        $secondAttempt = $this->get('/game/check-trial');
        $secondAttempt->assertStatus(403);
        $secondAttempt->assertJson(['error' => 'LOGIN_REQUIRED']);
    }

    public function test_guest_converting_to_player_links_guest_session(): void
    {
        $this->get('/guest/start');
        $token = session('guest_token');

        $this->post('/register', [
            'name'                  => 'Converted Player',
            'email'                 => 'converted@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // After register and login, guest session is marked converted
        $user = User::where('email', 'converted@example.com')->first();
        $this->assertDatabaseHas('guest_sessions', [
            'guest_token'       => $token,
            'converted_user_id' => $user->id,
        ]);
    }
}

