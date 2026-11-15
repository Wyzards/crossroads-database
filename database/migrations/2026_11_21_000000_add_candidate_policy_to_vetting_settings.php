<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who can be a vetting candidate (Ryan, 2026-11-15: "Leave project hunts out of the poll for now until I redo
 * that system. So ATM just 3 from vetting queue"). hunt_in_poll: whether the top Hunt project may be a
 * candidate (default off). max_candidates: the poll's option cap (default 3; Discord allows 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vetting_settings', function (Blueprint $table) {
            $table->boolean('hunt_in_poll')->default(false)->after('enabled_at');
            $table->unsignedTinyInteger('max_candidates')->default(3)->after('hunt_in_poll');
        });
    }

    public function down(): void
    {
        Schema::table('vetting_settings', function (Blueprint $table) {
            $table->dropColumn(['hunt_in_poll', 'max_candidates']);
        });
    }
};
