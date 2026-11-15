<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thursday poll (design step 4): the frozen candidate list, the poll's post and message ids, and the
 * frozen poll result. Written once each, like the other decisions of a cycle.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vetting_cycles', function (Blueprint $table) {
            $table->json('candidates')->nullable();      // [{project_id, source: queue|hunt, waiting_since, answer_id}], poll order
            $table->string('poll_thread_id', 30)->nullable();
            $table->string('poll_message_id', 30)->nullable();
            $table->json('poll_results')->nullable();    // [{project_id, votes, voter_ids}]
        });
    }

    public function down(): void
    {
        Schema::table('vetting_cycles', function (Blueprint $table) {
            $table->dropColumn(['candidates', 'poll_thread_id', 'poll_message_id', 'poll_results']);
        });
    }
};
