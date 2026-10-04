<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // In local dev, bootstrap a demo user with the starter chart so the
        // app is usable immediately. In production, seed charts per real user.
        if (app()->environment('local')) {
            $user = User::firstOrCreate(
                ['firebase_uid' => 'demo-local-uid'],
                ['name' => 'Demo', 'email' => 'demo@definance.local']
            );

            ChartOfAccountsSeeder::seedForUser($user->id);
        }
    }
}
