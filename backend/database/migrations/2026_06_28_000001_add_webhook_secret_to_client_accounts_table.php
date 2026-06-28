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
        Schema::table('client_accounts', function (Blueprint $table) {
            $table->string('webhook_secret')->nullable()->after('api_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('client_accounts', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
