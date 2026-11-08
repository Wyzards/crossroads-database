<?php

namespace App\Http\Controllers;

use App\Models\SystemChannel;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public const CHANNELS = [
        'achievements_channel_id',
        'intake_channel_id',
        'intake_review_forum_id',
        'architects_channel_id',
        'hunt_suggestions_channel_id',
    ];

    public function getChannels()
    {
        return response()->json(SystemChannel::first() ?? (object) array_fill_keys(self::CHANNELS, null));
    }

    /** Partial update: only the channels given change. */
    public function setChannels(Request $request)
    {
        $data = $request->validate(array_fill_keys(self::CHANNELS, 'sometimes|nullable|string|max:30'));

        $channels = SystemChannel::first();
        if (!$channels)
            $channels = SystemChannel::create($data);
        else
            $channels->update($data);

        return response()->json($channels->fresh());
    }
}
