<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;

class BotUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'bot@local'],
            [
                'name' => 'Discord Bot',
                'password' => bcrypt(Str::random(32)), // Random password, bot only uses API token
            ]
        );

        // Create a personal access token
        $token = $user->createToken('discord-bot-token')->plainTextToken;

        // Optionally print it for copy-pasting into .env or your bot
        echo "\n\nBot API Token: {$token}\n\n";
    }
}
