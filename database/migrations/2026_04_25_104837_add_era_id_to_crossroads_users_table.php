<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crossroads_users', function (Blueprint $table) {
            $table->foreignId('era_id')
                ->nullable()
                ->after('discord_id')
                ->constrained('eras')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crossroads_users', function (Blueprint $table) {
            $table->dropForeign(['era_id']);
            $table->dropColumn('era_id');
        });
    }
};
