<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CrossroadsUser;
use App\Models\UserSubscription;

class SubscriptionController extends Controller
{
    public function updateSubscription(Request $request, CrossroadsUser $user)
    {
        $subscription = UserSubscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'started_at' => $request->input('started_at', now()),
                'total_months' => $request->input('total_months', 0),
                'current_streak' => $request->input('current_streak', 0),
                'is_active' => $request->input('is_active', true),
            ]
        );

        return response()->json($subscription);
    }
}
