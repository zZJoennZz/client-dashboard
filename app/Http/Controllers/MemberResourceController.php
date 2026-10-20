<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\ResourceAcknowledgment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberResourceController extends Controller
{
    public function acknowledge(Request $request, Resource $resource): JsonResponse
    {
        abort_if($resource->trashed() || $resource->status !== 'published', 404);
        abort_unless($resource->requires_acknowledgment, 409);

        /** @var User $user */
        $user = $request->user();
        $businesses = $user->businesses()->wherePivot('status', 'active')
            ->when($resource->business_id !== null, fn ($query) => $query->whereKey($resource->business_id))
            ->get();
        abort_if($businesses->isEmpty(), 404);

        DB::transaction(function () use ($businesses, $resource, $user): void {
            foreach ($businesses as $business) {
                ResourceAcknowledgment::query()->updateOrCreate(
                    ['resource_id' => $resource->id, 'business_id' => $business->id, 'version' => $resource->version],
                    ['user_id' => $user->id, 'acknowledged_at' => now()],
                );
            }
        });

        return response()->json(['acknowledged' => true, 'version' => $resource->version]);
    }
}