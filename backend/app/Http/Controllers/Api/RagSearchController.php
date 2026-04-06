<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RagMatchingService;
use Illuminate\Http\Request;

class RagSearchController extends Controller
{
    public function __invoke(Request $request, RagMatchingService $ragService)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:3|max:200',
        ]);

        try {
            $results = $ragService->executeSemanticSearch($validated['query']);

            return response()->json([
                'success' => true,
                'data'    => $results['products'],
                'message' => $results['synthesis'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Neural link severed. Search temporarily unavailable.',
            ], 500);
        }
    }
}
