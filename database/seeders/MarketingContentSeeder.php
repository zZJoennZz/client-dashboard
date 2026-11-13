<?php

namespace Database\Seeders;

use App\Models\MarketingActivity;
use App\Models\Resource;
use App\Models\ResourceVersion;
use Illuminate\Database\Seeder;

class MarketingContentSeeder extends Seeder
{
    public function run(): void
    {
        MarketingActivity::query()->firstOrCreate(
            ['title' => 'Community promotion campaign'],
            [
                'promotion_type' => 'Campaign',
                'platforms' => [
                    ['platform' => 'Facebook', 'url' => null],
                    ['platform' => 'Instagram', 'url' => null],
                ],
                'member_description' => 'A coordinated community campaign across social platforms.',
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