<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('resource_type')->default('article');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->string('status')->default('draft');
            $table->string('visibility')->default('private');
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'visibility']);
            $table->index(['status', 'visibility']);
            $table->index('resource_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
