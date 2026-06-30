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
        Schema::table('keywords', function (Blueprint $table) {
            // Nullable because existing shared shortcode keywords will need to be manually assigned 
            // or they are dedicated shortcode keywords and can be safely assigned.
            $table->unsignedBigInteger('client_account_id')->nullable()->after('shortcode_id');
            $table->foreign('client_account_id')
                  ->references('id')
                  ->on('client_accounts')
                  ->onDelete('cascade');
        });

        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('client_account_id')->nullable()->after('keyword_id');
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
        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->dropForeign(['client_account_id']);
            $table->dropColumn('client_account_id');
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->dropForeign(['client_account_id']);
            $table->dropColumn('client_account_id');
        });
    }
};
