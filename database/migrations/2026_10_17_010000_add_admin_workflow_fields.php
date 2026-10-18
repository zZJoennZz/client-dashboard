<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('registration_enabled')->default(true);
            $table->timestamp('registration_deadline')->nullable();
        });

        Schema::table('marketing_activities', function (Blueprint $table): void {
            $table->string('promotion_type')->nullable();
            $table->string('platform')->nullable();
            $table->text('caption')->nullable();
            $table->text('member_description')->nullable();
            $table->string('published_url')->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('assigned_staff_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->text('fulfillment_notes')->nullable();
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->boolean('email_announcements_opt_in')->default(false);
        });

        Schema::create('member_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('subject', 200);
            $table->text('body');
            $table->json('audience')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('failed_recipients')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_announcements');

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn('email_announcements_opt_in');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_staff_id');
            $table->dropColumn('fulfillment_notes');
        });

        Schema::table('marketing_activities', function (Blueprint $table): void {
            $table->dropColumn(['promotion_type', 'platform', 'caption', 'member_description', 'published_url']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['registration_enabled', 'registration_deadline']);
        });
    }
};
