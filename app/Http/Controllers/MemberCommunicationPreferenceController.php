<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberCommunicationPreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email_announcements_opt_in' => ['required', 'boolean'],
        ]);

        $profile = $request->user()->profile()->firstOrCreate(
            [],
            ['first_name' => 'Member']
        );
        $profile->forceFill([
            'email_announcements_opt_in' => $data['email_announcements_opt_in'],
        ])->save();

        return response()->json([
            'email_announcements_opt_in' => $profile->email_announcements_opt_in,
        ]);
    }
}
