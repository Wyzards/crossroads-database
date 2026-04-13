<?php

namespace App\Http\Controllers;

use App\Models\ProjectList;
use Illuminate\Http\Request;

class ProjectListsController extends Controller
{
    public function index()
    {
        return ProjectList::all(); // minimal, just the metadata
    }

    public function show(ProjectList $list)
    {
        return response()->json($list);
    }
}
