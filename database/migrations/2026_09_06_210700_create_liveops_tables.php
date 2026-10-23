<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liveops_events', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('kind', 20)->default('tap_timer'); // tap_timer
            $table->unsignedInteger('duration_seconds')->default(60);
            $table->unsignedBigInteger('reward_coins')->default(0);
            $table->integer('reward_cash_cents')->default(0);
            $table->unsignedTinyInteger('ad_multiplier')->default(2); // watch ad -> ×N reward
            $table->unsignedInteger('cooldown_seconds')->default(3600);
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_liveops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('liveops_events')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('claimed_at')->nullable();
            $table->boolean('boosted')->default(false);
            $table->unsignedBigInteger('coins_awarded')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_liveops');
        Schema::dropIfExists('liveops_events');
    }
};
