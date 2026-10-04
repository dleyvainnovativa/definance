<?php

namespace Tests\Concerns;

use App\Models\User;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Mockery;

/**
 * Lets feature tests authenticate against the real `firebase.jwt` middleware
 * without a live Firebase: the kreait Auth contract is mocked to return a token
 * whose `sub` matches the given user's firebase_uid, and a Bearer header is set.
 */
trait FakesFirebaseAuth
{
    protected function loginApi(User $user): static
    {
        $claims = Mockery::mock();
        $claims->shouldReceive('get')->with('sub')->andReturn($user->firebase_uid);

        $token = Mockery::mock();
        $token->shouldReceive('claims')->andReturn($claims);

        $firebase = Mockery::mock(FirebaseAuth::class);
        $firebase->shouldReceive('verifyIdToken')->andReturn($token);

        $this->app->instance(FirebaseAuth::class, $firebase);

        return $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ]);
    }
}
