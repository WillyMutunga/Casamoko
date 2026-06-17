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
        // 1. Carriers Table
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // SAFARICOM, AIRTEL, TELKOM, etc.
            $table->json('binding_details')->nullable();
            $table->timestamps();
        });

        // 2. Carrier Routes Table
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('carrier_id');
            $table->decimal('cost_per_sms', 15, 4)->default(0.0000);
            $table->integer('tps_limit')->default(100); // Throughput throttle limit
            $table->integer('priority')->default(1); // Higher is prioritized
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('carrier_id')
                  ->references('id')
                  ->on('carriers')
                  ->onDelete('cascade');
        });

        // 3. Pricing Rules (Tariff Table)
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('destination_prefix'); // e.g. "2547" or "254" for Kenya
            $table->decimal('base_cost', 15, 4)->default(0.0000);
            $table->decimal('reseller_markup', 15, 4)->default(0.0000);
            $table->unsignedBigInteger('client_account_id')->nullable(); // Null means system-wide rule
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('set null');
        });

        // 4. Sender IDs Table
        Schema::create('sender_ids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id');
            $table->string('sender_id', 11); // max 11 chars
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });

        // 5. Shortcodes Table
        Schema::create('shortcodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id')->nullable(); // Null means shared
            $table->string('shortcode'); // e.g. "22344"
            $table->boolean('is_dedicated')->default(false);
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('set null');
        });

        // 6. Keywords Table
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shortcode_id');
            $table->string('keyword'); // e.g. "JOIN" or "STOP"
            $table->string('callback_webhook')->nullable();
            $table->string('action_type')->default('WEBHOOK'); // OPT_IN, OPT_OUT, WEBHOOK
            $table->timestamps();

            $table->foreign('shortcode_id')
                  ->references('id')
                  ->on('shortcodes')
                  ->onDelete('cascade');
        });

        // 7. Opt-Out Registry Table (Immediate Programmatic Campaign Blocklist)
        Schema::create('opt_out_registries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id');
            $table->string('msisdn'); // phone number
            $table->string('msisdn_hash')->index(); // Salted SHA-256 for fast lookup
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });

        // 8. Campaigns Table
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id');
            $table->unsignedBigInteger('sender_id_id')->nullable();
            $table->string('name');
            $table->text('template');
            $table->string('unicode_type')->default('GSM-7'); // GSM-7, UCS-2
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('status')->default('DRAFT'); // DRAFT, SCHEDULED, PROCESSING, COMPLETED, PAUSED
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');

            $table->foreign('sender_id_id')
                  ->references('id')
                  ->on('sender_ids')
                  ->onDelete('set null');
        });

        // 9. Message Records (Transactional dispatch log)
        Schema::create('message_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->nullable(); // nullable for quick sends
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('msisdn_hash')->index(); // Salted SHA-256 MSISDN
            $table->unsignedBigInteger('route_id')->nullable();
            $table->decimal('price', 15, 4)->default(0.0000);
            $table->string('status')->default('QUEUED'); // QUEUED, SENT, DELIVERED, FAILED
            $table->string('mno_message_id')->nullable(); // Network operator ID
            $table->string('network_status_code')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')
                  ->references('id')
                  ->on('campaigns')
                  ->onDelete('cascade');

            $table->foreign('route_id')
                  ->references('id')
                  ->on('routes')
                  ->onDelete('set null');
        });

        // 10. Incoming Messages Table (Mobile Originated - MO)
        Schema::create('incoming_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shortcode_id');
            $table->unsignedBigInteger('keyword_id')->nullable();
            $table->string('msisdn');
            $table->string('msisdn_hash');
            $table->text('message');
            $table->timestamps();

            $table->foreign('shortcode_id')
                  ->references('id')
                  ->on('shortcodes')
                  ->onDelete('cascade');

            $table->foreign('keyword_id')
                  ->references('id')
                  ->on('keywords')
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
        Schema::dropIfExists('incoming_messages');
        Schema::dropIfExists('message_records');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('opt_out_registries');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('shortcodes');
        Schema::dropIfExists('sender_ids');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('carriers');
    }
};
