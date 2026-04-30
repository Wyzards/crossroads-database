<?php

namespace App\Http\Controllers;

use App\Models\SystemChannel;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function getChannels()
    {
        return response()->json(SystemChannel::first());
    }

    public function setChannels(Request $request)
    {
        $channels = SystemChannel::first();

        if (!$channels)
        {
            $channels = SystemChannel::create([
                'achievements_channel_id' => $request->input('achievements_channel_id'),
            ]);
        } else
        {
            $channels->update([
                'achievements_channel_id' => $request->input('achievements_channel_id'),
            ]);

            // TODO: Allow partials
        }

        return response()->json($channels);
    }
}
