<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class RegistrationController extends Controller implements CreatesNewUsers
{
    /**
     * Validate registration data and create the account and simulated membership.
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $data = Validator::make($input, [
            'company_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $fullName = trim($data['first_name'].' '.$data['last_name']);
            $user->profile()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
            ]);

            $business = Business::create([
                'company_name' => $data['company_name'],
                'slug' => $this->uniqueBusinessSlug($data['company_name']),
                'description' => $data['description'] ?? null,
                'contact_name' => $fullName,
                'contact_email' => $data['email'],
                'contact_phone' => $data['phone'] ?? null,
                'website' => $data['website'] ?? null,
                'address_line_1' => $data['address_line_1'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => 'US',
                'status' => 'active',
                'is_active' => true,
                'is_published' => false,
                'directory_visibility' => 'private',
                'settings' => [
                    'category' => $data['category'],
                ],
            ]);
            $categoryName = trim($data['category']);
            $category = BusinessCategory::query()->firstOrCreate(
                ['name' => $categoryName],
                ['slug' => $this->uniqueCategorySlug(Str::slug($categoryName) ?: 'category')]
            );
            $business->categories()->attach($category->id, ['is_primary' => true]);

            $business->users()->attach($user->id, [
                'role' => 'owner',
                'access_level' => 'owner',
                'status' => 'active',
                'is_primary' => true,
                'accepted_at' => now(),
            ]);

            $plan = $this->registrationPlan();

            Membership::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'payment_status' => 'paid',
                'start_date' => now()->toDateString(),
                'activated_at' => now(),
                'metadata' => [
                    'payment_provider' => 'placeholder',
                    'payment_mode' => 'simulated',
                    'note' => 'No payment was collected. Payment integration is pending.',
                ],
            ]);

            return $user;
        });
    }

    private function uniqueBusinessSlug(string $companyName): string
    {
        $baseSlug = Str::slug($companyName) ?: 'roanoke-business';
        $slug = $baseSlug;
        $suffix = 2;

        while (Business::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (BusinessCategory::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }

    private function registrationPlan(): MembershipPlan
    {
        $plan = MembershipPlan::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($plan !== null) {
            return $plan;
        }

        $plan = MembershipPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if ($plan !== null) {
            return $plan;
        }

        $configuredAmount = PlatformSetting::getValue('membership.amount', config('membership.amount'));
        $configuredPeriod = PlatformSetting::getValue('membership.period', config('membership.period', 'year'));

        return MembershipPlan::query()->updateOrCreate(
            ['slug' => 'rediscover-roanoke'],
            [
                'name' => 'The Argyle Buzz Membership',
                'description' => 'Local business growth initiative membership. Payment processing is not yet connected.',
                'price' => is_numeric($configuredAmount) ? max(0, (float) $configuredAmount) : 0,
                'currency' => 'USD',
                'billing_interval' => $configuredPeriod,
                'is_active' => true,
                'is_default' => true,
                'trial_days' => 0,
                'sort_order' => 0,
            ]
        );
    }
}
