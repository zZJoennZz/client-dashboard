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
        Schema::create('report_a_i_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20);                 // processing | ready | failed
            $table->json('summary_json')->nullable();     // validated structured data
            $table->longText('summary_html')->nullable(); // rendered by Blade, safe to output
            $table->string('model', 100)->nullable();
            $table->string('files_hash', 64)->nullable(); // skip re-analysis if files unchanged
            $table->text('error')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_a_i_analyses');
    }
};
