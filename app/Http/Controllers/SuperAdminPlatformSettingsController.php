<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MembershipPlan;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SuperAdminPlatformSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'membership_pricing' => $this->membershipPricing(),
            'website_content' => [
                'status' => 'planned',
                'description' => 'Editing public page content will be added in a later phase.',
            ],
        ]);
    }

    public function updateMembershipPricing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['present', 'nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'period' => ['required', Rule::in(['month', 'year'])],
        ]);

        $old = $this->membershipPricing();
        $updated = [
            'amount' => $data['amount'] === null ? null : number_format((float) $data['amount'], 2, '.', ''),
            'currency_symbol' => $old['currency_symbol'],
            'period' => $data['period'],
        ];

        DB::transaction(function () use ($request, $old, $updated): void {
            foreach (['amount', 'period'] as $field) {
                PlatformSetting::query()->updateOrCreate(
                    ['key' => 'membership.'.$field],
                    ['value' => $updated[$field], 'updated_by' => $request->user()->id],
                );
            }

            $slug = 'platform-membership-'.substr(sha1(json_encode($updated, JSON_THROW_ON_ERROR)), 0, 24);
            MembershipPlan::query()->where('is_default', true)->update(['is_default' => false]);
            MembershipPlan::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => 'Rediscover Roanoke Membership',
                    'description' => 'Platform-configured membership pricing. Registration payment status remains simulated; no payment is collected.',
                    'price' => $updated['amount'] ?? 0,
                    'currency' => 'USD',
                    'billing_interval' => $updated['period'],
                    'is_active' => true,
                    'is_default' => true,
                    'trial_days' => 0,
                    'sort_order' => 0,
                ],
            );

            AuditLog::record(
                $request->user(),
                'platform.membership_pricing_updated',
                null,
                $old,
                $updated,
            );
        });

        return response()->json([
            'membership_pricing' => $updated,
            'message' => 'Public membership pricing was updated. Registration still does not collect payment.',
        ]);
    }

    /**
     * @return array{amount: string|null, currency_symbol: string, period: string}
     */
    private function membershipPricing(): array
    {
        return [
            'amount' => PlatformSetting::getValue('membership.amount', config('membership.amount')),
            'currency_symbol' => (string) PlatformSetting::getValue('membership.currency_symbol', config('membership.currency_symbol', '$')),
            'period' => (string) PlatformSetting::getValue('membership.period', config('membership.period', 'year')),
        ];
    }
}
