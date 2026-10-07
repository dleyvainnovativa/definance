<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Etiqueta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesFirebaseAuth;
use Tests\TestCase;

class LabelReportTest extends TestCase
{
    use FakesFirebaseAuth, RefreshDatabase;

    public function test_label_rolls_up_movement_of_its_tagged_accounts(): void
    {
        $user = User::factory()->create();
        $cash = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $user->id, 'code' => '100.1', 'is_cash' => true]);
        $netflix = ChartOfAccount::factory()->ofType(AccountType::Expense)->create(['user_id' => $user->id, 'code' => '500.01']);
        $spotify = ChartOfAccount::factory()->ofType(AccountType::Expense)->create(['user_id' => $user->id, 'code' => '500.02']);
        $other = ChartOfAccount::factory()->ofType(AccountType::Expense)->create(['user_id' => $user->id, 'code' => '500.09']);

        $subs = Etiqueta::factory()->create(['user_id' => $user->id, 'name' => 'Suscripciones']);
        $subs->accounts()->attach([$netflix->id, $spotify->id]);

        // two egresos tagged, one not
        $pay = fn (ChartOfAccount $exp, int $amt, string $date) => $this->loginApi($user)->postJson('/api/entries', [
            'entry_date' => $date,
            'legs' => [['account_id' => $exp->id, 'debit' => $amt], ['account_id' => $cash->id, 'credit' => $amt]],
        ])->assertCreated();
        $pay($netflix, 219, '2026-05-05');
        $pay($spotify, 115, '2026-05-10');
        $pay($other, 999, '2026-05-12'); // not tagged → excluded

        $res = $this->loginApi($user)->getJson('/api/reports/by-label?from=2026-05-01&to=2026-05-31')
            ->assertOk()->json();

        $row = collect($res['rows'])->firstWhere('name', 'Suscripciones');
        $this->assertNotNull($row);
        $this->assertSame('334.0000', $row['total']);        // 219 + 115
        $this->assertCount(2, $row['accounts']);
    }

    public function test_label_outside_the_period_is_zero(): void
    {
        $user = User::factory()->create();
        $cash = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $user->id, 'code' => '100.1', 'is_cash' => true]);
        $exp = ChartOfAccount::factory()->ofType(AccountType::Expense)->create(['user_id' => $user->id, 'code' => '500.01']);
        $label = Etiqueta::factory()->create(['user_id' => $user->id, 'name' => 'Suscripciones']);
        $label->accounts()->attach([$exp->id]);

        $this->loginApi($user)->postJson('/api/entries', [
            'entry_date' => '2026-05-10',
            'legs' => [['account_id' => $exp->id, 'debit' => 100], ['account_id' => $cash->id, 'credit' => 100]],
        ])->assertCreated();

        $row = collect($this->loginApi($user)->getJson('/api/reports/by-label?from=2026-06-01&to=2026-06-30')->json('rows'))
            ->firstWhere('name', 'Suscripciones');
        $this->assertSame('0.0000', $row['total']);
    }
}
