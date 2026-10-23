<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();       // tng, duitnow, bank, dana, gcash
            $table->string('label');
            $table->string('type', 20)->default('ewallet'); // ewallet|bank
            $table->integer('min_cents')->default(500);
            $table->integer('fee_cents')->default(0);
            $table->unsignedTinyInteger('fee_percent')->default(0);
            $table->json('fields');                 // [{key,label,type,required}]
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method_key', 30);
            $table->integer('amount_cents');
            $table->integer('fee_cents')->default(0);
            $table->integer('net_cents');
            $table->text('account');               // encrypted json
            $table->string('status', 20)->default('pending'); // pending|approved|processing|paid|rejected
            $table->string('admin_note')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('withdraw_methods');
    }
};
