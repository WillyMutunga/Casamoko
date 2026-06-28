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
        Schema::table('shortcodes', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false)->after('is_dedicated');
            $table->decimal('premium_rate', 15, 4)->default(3.0000)->after('is_premium');
            $table->boolean('charge_on_mo')->default(false)->after('premium_rate');
        });

        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->string('link_id')->nullable()->after('message');
        });

        Schema::table('message_records', function (Blueprint $table) {
            $table->string('link_id')->nullable()->after('mno_message_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shortcodes', function (Blueprint $table) {
            $table->dropColumn(['is_premium', 'premium_rate', 'charge_on_mo']);
        });

        Schema::table('incoming_messages', function (Blueprint $table) {
            $table->dropColumn('link_id');
        });

        Schema::table('message_records', function (Blueprint $table) {
            $table->dropColumn('link_id');
        });
    }
};
