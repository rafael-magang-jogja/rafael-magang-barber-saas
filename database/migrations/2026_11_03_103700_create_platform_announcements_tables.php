<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->text('message');
            $table->string('type', 30)->default('info'); // info, warning, maintenance, urgent
            $table->string('target_role', 30)->default('all'); // all, owner, admin, barber, customer
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'target_role']);
        });

        Schema::create('platform_announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('platform_announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('dismissed_permanently')->default(false);
            $table->timestamp('last_shown_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcement_reads');
        Schema::dropIfExists('platform_announcements');
    }
};
