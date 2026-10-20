<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\MarketingActivity;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MarketingContentSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = collect([
            ['slug' => 'blue-ridge-books', 'company_name' => 'Blue Ridge Books', 'contact_name' => 'Jordan Lee', 'contact_email' => 'jordan@blueridgebooks.example'],
            ['slug' => 'roanoke-river-coffee', 'company_name' => 'Roanoke River Coffee', 'contact_name' => 'Casey Morgan', 'contact_email' => 'casey@rivercoffee.example'],
            ['slug' => 'market-street-goods', 'company_name' => 'Market Street Goods', 'contact_name' => 'Taylor Reed', 'contact_email' => 'taylor@marketstreetgoods.example'],
        ])->map(function (array $attributes): Business {
            $business = Business::query()->firstOrCreate(['slug' => $attributes['slug']], $attributes + [
                'status' => 'active', 'is_active' => true, 'is_published' => true,
                'directory_visibility' => 'public', 'published_at' => now(), 'settings' => [],
            ]);
            $user = User::query()->firstOrCreate(['email' => $attributes['contact_email']], [
                'password' => Hash::make('password'), 'email_verified_at' => now(),
            ]);
            if (! $business->users()->whereKey($user->id)->exists()) {
                $business->users()->attach($user->id, [
                    'role' => 'owner', 'access_level' => 'owner', 'status' => 'active', 'is_primary' => true,
                ]);
            }

            return $business;
        });

        foreach ($businesses->take(2) as $index => $business) {
            MarketingActivity::query()->firstOrCreate(
                ['business_id' => $business->id, 'title' => $index === 0 ? 'Summer reading spotlight' : 'Local coffee feature'],
                [
                    'scope' => 'business', 'promotion_type' => 'Social media highlight',
                    'platform' => $index === 0 ? 'City Facebook' : 'City Instagram',
                    'member_description' => 'Your business will be featured in an upcoming community post.',
                    'scheduled_at' => now()->addDays(14 + ($index * 7)), 'status' => 'scheduled',
                ],
            );
        }

        MarketingActivity::query()->firstOrCreate(
            ['scope' => 'program', 'title' => 'Small Business Saturday campaign'],
            [
                'business_id' => null, 'promotion_type' => 'Campaign', 'platform' => 'Program-wide',
                'member_description' => 'A coordinated holiday campaign inviting members to share local shopping offers.',
                'scheduled_at' => now()->addMonth(), 'status' => 'planned',
            ],
        );

        Resource::query()->firstOrCreate(['title' => 'Branding and promotion guidelines'], [
            'resource_group' => 'guideline', 'resource_type' => 'article', 'category' => 'Branding',
            'tags' => ['Branding', 'Promotion'], 'description' => 'Program requirements for logo use and member promotions.',
            'content' => 'Use approved business names and current logos. Promotional claims should be accurate, and all offers must include clear dates and terms.',
            'status' => 'published', 'visibility' => 'member', 'requires_acknowledgment' => true,
            'version' => 1, 'published_at' => now(),
        ]);

        Resource::query()->firstOrCreate(['title' => 'How to leverage social media'], [
            'resource_group' => 'how_to', 'resource_type' => 'article', 'category' => 'Social Media',
            'tags' => ['Social Media', 'Promotion'], 'description' => 'A practical checklist for planning useful, consistent posts.',
            'content' => "Choose one goal for each post.\nUse a clear photo and a short caption.\nInclude your location and a direct next step.\nReply to comments while the post is current.",
            'estimated_minutes' => 5, 'status' => 'published', 'visibility' => 'member', 'published_at' => now(),
        ]);

        Resource::query()->firstOrCreate(['title' => 'Improving storefront appeal'], [
            'resource_group' => 'how_to', 'resource_type' => 'article', 'category' => 'Storefront',
            'tags' => ['Storefront', 'Customer Experience'], 'description' => 'Low-cost ways to make storefronts easier to notice and visit.',
            'content' => "Keep the entrance visible and well lit.\nFeature one seasonal display at a time.\nMake hours and accessibility information easy to find.",
            'estimated_minutes' => 4, 'status' => 'published', 'visibility' => 'member', 'published_at' => now(),
        ]);

        Resource::query()->whereIn('title', [
            'Branding and promotion guidelines',
            'How to leverage social media',
            'Improving storefront appeal',
        ])->each(function (Resource $resource): void {
            ResourceVersion::query()->firstOrCreate(
                ['resource_id' => $resource->id, 'version' => $resource->version],
                ['snapshot' => $resource->only([
                    'title', 'description', 'content', 'resource_type', 'category', 'resource_group', 'tags',
                    'file_name', 'file_path', 'file_url', 'video_embed_url', 'estimated_minutes',
                    'status', 'visibility', 'requires_acknowledgment',
                ])],
            );
        });
    }
}