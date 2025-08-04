<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\CrossroadsUser;

class CrossroadsUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create 10 dummy users
        for ($i = 0; $i < 10; $i++)
        {
            CrossroadsUser::create([
                'minecraft_uuid' => (string) Str::uuid(),
                'discord_id' => (string) rand(100000000000000000, 999999999999999999), // 18-digit Discord snowflake style
            ]);
        }
    }
}
