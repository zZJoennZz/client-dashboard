<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('accounts.super_admin_initial_password');
        if ($password === '') {
            throw new InvalidArgumentException('Set SUPER_ADMIN_INITIAL_PASSWORD before seeding super admin accounts.');
        }

        foreach (config('accounts.super_admin_emails', []) as $configuredEmail) {
            $email = strtolower(trim((string) $configuredEmail));
            $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
            if ($validator->fails()) {
                throw new InvalidArgumentException("Invalid super admin email configured: {$configuredEmail}");
            }

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
            $isNewUser = $user === null;
            $user ??= new User(['email' => $email]);
            $user->forceFill([
                'account_type' => User::ACCOUNT_TYPE_SUPER_ADMIN,
                'password' => $password,
                ...($isNewUser ? ['email_verified_at' => now()] : []),
            ])->save();

            if (! $user->profile()->exists()) {
                $user->profile()->create([
                    'first_name' => 'Super Admin',
                    'country' => 'US',
                ]);
            }
        }
    }
}
