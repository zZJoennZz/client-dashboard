<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('directory_visibility')->default('private');
            $table->timestamp('published_at')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->index(['status', 'is_published']);
            $table->index('directory_visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_published']);
            $table->dropIndex(['directory_visibility']);
            $table->dropColumn([
                'description',
                'logo_path',
                'status',
                'is_published',
                'is_featured',
                'directory_visibility',
                'published_at',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
            ]);
        });
    }
};
