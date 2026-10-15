<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'client_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->after('id');
            });
        }

        $hasClientForeignKey = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === ['client_id']
                && ($foreignKey['foreign_table'] ?? null) === 'clients');

        if (! $hasClientForeignKey) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $hasClientForeignKey = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === ['client_id']
                && ($foreignKey['foreign_table'] ?? null) === 'clients');

        if ($hasClientForeignKey) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['client_id']);
            });
        }

        // Keep the column to preserve legacy client associations on rollback.
    }
};
