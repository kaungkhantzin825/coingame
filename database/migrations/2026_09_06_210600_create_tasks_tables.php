<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('kind', 20)->default('one_time'); // one_time|daily|progress
            $table->unsignedInteger('goal')->default(1);
            $table->string('event', 40)->nullable(); // which game event advances it: play_game, merge, reach_score, watch_ad, invite...
            $table->unsignedBigInteger('reward_coins')->default(0);
            $table->integer('reward_cash_cents')->default(0);
            $table->unsignedInteger('reward_gems')->default(0);
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('period')->nullable(); // set for daily tasks
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'task_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tasks');
        Schema::dropIfExists('tasks');
    }
};
