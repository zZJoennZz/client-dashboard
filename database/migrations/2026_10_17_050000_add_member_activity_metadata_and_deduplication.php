<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_activities')) {
            return;
        }

        Schema::table('member_activities', function (Blueprint $table): void {
            if (! Schema::hasColumn('member_activities', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (! Schema::hasColumn('member_activities', 'activity_date')) {
                $table->date('activity_date')->nullable();
            }
        });

        $hasDailyIndex = collect(Schema::getIndexes('member_activities'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'member_activities_daily_lookup');
        if (Schema::hasColumn('member_activities', 'activity_date') && ! $hasDailyIndex) {
            Schema::table('member_activities', function (Blueprint $table): void {
                $table->index(['activity_date', 'user_id', 'activity_type'], 'member_activities_daily_lookup');
            });
        }

        DB::table('member_activities')
            ->whereNull('activity_date')
            ->orderBy('id')
            ->chunkById(500, function ($activities): void {
                foreach ($activities as $activity) {
                    DB::table('member_activities')
                        ->where('id', $activity->id)
                        ->update(['activity_date' => substr((string) $activity->created_at, 0, 10)]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('member_activities')) {
            return;
        }

        $hasDailyIndex = collect(Schema::getIndexes('member_activities'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'member_activities_daily_lookup');

        if ($hasDailyIndex) {
            Schema::table('member_activities', function (Blueprint $table): void {
                $table->dropIndex('member_activities_daily_lookup');
            });
        }

        $columns = array_values(array_filter(
            ['metadata', 'activity_date'],
            fn (string $column): bool => Schema::hasColumn('member_activities', $column)
        ));
        if ($columns !== []) {
            Schema::table('member_activities', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
