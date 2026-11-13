<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        if ($request->user()?->isSystemAdministrator()) {
            return redirect()->route('super-admin.spa');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
