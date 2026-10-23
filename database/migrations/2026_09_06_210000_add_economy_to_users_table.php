<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Device accounts get generated placeholder name/email/password in
            // the auth controller, so we don't need doctrine/dbal to make the
            // original columns nullable.
            $table->string('device_id')->nullable()->unique()->after('id');
            $table->string('referral_code', 12)->nullable()->unique()->after('device_id');
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete()->after('referral_code');

            $table->unsignedBigInteger('coins')->default(0)->after('referred_by');
            $table->integer('cash_cents')->default(0)->after('coins');
            $table->unsignedInteger('gems')->default(0)->after('cash_cents');

            $table->unsignedSmallInteger('daily_streak')->default(0)->after('gems');
            $table->date('last_daily_claim_on')->nullable()->after('daily_streak');
            $table->unsignedBigInteger('total_withdrawn_cents')->default(0)->after('last_daily_claim_on');

            $table->string('status', 20)->default('active')->after('total_withdrawn_cents'); // active|flagged|banned
            $table->timestamp('banned_at')->nullable()->after('status');
            $table->json('meta')->nullable()->after('banned_at');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn([
                'device_id', 'referral_code', 'coins', 'cash_cents', 'gems',
                'daily_streak', 'last_daily_claim_on', 'total_withdrawn_cents',
                'status', 'banned_at', 'meta',
            ]);
        });
    }
};
