<?php

namespace Tests\Feature;

use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function device(User $user, string $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0 Safari/537.36'): LoginDevice
    {
        return LoginDevice::create([
            'user_id' => $user->id,
            'ip_address' => '187.1.2.3',
            'user_agent' => $ua,
            'last_login_at' => now(),
        ]);
    }

    public function test_it_lists_only_the_users_devices_with_a_friendly_label(): void
    {
        $this->device($this->user);
        $this->device(User::factory()->create()); // another user's device

        $this->loginApi($this->user)
            ->getJson('/api/devices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Chrome · macOS');
    }

    public function test_a_device_can_be_revoked(): void
    {
        $device = $this->device($this->user);

        $this->loginApi($this->user)->deleteJson("/api/devices/{$device->id}")->assertNoContent();

        $this->assertDatabaseMissing('login_devices', ['id' => $device->id]);
    }

    public function test_a_user_cannot_revoke_another_users_device(): void
    {
        $foreign = $this->device(User::factory()->create());

        $this->loginApi($this->user)->deleteJson("/api/devices/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('login_devices', ['id' => $foreign->id]);
    }
}
