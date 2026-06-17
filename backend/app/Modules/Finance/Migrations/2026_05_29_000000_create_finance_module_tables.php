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
        // 1. Wallet Transactions Table (Double Entry Snapshot Ledger)
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id');
            $table->decimal('amount', 15, 4); // positive for credits, negative for debits
            $table->decimal('balance_after', 15, 4); // snapshot of balance after this operation
            $table->string('type'); // TOPUP, SMS_DISPATCH, REFUND
            $table->string('reference_type')->nullable(); // Campaign, MpesaPayment, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });

        // 2. Invoices Table
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id');
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 15, 4);
            $table->string('status')->default('UNPAID'); // PAID, UNPAID, CANCELLED
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->json('breakdown_metadata')->nullable();
            $table->string('download_token')->nullable();
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });

        // 3. Mpesa Payments (Safaricom Daraja API callbacks)
        Schema::create('mpesa_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id');
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->unique();
            $table->string('mpesa_receipt_number')->nullable()->unique();
            $table->decimal('amount', 15, 4);
            $table->string('phone_number');
            $table->string('status')->default('PENDING'); // PENDING, SUCCESS, FAILED
            $table->json('response_metadata')->nullable();
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mpesa_payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('wallet_transactions');
    }
};
