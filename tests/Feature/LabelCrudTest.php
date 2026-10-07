<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class LabelCrudTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    public function test_creates_and_lists_labels(): void
    {
        $user = User::factory()->create();

        $this->loginApi($user)->postJson('/api/labels', ['name' => 'Suscripciones', 'color' => '#406dab'])
            ->assertCreated()->assertJsonPath('data.name', 'Suscripciones');

        $this->loginApi($user)->getJson('/api/labels')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_name_is_unique_per_user(): void
    {
        $user = User::factory()->create();
        Etiqueta::factory()->create(['user_id' => $user->id, 'name' => 'Viajes']);

        $this->loginApi($user)->postJson('/api/labels', ['name' => 'Viajes'])
            ->assertStatus(422)->assertJsonValidationErrorFor('name');
    }

    public function test_update_and_delete(): void
    {
        $user = User::factory()->create();
        $label = Etiqueta::factory()->create(['user_id' => $user->id, 'name' => 'Old']);

        $this->loginApi($user)->putJson("/api/labels/{$label->id}", ['name' => 'New'])
            ->assertOk()->assertJsonPath('data.name', 'New');

        $this->loginApi($user)->deleteJson("/api/labels/{$label->id}")->assertNoContent();
        $this->assertDatabaseMissing('etiquetas', ['id' => $label->id]);
    }

    public function test_cannot_touch_another_users_label(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $label = Etiqueta::factory()->create(['user_id' => $owner->id]);

        $this->loginApi($other)->putJson("/api/labels/{$label->id}", ['name' => 'Hack'])->assertNotFound();
        $this->loginApi($other)->deleteJson("/api/labels/{$label->id}")->assertNotFound();
    }

    public function test_listing_is_scoped_to_the_user(): void
    {
        $alice = User::factory()->create();
        Etiqueta::factory()->count(2)->create(['user_id' => $alice->id]);
        Etiqueta::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->loginApi($alice)->getJson('/api/labels')->assertOk()->assertJsonCount(2, 'data');
    }
}
