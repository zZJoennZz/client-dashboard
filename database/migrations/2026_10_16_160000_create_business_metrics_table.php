<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('clients')->restrictOnDelete();
            $table->string('metric_type');
            $table->decimal('value', 14, 4);
            $table->string('unit', 32)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('source')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['business_id', 'period_start', 'period_end'], 'business_metrics_business_period_index');
            $table->index(['business_id', 'metric_type', 'period_start'], 'business_metrics_business_type_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_metrics');
    }
};
