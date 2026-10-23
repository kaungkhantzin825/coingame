<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40); // merge_reward, daily_checkin, task_reward, ad_reward, ad_reward_revoke, liveops_reward, minigame_reward, referral_bonus, convert_coins_out, convert_cash_in, withdraw_hold, withdraw_paid, withdraw_refund, admin_adjust

            $table->bigInteger('coins_delta')->default(0);
            $table->integer('cash_cents_delta')->default(0);
            $table->integer('gems_delta')->default(0);

            $table->unsignedBigInteger('balance_coins_after');
            $table->integer('balance_cash_after');
            $table->unsignedInteger('balance_gems_after');

            $table->string('idempotency_key')->nullable()->unique();
            $table->string('source', 40)->nullable(); // game, ad, admin, system
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
