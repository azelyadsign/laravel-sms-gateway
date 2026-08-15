<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            // MySQL uses the unique index to back the user_id foreign key,
            // so the FK must be dropped before the index can be removed.
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            // The plain index may not exist on databases that ran an
            // earlier version of this migration.
            if (Schema::hasIndex('device_tokens', 'device_tokens_user_id_index')) {
                $table->dropIndex(['user_id']);
            }

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
