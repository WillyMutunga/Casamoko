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
        // 1. Reseller Accounts Table
        Schema::create('reseller_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('markup_percentage', 5, 2)->default(0.00);
            $table->decimal('api_credits', 15, 4)->default(0.0000);
            $table->json('billing_config')->nullable();
            $table->timestamps();
        });

        // 2. Client Accounts Table
        Schema::create('client_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_account_id')->nullable();
            $table->string('name');
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, SUSPENDED
            $table->decimal('wallet_balance', 15, 4)->default(0.0000);
            $table->decimal('credit_limit', 15, 4)->default(0.0000);
            $table->json('kyc_metadata')->nullable();
            $table->timestamps();

            $table->foreign('reseller_account_id')
                  ->references('id')
                  ->on('reseller_accounts')
                  ->onDelete('set null');
        });

        // 3. Users Table (Extending default users with roles and lifecycle properties)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role_tier')->default('CLIENT'); // SUPER_ADMIN, RESELLER, CLIENT
            $table->string('sub_role')->nullable(); // CLIENT_ADMIN, CAMPAIGN_MANAGER, ANALYST, FINANCE_OFFICER
            $table->string('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('password_expires_at')->nullable();
            $table->boolean('is_active_user')->default(true);
            $table->string('suspension_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });

        // 4. Login Attempts (Immutable Audit Log)
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->string('username');
            $table->string('status'); // SUCCESS, FAILED
            $table->string('failure_reason')->nullable(); // INVALID_CREDENTIALS, BRUTE_FORCE, SUSPENDED
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('client_accounts');
        Schema::dropIfExists('reseller_accounts');
    }
};
