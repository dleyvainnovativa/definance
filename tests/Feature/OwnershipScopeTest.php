<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_scoped_to_the_authenticated_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        ChartOfAccount::factory()->ofType(AccountType::Asset)->count(3)->create(['user_id' => $alice->id]);
        $bobAccount = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $bob->id]);

        $this->actingAs($alice);

        $this->assertSame(3, ChartOfAccount::count());
        // Bob's account is invisible to Alice even by direct id lookup.
        $this->assertNull(ChartOfAccount::find($bobAccount->id));
    }

    public function test_user_id_is_stamped_from_the_authenticated_user_on_create(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $account = ChartOfAccount::create([
            'code' => '101',
            'name' => 'Caja',
            'type' => AccountType::Asset,
        ]);

        $this->assertSame($alice->id, $account->user_id);
    }

    public function test_for_user_scope_bypasses_the_global_scope(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $bob->id]);

        $this->actingAs($alice);

        // Explicit console-style lookup can still reach another user's rows.
        $this->assertSame(1, ChartOfAccount::query()->forUser($bob->id)->count());
    }
}
