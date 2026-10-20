<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SemanticMapping;
use Illuminate\Http\Request;

class SemanticMappingController extends Controller
{
    public function index(Request $request)
    {
        $query = SemanticMapping::query()->where('is_active', true);

        foreach (['source_system', 'target_system', 'source_table'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->toString());
            }
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('source_system')
                ->orderBy('source_table')
                ->orderBy('source_column')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
