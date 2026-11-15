<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_xp_events', function (Blueprint $table) {
            // Longest planned key: "vetting_feedback:<20-digit id>" = 37 chars.
            $table->string('context', 64)->nullable()->after('xp_amount');

            // NULLs are distinct in a unique index (MariaDB, SQLite), so awards without a context are unaffected.
            $table->unique(['user_id', 'xp_event_definition_id', 'context'], 'user_xp_events_context_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_xp_events', function (Blueprint $table) {
            $table->dropUnique('user_xp_events_context_unique');
            $table->dropColumn('context');
        });
    }
};
