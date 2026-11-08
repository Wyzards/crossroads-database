<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

/** The curated gameplay tag list. Featured tags are the forum tag set (Discord: 20 per forum). */
class TagController extends Controller
{
    // Leaves forum room for a list's own tags (stage, format) within Discord's 20.
    public const MAX_FEATURED = 15;

    public function index()
    {
        return response()->json(Tag::withCount('projects')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:20|unique:tags,name',
            'featured' => 'sometimes|boolean',
        ]);

        if (($data['featured'] ?? false) && Tag::where('featured', true)->count() >= self::MAX_FEATURED)
            return response()->json(['message' => 'At most ' . self::MAX_FEATURED . ' tags can be featured'], 422);

        return response()->json(Tag::create($data), 201);
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:20|unique:tags,name,' . $tag->id,
            'featured' => 'sometimes|boolean',
        ]);

        if (($data['featured'] ?? false) && !$tag->featured && Tag::where('featured', true)->count() >= self::MAX_FEATURED)
            return response()->json(['message' => 'At most ' . self::MAX_FEATURED . ' tags can be featured'], 422);

        $tag->update($data);
        return response()->json($tag);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();
        return response()->noContent();
    }
}
