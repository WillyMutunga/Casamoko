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
        Schema::table('client_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('client_accounts', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('name');
            }
            if (!Schema::hasColumn('client_accounts', 'cost_per_sms')) {
                $table->decimal('cost_per_sms', 8, 4)->default(1.5000)->after('credit_limit');
            }
            if (!Schema::hasColumn('client_accounts', 'default_sender_id')) {
                $table->string('default_sender_id')->nullable()->after('cost_per_sms');
            }
            if (!Schema::hasColumn('client_accounts', 'low_balance_threshold')) {
                $table->decimal('low_balance_threshold', 10, 4)->default(100.0000)->after('default_sender_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_accounts', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'cost_per_sms', 'default_sender_id', 'low_balance_threshold']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number']);
        });
    }
};
