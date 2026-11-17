<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Credits\CreditLedger;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('DEMO_PASSWORD') ?: 'password';

        $user = User::query()->updateOrCreate(
            ['email' => 'demo@pixelforge.test'],
            [
                'name' => 'Demo User',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_admin' => true,
            ],
        );

        if ($user->credit_balance <= 0) {
            app(CreditLedger::class)->purchase(
                $user,
                Money::fromDecimalString('25.000000000'),
                'demo-opening-balance',
            );
        }

        $this->command?->info("Demo user: demo@pixelforge.test / {$password}");
    }
}
