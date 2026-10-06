<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Mockery;
use Tests\TestCase;

class WelcomeEmailTest extends TestCase
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

    public function test_a_welcome_email_is_queued_for_a_new_user(): void
    {
        Mail::fake();
        $this->fakeFirebase('uid-new', 'nuevo@acme.mx', 'Nuevo');

        $this->postJson('/auth/session', ['id_token' => 'any'])->assertOk();

        Mail::assertQueued(WelcomeMail::class, fn (WelcomeMail $m) => $m->hasTo('nuevo@acme.mx'));
    }

    public function test_no_welcome_email_for_an_existing_user(): void
    {
        User::factory()->create(['firebase_uid' => 'uid-old', 'email' => 'viejo@acme.mx']);
        Mail::fake();
        $this->fakeFirebase('uid-old', 'viejo@acme.mx', 'Viejo');

        $this->postJson('/auth/session', ['id_token' => 'any'])->assertOk();

        Mail::assertNothingQueued();
    }
}
