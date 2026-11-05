<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->date('reporting_period_start')->nullable()->after('reporting_period');
            $table->date('reporting_period_end')->nullable()->after('reporting_period_start');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['reporting_period_start', 'reporting_period_end']);
        });
    }
};
