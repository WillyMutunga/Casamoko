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
        Schema::create('shortcode_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shortcode_id');
            $table->unsignedBigInteger('client_account_id');
            $table->string('msisdn_hash')->index();
            $table->string('msisdn');
            $table->string('webhook_url')->nullable(); // Where to send subsequent session replies
            $table->string('current_state')->default('START');
            $table->json('context_data')->nullable(); // Store JSON state data like collected names, IDs
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('shortcode_id')->references('id')->on('shortcodes')->onDelete('cascade');
            $table->foreign('client_account_id')->references('id')->on('client_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shortcode_sessions');
    }
};
