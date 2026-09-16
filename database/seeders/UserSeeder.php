<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public const string DEFAULT_EMAIL = 'test@example.com';

    public const string DEFAULT_PASSWORD = 'password';

    /**
     * Seed the application's default user for Sanctum smoke tests.
     */
    public function run(): void
    {
        $email = (string) config('seeders.default_user.email');
        $password = (string) config('seeders.default_user.password');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Test User',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );
    }
}
