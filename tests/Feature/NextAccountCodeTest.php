<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class NextAccountCodeTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    private User $user;

    private ChartOfAccount $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->parent = ChartOfAccount::factory()->ofType(AccountType::Asset)
            ->create(['user_id' => $this->user->id, 'code' => '100', 'is_postable' => false]);
    }

    private function child(string $code): ChartOfAccount
    {
        return ChartOfAccount::factory()->ofType(AccountType::Asset)->create([
            'user_id' => $this->user->id, 'parent_id' => $this->parent->id, 'code' => $code,
        ]);
    }

    public function test_suggests_the_first_child_code(): void
    {
        $this->loginApi($this->user)->getJson("/api/accounts/next-code?parent_id={$this->parent->id}")
            ->assertOk()
            ->assertJsonPath('next_code', '100.01');
    }

    public function test_suggests_the_next_after_existing_children(): void
    {
        $this->child('100.01');
        $this->child('100.02');

        $this->loginApi($this->user)->getJson("/api/accounts/next-code?parent_id={$this->parent->id}")
            ->assertOk()
            ->assertJsonPath('next_code', '100.03');
    }

    public function test_gaps_are_not_reused(): void
    {
        $this->child('100.01');
        $this->child('100.03'); // 100.02 was deleted

        $this->loginApi($this->user)->getJson("/api/accounts/next-code?parent_id={$this->parent->id}")
            ->assertOk()
            ->assertJsonPath('next_code', '100.04'); // max+1, not the gap
    }

    public function test_builds_grandchild_codes_from_the_full_parent_code(): void
    {
        $sub = $this->child('100.01');

        $this->loginApi($this->user)->getJson("/api/accounts/next-code?parent_id={$sub->id}")
            ->assertOk()
            ->assertJsonPath('next_code', '100.01.01');
    }

    public function test_parent_id_is_required(): void
    {
        $this->loginApi($this->user)->getJson('/api/accounts/next-code')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('parent_id');
    }

    public function test_parent_must_belong_to_the_user(): void
    {
        $other = User::factory()->create();
        $foreign = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $other->id, 'code' => '900']);

        $this->loginApi($this->user)->getJson("/api/accounts/next-code?parent_id={$foreign->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('parent_id');
    }
}
