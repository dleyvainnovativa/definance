<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Mockery;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFirebase(string $uid, ?string $email = null, ?string $name = null): void
    {
        $claims = Mockery::mock();
        $claims->shouldReceive('get')->with('sub')->andReturn($uid);
        $claims->shouldReceive('get')->with('email')->andReturn($email);
        $claims->shouldReceive('get')->with('name')->andReturn($name);

        $token = Mockery::mock();
        $token->shouldReceive('claims')->andReturn($claims);

        $firebase = Mockery::mock(FirebaseAuth::class);
        $firebase->shouldReceive('verifyIdToken')->andReturn($token);

        $this->app->instance(FirebaseAuth::class, $firebase);
    }

    public function test_session_exchange_creates_and_logs_in_the_user(): void
    {
        $this->fakeFirebase('uid-abc', 'ana@acme.mx', 'Ana');

        $response = $this->postJson('/auth/session', ['id_token' => 'any-token']);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('users', ['firebase_uid' => 'uid-abc', 'email' => 'ana@acme.mx']);
        $this->assertDatabaseCount('login_devices', 1);
        $this->assertAuthenticated();
    }

    public function test_existing_user_is_reused_not_duplicated(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-abc']);
        $this->fakeFirebase('uid-abc', 'ana@acme.mx', 'Ana');

        $this->postJson('/auth/session', ['id_token' => 'any-token'])->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_invalid_token_is_rejected(): void
    {
        $firebase = Mockery::mock(FirebaseAuth::class);
        $firebase->shouldReceive('verifyIdToken')->andThrow(new \RuntimeException('bad token'));
        $this->app->instance(FirebaseAuth::class, $firebase);

        $this->postJson('/auth/session', ['id_token' => 'bad'])->assertStatus(401);
        $this->assertGuest();
    }
}
