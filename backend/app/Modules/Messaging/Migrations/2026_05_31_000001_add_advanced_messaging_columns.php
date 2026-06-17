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
        // 1. Extend campaigns table
        Schema::table('campaigns', function (Blueprint $table) {
            $table->integer('tps_limit')->default(50);
            $table->string('quiet_hours_start')->nullable();
            $table->string('quiet_hours_end')->nullable();
            $table->string('recurring_type')->default('ONCE'); // ONCE, DAILY, WEEKLY, MONTHLY
            $table->string('approval_status')->default('APPROVED'); // PENDING_APPROVAL, APPROVED, REJECTED
            $table->boolean('is_ab_test')->default(false);
            $table->text('template_b')->nullable();
            $table->integer('ab_split_ratio')->default(50);
        });

        // 2. Extend keywords table
        Schema::table('keywords', function (Blueprint $table) {
            $table->text('reply_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'tps_limit',
                'quiet_hours_start',
                'quiet_hours_end',
                'recurring_type',
                'approval_status',
                'is_ab_test',
                'template_b',
                'ab_split_ratio'
            ]);
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->dropColumn('reply_message');
        });
    }
};
