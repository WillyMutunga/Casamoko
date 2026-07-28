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
        Schema::table('incoming_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('incoming_messages', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_read');
            }
            if (!Schema::hasColumn('incoming_messages', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false)->after('is_archived');
            }
        });

        Schema::table('message_records', function (Blueprint $table) {
            if (!Schema::hasColumn('message_records', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('status');
            }
            if (!Schema::hasColumn('message_records', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false)->after('is_archived');
            }
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
            if (Schema::hasColumn('incoming_messages', 'is_archived')) {
                $table->dropColumn('is_archived');
            }
            if (Schema::hasColumn('incoming_messages', 'is_deleted')) {
                $table->dropColumn('is_deleted');
            }
        });

        Schema::table('message_records', function (Blueprint $table) {
            if (Schema::hasColumn('message_records', 'is_archived')) {
                $table->dropColumn('is_archived');
            }
            if (Schema::hasColumn('message_records', 'is_deleted')) {
                $table->dropColumn('is_deleted');
            }
        });
    }
};
