<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketing_activities', 'platforms')) {
            Schema::table('marketing_activities', function (Blueprint $table): void {
                $table->json('platforms')->nullable();
            });
        }

        DB::table('marketing_activities')
            ->select(['id', 'platform', 'published_url'])
            ->orderBy('id')
            ->chunk(100, function ($activities): void {
                foreach ($activities as $activity) {
                    $legacyPlatform = trim((string) $activity->platform);
                    $url = filter_var($activity->published_url, FILTER_VALIDATE_URL)
                        && in_array(strtolower((string) parse_url($activity->published_url, PHP_URL_SCHEME)), ['http', 'https'], true)
                        ? $activity->published_url
                        : null;

                    if ($legacyPlatform === '' && $url === null) {
                        continue;
                    }

                    $platform = 'Other';
                    foreach (config('marketing.platforms') as $option) {
                        $matches = $option === 'X'
                            ? preg_match('/(^|[^a-z])x([^a-z]|$)|twitter/i', $legacyPlatform) === 1
                            : str_contains(strtolower($legacyPlatform), strtolower($option));
                        if ($option !== 'Other' && $matches) {
                            $platform = $option;
                            break;
                        }
                    }

                    DB::table('marketing_activities')
                        ->where('id', $activity->id)
                        ->update(['platforms' => json_encode([['platform' => $platform, 'url' => $url]])]);
                }
            });

        Schema::table('marketing_activities', function (Blueprint $table): void {
            $table->dropForeign(['business_id']);
            $table->dropForeign(['responsible_admin_id']);
            $table->dropIndex(['business_id', 'status']);
            $table->dropIndex(['scope', 'scheduled_at']);
            $table->dropColumn(['business_id', 'responsible_admin_id', 'scope', 'platform', 'published_url']);
        });
    }

    public function down(): void
    {
        Schema::table('marketing_activities', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('scope')->default('program');
            $table->foreignId('responsible_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('platform')->nullable();
            $table->string('published_url')->nullable();
            $table->dropColumn('platforms');
        });

        Schema::table('marketing_activities', function (Blueprint $table): void {
            $table->index(['business_id', 'status']);
            $table->index(['scope', 'scheduled_at']);
        });
    }
};
