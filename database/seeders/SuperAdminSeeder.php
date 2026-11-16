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
        $emails = config('accounts.system_admin_emails', []);
        if (count($emails) > 1) {
            throw new InvalidArgumentException('Configure only one System Administrator email address.');
        }
        if ($emails === []) {
            return;
        }

        $password = (string) config('accounts.system_admin_initial_password');
        if ($password === '') {
            throw new InvalidArgumentException('Set SYSTEM_ADMIN_INITIAL_PASSWORD before seeding the System Administrator account.');
        }

        $configuredEmail = $emails[0];
        $email = strtolower(trim((string) $configuredEmail));
        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            throw new InvalidArgumentException("Invalid System Administrator email configured: {$configuredEmail}");
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
        $existingSystemAdmin = User::query()
            ->where('account_type', User::ACCOUNT_TYPE_SUPER_ADMIN)
            ->first();
        if ($existingSystemAdmin !== null && $existingSystemAdmin->isNot($user)) {
            throw new InvalidArgumentException('A different System Administrator account already exists.');
        }
        $isNewUser = $user === null;
        $user ??= new User(['email' => $email]);
        $user->forceFill([
            'account_type' => User::ACCOUNT_TYPE_SUPER_ADMIN,
            'password' => $password,
            ...($isNewUser ? ['email_verified_at' => now()] : []),
        ])->save();

        if (! $user->profile()->exists()) {
            $user->profile()->create([
                'first_name' => 'System Administrator',
                'country' => 'US',
            ]);
        }
    }
}
