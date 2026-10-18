<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('client_agreements', 'uploaded_by')) {
            return;
        }

        Schema::table('client_agreements', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
        });

        Schema::table('client_agreements', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->change();
        });

        Schema::table('client_agreements', function (Blueprint $table) {
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Keep the column nullable because the foreign key uses ON DELETE SET NULL.
    }
};
