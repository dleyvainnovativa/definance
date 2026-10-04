<?php

namespace App\Rules;

use App\Models\ChartOfAccount;
use App\Models\Scopes\UserScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * Validates that an account id belongs to the authenticated user (and is
 * postable, by default). This closes the old IDOR where `exists:chart_of_accounts,id`
 * accepted any user's account id.
 *
 *   'debit_account_id' => ['required', new OwnedAccount],
 *   'parent_id'        => ['nullable', new OwnedAccount(requirePostable: false)],
 */
class OwnedAccount implements ValidationRule
{
    public function __construct(private bool $requirePostable = true)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $account = ChartOfAccount::withoutGlobalScope(UserScope::class)
            ->where('user_id', Auth::id())
            ->find($value);

        if (! $account) {
            $fail('The selected account is invalid.');

            return;
        }

        if ($this->requirePostable && (! $account->is_postable || ! $account->is_active)) {
            $fail('The selected account cannot receive entries.');
        }
    }
}
