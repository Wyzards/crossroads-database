<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pipeline v1 cleanup: `type` became format + tags and `description` became section 1 (content
 * model, 2026-11-08); nothing reads or writes them since. Down re-adds empty columns only; the
 * data is in ~/db-backup-20261108-step3-premigrate.sql.gz on the EC2 host.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['type', 'description']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('type')->nullable();
            $table->text('description')->nullable();
        });
    }
};
