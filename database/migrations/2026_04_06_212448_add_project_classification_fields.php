<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('architect_approval')->default('hidden')->after('status');
            $table->string('community_vetted')->default('unvetted')->after('architect_approval');
            $table->string('project_stage')->default('in_development')->after('community_vetted');
            $table->string('accessibility')->default('closed')->after('project_stage');
        });

        // 👇 DATA MIGRATION HAPPENS HERE
        DB::table('projects')->orderBy('id')->chunk(100, function ($projects) {
            foreach ($projects as $project)
            {

                $updates = [
                    'architect_approval' => 'hidden',
                    'community_vetted' => 'unvetted',
                    'project_stage' => 'in_development',
                    'accessibility' => 'closed',
                ];

                switch ($project->status)
                {
                    case 'playable':
                        $updates['architect_approval'] = 'approved';
                        $updates['project_stage'] = 'released';
                        $updates['accessibility'] = 'public';
                        $updates['community_vetted'] = 'skipped';
                        break;

                    case 'in_development':
                        $updates['architect_approval'] = 'approved';
                        $updates['project_stage'] = 'in_development';
                        break;

                    case 'archived':
                        $updates['project_stage'] = 'closed';
                        break;

                    case 'hidden':
                        break;
                }

                DB::table('projects')
                    ->where('id', $project->id)
                    ->update($updates);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
