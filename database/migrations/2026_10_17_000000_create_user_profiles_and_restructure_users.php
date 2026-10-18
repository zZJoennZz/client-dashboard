<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('job_title')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 2)->default('US');
            $table->timestamps();
        });

        if (Schema::hasColumn('users', 'name')) {
            $legacyColumns = ['id', 'name'];

            foreach (['phone', 'job_title'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $legacyColumns[] = $column;
                }
            }

            DB::table('users')
                ->select($legacyColumns)
                ->orderBy('id')
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        $parts = preg_split('/\s+/u', trim((string) $user->name), 2, PREG_SPLIT_NO_EMPTY) ?: [];

                        DB::table('user_profiles')->insertOrIgnore([
                            'user_id' => $user->id,
                            'first_name' => $parts[0] ?? 'Member',
                            'last_name' => $parts[1] ?? null,
                            'phone' => $user->phone ?? null,
                            'job_title' => $user->job_title ?? null,
                            'country' => 'US',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->default('regular')->index();
        });

        $this->preserveLegacyClientAssociations();
        $this->dropLegacyClientId();

        $columnsToDrop = array_values(array_filter(
            ['name', 'phone', 'job_title'],
            fn (string $column): bool => Schema::hasColumn('users', $column)
        ));

        if ($columnsToDrop !== []) {
            Schema::table('users', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->dropIndex(['account_type']);
            $table->dropColumn('account_type');
        });

        DB::table('user_profiles')
            ->orderBy('user_id')
            ->chunkById(100, function ($profiles): void {
                foreach ($profiles as $profile) {
                    DB::table('users')
                        ->where('id', $profile->user_id)
                        ->update([
                            'name' => trim($profile->first_name.' '.($profile->last_name ?? '')),
                            'phone' => $profile->phone,
                            'job_title' => $profile->job_title,
                        ]);
                }
            }, 'user_id');

        if (! Schema::hasColumn('users', 'client_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('client_id')->nullable()->after('id');
            });

            if (Schema::hasTable('clients')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('business_user')) {
            DB::table('business_user')
                ->where('is_primary', true)
                ->orderBy('id')
                ->chunkById(100, function ($associations): void {
                    foreach ($associations as $association) {
                        DB::table('users')
                            ->where('id', $association->user_id)
                            ->whereNull('client_id')
                            ->update(['client_id' => $association->business_id]);
                    }
                });
        }

        Schema::dropIfExists('user_profiles');
    }

    private function preserveLegacyClientAssociations(): void
    {
        if (! Schema::hasColumn('users', 'client_id') || ! Schema::hasTable('business_user')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('business_user')->insertOrIgnore([
                        'business_id' => $user->client_id,
                        'user_id' => $user->id,
                        'role' => 'member',
                        'access_level' => 'member',
                        'status' => 'active',
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function dropLegacyClientId(): void
    {
        if (! Schema::hasColumn('users', 'client_id')) {
            return;
        }

        $hasClientForeignKey = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === ['client_id']);

        if ($hasClientForeignKey) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropForeign(['client_id']);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('client_id');
        });
    }
};
