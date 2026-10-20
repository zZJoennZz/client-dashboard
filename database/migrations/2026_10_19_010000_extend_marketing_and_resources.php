<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_activities', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->change();
            $table->string('scope')->default('business')->after('business_id');
            $table->index(['scope', 'scheduled_at']);
        });

        Schema::table('resources', function (Blueprint $table): void {
            $table->string('category')->default('General')->after('resource_type');
            $table->string('resource_group')->default('general')->after('category');
            $table->json('tags')->nullable()->after('category');
            $table->longText('content')->nullable()->after('description');
            $table->string('video_embed_url')->nullable()->after('file_url');
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('video_embed_url');
            $table->boolean('requires_acknowledgment')->default(false)->after('is_featured');
            $table->unsignedInteger('version')->default(1)->after('requires_acknowledgment');
        });

        Schema::create('resource_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['resource_id', 'version']);
        });

        Schema::create('resource_acknowledgments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['resource_id', 'business_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_acknowledgments');
        Schema::dropIfExists('resource_versions');

        Schema::table('resources', function (Blueprint $table): void {
            $table->dropColumn([
                'category',
                'resource_group',
                'tags',
                'content',
                'video_embed_url',
                'estimated_minutes',
                'requires_acknowledgment',
                'version',
            ]);
        });

        Schema::table('marketing_activities', function (Blueprint $table): void {
            $table->dropIndex(['scope', 'scheduled_at']);
            $table->dropColumn('scope');
            $table->foreignId('business_id')->nullable(false)->change();
        });
    }
};