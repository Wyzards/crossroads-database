<?php

namespace Database\Factories;

use App\Models\ProjectAttachment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectAttachmentFactory extends Factory
{
    protected $model = ProjectAttachment::class;

    public function definition(): array
    {
        $filename = $this->faker->lexify('attachment_??????') . '.pdf';
        $path = 'attachments/' . $filename;

        return [
            'project_id' => Project::factory(),
            'path' => $path,
        ];
    }
}
