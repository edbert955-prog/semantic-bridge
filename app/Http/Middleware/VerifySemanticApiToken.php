<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySemanticApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('semantic.api_token', '');
        $provided = (string) ($request->bearerToken() ?: $request->header('X-Semantic-Token', ''));

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Token integrasi tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}
