<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where a map is downloaded (maps have no address to join). Backfill: `php artisan maps:backfill-download`. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('download_url', 500)->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn(Blueprint $t) => $t->dropColumn('download_url'));
    }
};
