<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Open invite problems (see the invite_checks migration). The bot decides; this only stores. */
class InviteCheckController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('invite_checks')->orderBy('id')->get());
    }

    /** Records a problem (first_seen_at stays as it was if the row exists). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'url' => 'required|string|max:500',
            'status' => 'required|in:bad,warning',
            'reason' => 'required|string|max:255',
        ]);
        $existing = DB::table('invite_checks')->where(['project_id' => $data['project_id'], 'url' => $data['url']])->first();
        if ($existing) {
            DB::table('invite_checks')->where('id', $existing->id)->update(['status' => $data['status'], 'reason' => $data['reason'], 'updated_at' => now()]);
        } else {
            DB::table('invite_checks')->insert($data + ['first_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        return response()->json(DB::table('invite_checks')->where(['project_id' => $data['project_id'], 'url' => $data['url']])->first(), $existing ? 200 : 201);
    }

    public function destroy(int $id)
    {
        DB::table('invite_checks')->where('id', $id)->delete();
        return response()->noContent();
    }
}
