<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('published_at');
            $table->text('review_feedback')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('review_feedback')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['submitted_at', 'review_feedback', 'reviewed_at']);
        });
    }
};
