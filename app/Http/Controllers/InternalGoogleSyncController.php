<?php

namespace App\Http\Controllers;

use App\Models\InternalGoogleSyncRun;
use Illuminate\Http\Request;

class InternalGoogleSyncController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $source = trim((string) $request->query('source', ''));

        $query = InternalGoogleSyncRun::query()
            ->withCount('errors')
            ->orderByDesc('id');

        if ($source !== '') {
            $query->where('source', $source);
        }

        return response()->json([
            'data' => $query->limit($limit)->get(),
        ]);
    }

    public function show(InternalGoogleSyncRun $run)
    {
        return response()->json([
            'data' => $run->load(['errors' => function ($query) {
                $query->orderBy('source_row')->orderBy('id');
            }]),
        ]);
    }
}
