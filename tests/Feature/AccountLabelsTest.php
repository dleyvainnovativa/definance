<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Etiqueta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class AccountLabelsTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    public function test_account_can_be_tagged_on_create_and_returns_labels(): void
    {
        $user = User::factory()->create();
        $a = Etiqueta::factory()->create(['user_id' => $user->id, 'name' => 'Suscripciones']);
        $b = Etiqueta::factory()->create(['user_id' => $user->id, 'name' => 'Fijos']);

        $this->loginApi($user)->postJson('/api/accounts', [
            'code' => '500.01', 'name' => 'Netflix', 'type' => 'expense', 'label_ids' => [$a->id, $b->id],
        ])->assertCreated()->assertJsonCount(2, 'data.labels');

        $this->assertDatabaseHas('account_etiqueta', ['etiqueta_id' => $a->id]);
    }

    public function test_labels_are_replaced_on_update(): void
    {
        $user = User::factory()->create();
        $acct = ChartOfAccount::factory()->ofType(AccountType::Expense)->create(['user_id' => $user->id, 'code' => '500.02']);
        $a = Etiqueta::factory()->create(['user_id' => $user->id]);
        $b = Etiqueta::factory()->create(['user_id' => $user->id]);
        $acct->etiquetas()->sync([$a->id]);

        $this->loginApi($user)->putJson("/api/accounts/{$acct->id}", ['label_ids' => [$b->id]])
            ->assertOk()->assertJsonCount(1, 'data.labels')->assertJsonPath('data.labels.0.id', $b->id);

        $this->assertDatabaseMissing('account_etiqueta', ['account_id' => $acct->id, 'etiqueta_id' => $a->id]);
    }

    public function test_cannot_tag_with_another_users_label(): void
    {
        $user = User::factory()->create();
        $foreign = Etiqueta::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->loginApi($user)->postJson('/api/accounts', [
            'code' => '500.03', 'name' => 'Spotify', 'type' => 'expense', 'label_ids' => [$foreign->id],
        ])->assertStatus(422)->assertJsonValidationErrorFor('label_ids.0');
    }
}
