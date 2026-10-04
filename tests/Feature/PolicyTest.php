<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\EntryStatus;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_update_another_users_account(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $account = ChartOfAccount::factory()->ofType(AccountType::Asset)->create(['user_id' => $alice->id]);

        $this->assertTrue($alice->can('update', $account));
        $this->assertFalse($bob->can('update', $account));
    }

    public function test_locked_accounts_cannot_be_edited_or_deleted(): void
    {
        $alice = User::factory()->create();
        $locked = ChartOfAccount::factory()->ofType(AccountType::Equity)->create([
            'user_id' => $alice->id,
            'is_editable' => false,
            'is_deletable' => false,
        ]);

        $this->assertFalse($alice->can('update', $locked));
        $this->assertFalse($alice->can('delete', $locked));
    }

    public function test_posted_entries_cannot_be_edited_only_voided(): void
    {
        $alice = User::factory()->create();
        $posted = JournalEntry::factory()->create(['user_id' => $alice->id, 'status' => EntryStatus::Posted]);
        $draft = JournalEntry::factory()->draft()->create(['user_id' => $alice->id]);

        $this->assertFalse($alice->can('update', $posted));
        $this->assertTrue($alice->can('void', $posted));
        $this->assertTrue($alice->can('update', $draft));
    }
}
