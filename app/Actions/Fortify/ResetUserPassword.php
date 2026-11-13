<?php

namespace App\Actions\Fortify;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset(AuthenticatableUser $user, array $input): void
    {
        $data = Validator::make($input, [
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ])->validate();

        $user->forceFill(['password' => $data['password']])->save();
    }
}
