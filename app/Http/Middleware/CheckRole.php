<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (! in_array($request->user()->role, $roles, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: role tidak memiliki akses',
            ], 403);
        }

        return $next($request);
    }
}
