<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds projects.tier and moves the old workaround (a ⭐/🔥 prefix in the project name) into it:
 * the emoji is stripped from the name and its tier set. Names without one stay 'normal'.
 */
return new class extends Migration {
    private const PREFIXES = ['⭐' => 'star', '🔥' => 'flame'];

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('tier')->default('normal')->after('accessibility');
        });

        foreach (DB::table('projects')->get(['id', 'name']) as $project) {
            foreach (self::PREFIXES as $emoji => $tier) {
                if (!str_starts_with($project->name, $emoji))
                    continue;

                $name = trim(mb_substr($project->name, mb_strlen($emoji)));
                $taken = DB::table('projects')->where('name', $name)->where('id', '!=', $project->id)->exists();

                DB::table('projects')->where('id', $project->id)->update(
                    $taken ? ['tier' => $tier] : ['tier' => $tier, 'name' => $name]
                );
                break;
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('projects')->whereIn('tier', array_values(self::PREFIXES))->get(['id', 'name', 'tier']) as $project) {
            $emoji = array_search($project->tier, self::PREFIXES);
            DB::table('projects')->where('id', $project->id)->update(['name' => $emoji . $project->name]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
