<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('applies_to', 32);
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['name', 'applies_to']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('access_role_id')->nullable()->after('account_type')
                ->constrained('access_roles')->restrictOnDelete();
            $table->string('system_admin_slot', 32)->nullable()->unique();
        });

        $systemAdmins = DB::table('users')
            ->where('account_type', 'super_admin')
            ->orderBy('id')
            ->pluck('id');

        if ($systemAdmins->isNotEmpty()) {
            DB::table('users')->where('id', $systemAdmins->first())
                ->update(['system_admin_slot' => 'system']);
            DB::table('users')->whereIn('id', $systemAdmins->slice(1))
                ->update(['account_type' => 'administrator']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['access_role_id']);
            $table->dropUnique(['system_admin_slot']);
            $table->dropColumn(['access_role_id', 'system_admin_slot']);
        });

        Schema::dropIfExists('access_roles');
    }
};
