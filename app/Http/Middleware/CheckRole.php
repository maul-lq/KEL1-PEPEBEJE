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
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated: Silakan login terlebih dahulu.',
                ], 401);
            }

            return redirect()->route('login');
        }

        if (! in_array($request->user()->role, $roles, true)) {
            $userRole = strtoupper(str_replace('_', ' ', $request->user()->role));

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Akses Ditolak: Peran [{$userRole}] tidak memiliki wewenang untuk mengakses fungsi ini (FR 2 & NFR RBAC).",
                ], 403);
            }

            abort(403, "Akses Ditolak: Peran [{$userRole}] tidak memiliki wewenang untuk mengakses halaman ini (FR 2 & NFR RBAC).");
        }

        return $next($request);
    }
}
