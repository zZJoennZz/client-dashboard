<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['facebook_url', 'instagram_url', 'linkedin_url', 'tiktok_url', 'youtube_url'] as $column) {
            if (Schema::hasColumn('clients', $column)) {
                Schema::table('clients', fn (Blueprint $table) => $table->text($column)->nullable()->change());
            }
        }

        $this->addClientColumn('tagline', fn (Blueprint $table) => $table->string('tagline', 255)->nullable());
        $this->addClientColumn('short_description', fn (Blueprint $table) => $table->string('short_description', 500)->nullable());
        $this->addClientColumn('year_established', fn (Blueprint $table) => $table->unsignedSmallInteger('year_established')->nullable());
        $this->addClientColumn('employee_count', fn (Blueprint $table) => $table->string('employee_count', 60)->nullable());
        $this->addClientColumn('ownership_information', fn (Blueprint $table) => $table->text('ownership_information')->nullable());
        $this->addClientColumn('alternate_phone', fn (Blueprint $table) => $table->string('alternate_phone', 40)->nullable());
        $this->addClientColumn('facebook_url', fn (Blueprint $table) => $table->text('facebook_url')->nullable());
        $this->addClientColumn('instagram_url', fn (Blueprint $table) => $table->text('instagram_url')->nullable());
        $this->addClientColumn('linkedin_url', fn (Blueprint $table) => $table->text('linkedin_url')->nullable());
        $this->addClientColumn('tiktok_url', fn (Blueprint $table) => $table->text('tiktok_url')->nullable());
        $this->addClientColumn('youtube_url', fn (Blueprint $table) => $table->text('youtube_url')->nullable());
        $this->addClientColumn('other_social_url', fn (Blueprint $table) => $table->text('other_social_url')->nullable());
        $this->addClientColumn('latitude', fn (Blueprint $table) => $table->decimal('latitude', 10, 7)->nullable());
        $this->addClientColumn('longitude', fn (Blueprint $table) => $table->decimal('longitude', 10, 7)->nullable());
        $this->addClientColumn('business_hours', fn (Blueprint $table) => $table->json('business_hours')->nullable());
        $this->addClientColumn('specialties', fn (Blueprint $table) => $table->json('specialties')->nullable());
        $this->addClientColumn('accessibility_information', fn (Blueprint $table) => $table->text('accessibility_information')->nullable());
        $this->addClientColumn('appointment_methods', fn (Blueprint $table) => $table->json('appointment_methods')->nullable());
        $this->addClientColumn('service_areas', fn (Blueprint $table) => $table->json('service_areas')->nullable());
        $this->addClientColumn('meta_title', fn (Blueprint $table) => $table->string('meta_title', 255)->nullable());
        $this->addClientColumn('meta_description', fn (Blueprint $table) => $table->string('meta_description', 500)->nullable());

        if (! Schema::hasTable('business_categories')) {
            Schema::create('business_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('business_business_category')) {
            Schema::create('business_business_category', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('business_category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['business_id', 'business_category_id']);
            $table->index(['business_category_id', 'is_primary']);
            });
        }

        if (! Schema::hasTable('business_images')) {
            Schema::create('business_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('clients')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('alt_text', 255)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['business_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('business_hours')) {
            Schema::create('business_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->boolean('by_appointment')->default(false);
            $table->timestamps();
            $table->unique(['business_id', 'day_of_week']);
            });
        }

        if (! Schema::hasTable('business_highlights')) {
            Schema::create('business_highlights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('price_information', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['business_id', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('business_videos')) {
            Schema::create('business_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->string('source_type', 20);
            $table->text('source_url')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
            });
        }

        $legacyCategories = DB::table('clients')
            ->whereNull('deleted_at')
            ->get(['id', 'settings']);
        foreach ($legacyCategories as $business) {
            $settings = is_array($business->settings)
                ? $business->settings
                : (json_decode((string) $business->settings, true) ?: []);
            $categoryName = trim((string) ($settings['category'] ?? ''));
            if ($categoryName === '') {
                continue;
            }

            $slugBase = Str::slug($categoryName) ?: 'category';
            $category = DB::table('business_categories')->where('name', $categoryName)->first();
            if (! $category) {
                $slug = $slugBase;
                $suffix = 2;
                while (DB::table('business_categories')->where('slug', $slug)->exists()) {
                    $slug = $slugBase.'-'.$suffix++;
                }
                $id = DB::table('business_categories')->insertGetId([
                    'name' => $categoryName,
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $id = $category->id;
            }

            DB::table('business_business_category')->insertOrIgnore([
                'business_id' => $business->id,
                'business_category_id' => $id,
                'is_primary' => true,
            ]);
        }
    }

    private function addClientColumn(string $name, \Closure $definition): void
    {
        if (! Schema::hasColumn('clients', $name)) {
            Schema::table('clients', $definition);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_videos');
        Schema::dropIfExists('business_highlights');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('business_images');
        Schema::dropIfExists('business_business_category');
        Schema::dropIfExists('business_categories');

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn([
                'tagline', 'short_description', 'year_established', 'employee_count',
                'ownership_information', 'alternate_phone', 'facebook_url', 'instagram_url',
                'linkedin_url', 'tiktok_url', 'youtube_url', 'other_social_url',
                'latitude', 'longitude', 'business_hours', 'specialties',
                'accessibility_information', 'appointment_methods', 'service_areas',
                'meta_title', 'meta_description',
            ]);
        });
    }
};
