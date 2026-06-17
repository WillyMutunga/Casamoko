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
        // 1. Contact Lists Table
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('subscription_source')->nullable();
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });

        // 2. Contacts Table
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_account_id');
            $table->string('msisdn'); // E.164 format, e.g., 254712345678
            $table->string('name')->nullable();
            $table->json('metadata')->nullable(); // JSON fields for template customization variables
            $table->string('msisdn_hash')->index(); // Salted SHA-256 hash for privacy enforcement
            $table->timestamps();

            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });

        // 3. Contact List Members Pivot Table (Many-to-Many relationship)
        Schema::create('contact_list_members', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_list_id');
            $table->unsignedBigInteger('contact_id');

            $table->primary(['contact_list_id', 'contact_id']);

            $table->foreign('contact_list_id')
                  ->references('id')
                  ->on('contact_lists')
                  ->onDelete('cascade');

            $table->foreign('contact_id')
                  ->references('id')
                  ->on('contacts')
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
        Schema::dropIfExists('contact_list_members');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_lists');
    }
};
