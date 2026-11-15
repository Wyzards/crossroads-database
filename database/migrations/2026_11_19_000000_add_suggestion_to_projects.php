<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hunt suggestions (design step 5): who suggested a Hunt project (for the XP when it is selected or
 * passes) and the #project-hunt-suggestions post it came from (unique: one approval per post).
 * Set by POST /hunt-suggestions only; not accepted by ProjectController.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('suggested_by_user_id')->nullable()->constrained('crossroads_users')->nullOnDelete();
            $table->string('suggestion_thread_id', 30)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_by_user_id');
            $table->dropColumn('suggestion_thread_id');
        });
    }
};
