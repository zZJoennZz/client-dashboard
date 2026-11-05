<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MemberAccountController extends Controller
{
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $profile = $request->user()->profile()->firstOrCreate(
            [],
            ['first_name' => 'Member']
        );
        $profile->forceFill(array_map(
            fn ($value) => is_string($value) ? trim($value) : $value,
            $data
        ))->save();

        $request->user()->load('profile');

        return response()->json([
            'message' => 'Profile updated successfully.',
            'profile' => [
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'phone' => $profile->phone,
                'job_title' => $profile->job_title,
                'address_line_1' => $profile->address_line_1,
                'address_line_2' => $profile->address_line_2,
                'city' => $profile->city,
                'state' => $profile->state,
                'postal_code' => $profile->postal_code,
                'country' => $profile->country,
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
