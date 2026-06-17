<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Extend Carrier Routes table
        Schema::table('routes', function (Blueprint $table) {
            $table->string('destination_network')->nullable()->after('carrier_id'); // SAFARICOM, AIRTEL, TELKOM
            $table->string('bind_type')->default('TRANSCEIVER')->after('destination_network'); // TRANSCEIVER, TRANSMITTER, RECEIVER
            $table->integer('window_size')->default(10)->after('bind_type');
            $table->boolean('use_tls')->default(false)->after('window_size');
            $table->decimal('delivery_rate_score', 5, 2)->default(100.00)->after('use_tls'); // real-time DLR quality
            $table->integer('latency_score')->default(150)->after('delivery_rate_score'); // milliseconds
        });

        // 2. Extend Client Accounts table
        Schema::table('client_accounts', function (Blueprint $table) {
            $table->string('billing_type')->default('PREPAID')->after('status'); // PREPAID, POSTPAID
            $table->string('currency')->default('KES')->after('billing_type'); // KES, USD
            $table->decimal('low_balance_threshold', 15, 4)->default(10.0000)->after('currency');
        });

        // 3. Extend Wallet Transactions table
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('reason_code')->nullable()->after('reference_id'); // REFUND_CORRECTION, MANUAL_CREDIT, MANUAL_DEBIT, TOPUP, BILLING_ADJUSTMENT
            $table->unsignedBigInteger('adjusted_by_user_id')->nullable()->after('reason_code');

            $table->foreign('adjusted_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['adjusted_by_user_id']);
            $table->dropColumn(['reason_code', 'adjusted_by_user_id']);
        });

        Schema::table('client_accounts', function (Blueprint $table) {
            $table->dropColumn(['billing_type', 'currency', 'low_balance_threshold']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn([
                'destination_network',
                'bind_type',
                'window_size',
                'use_tls',
                'delivery_rate_score',
                'latency_score'
            ]);
        });
    }
};
