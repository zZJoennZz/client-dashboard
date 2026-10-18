<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type', 40);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
            $table->index(['activity_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_activities');
    }
};
