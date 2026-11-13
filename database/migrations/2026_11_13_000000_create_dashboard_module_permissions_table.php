<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_module_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->boolean('can_read')->default(true);
            $table->boolean('can_write')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_module_permissions');
    }
};
