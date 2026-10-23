<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day')->unique(); // 1..7
            $table->unsignedBigInteger('reward_coins')->default(0);
            $table->integer('reward_cash_cents')->default(0);
            $table->unsignedInteger('reward_gems')->default(0);
            $table->boolean('is_bonus')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_rewards');
    }
};
