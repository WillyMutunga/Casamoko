<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Alter sender_ids table to support up to 30 characters for numeric long-codes
        // We use raw SQL to prevent Doctrine DBAL version conflicts during column alteration
        DB::statement('ALTER TABLE sender_ids MODIFY sender_id VARCHAR(30)');

        // Add fallback sender ID reference
        Schema::table('sender_ids', function (Blueprint $table) {
            $table->unsignedBigInteger('fallback_sender_id_id')->nullable()->after('status');
            $table->foreign('fallback_sender_id_id')
                  ->references('id')
                  ->on('sender_ids')
                  ->onDelete('set null');
        });

        // 2. Create MNO-specific approvals matrix table
        Schema::create('sender_id_mno_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id_id');
            $table->string('mno_name'); // SAFARICOM, AIRTEL, TELKOM
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('sender_id_id')
                  ->references('id')
                  ->on('sender_ids')
                  ->onDelete('cascade');
        });

        // 3. Create platform-level blacklist table for regulatory protection
        Schema::create('sender_id_blacklists', function (Blueprint $table) {
            $table->id();
            $table->string('pattern')->unique();
            $table->string('reason');
            $table->timestamps();
        });

        // 4. Seed regulatory protected keywords
        DB::table('sender_id_blacklists')->insert([
            [
                'pattern' => 'SAFARICOM',
                'reason' => 'Protected local mobile network operator brand name.',
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'pattern' => 'M-PESA',
                'reason' => 'Protected sovereign mobile money utility brand name.',
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'pattern' => 'POLICE',
                'reason' => 'Protected emergency and state security agency identity.',
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'pattern' => 'COKE',
                'reason' => 'Protected commercial global trade trademark.',
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'pattern' => 'AIRTEL',
                'reason' => 'Protected channel telecom operator brand name.',
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'pattern' => 'TELKOM',
                'reason' => 'Protected channel telecom operator brand name.',
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'pattern' => 'EQUITY',
                'reason' => 'Protected banking service and financial utility name.',
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'pattern' => 'KCB',
                'reason' => 'Protected banking service and financial utility name.',
                'created_at' => now(), 'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sender_id_blacklists');
        Schema::dropIfExists('sender_id_mno_approvals');

        Schema::table('sender_ids', function (Blueprint $table) {
            $table->dropForeign(['fallback_sender_id_id']);
            $table->dropColumn('fallback_sender_id_id');
        });

        DB::statement('ALTER TABLE sender_ids MODIFY sender_id VARCHAR(11)');
    }
};
