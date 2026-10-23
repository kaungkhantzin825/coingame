<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Waterfall config: one row per (placement, network), ordered by priority.
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 40);  // liveops, double_reward, free_booster, daily_boost, task_skip, interstitial
            $table->string('network', 30);    // admob, admob_mediation, applovin, unity, meta, ironsource, pangle
            $table->string('ad_unit_id');
            $table->string('format', 20)->default('rewarded'); // rewarded|interstitial|banner
            $table->unsignedSmallInteger('priority')->default(100); // lower = tried first
            $table->integer('floor_ecpm_cents')->default(0);
            $table->unsignedBigInteger('reward_coins')->default(0);
            $table->unsignedSmallInteger('daily_cap')->default(0); // 0 = unlimited
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['placement', 'active', 'priority']);
        });

        Schema::create('ad_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('placement', 40);
            $table->string('network', 30)->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('reward_coins')->default(0);
            $table->integer('ecpm_cents')->nullable();
            $table->timestamp('provisional_at')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->json('ssv_payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'placement', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_views');
        Schema::dropIfExists('ad_placements');
    }
};
