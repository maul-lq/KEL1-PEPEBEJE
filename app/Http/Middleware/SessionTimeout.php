<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    /**
     * Handle an incoming request.
     * Mengakhiri sesi otomatis saat pengguna idle selama 15 menit (NFR Keamanan Sesi).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = $request->session()->get('last_activity');
            $timeoutSeconds = (int) config('session.lifetime', 15) * 60;

            if ($lastActivity && (time() - $lastActivity) > $timeoutSeconds) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $timeoutMessage = 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit. Silakan login kembali.';

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $timeoutMessage,
                    ], 401);
                }

                return redirect()->route('login')->withErrors([
                    'login' => $timeoutMessage,
                ]);
            }

            $request->session()->put('last_activity', time());
        }

        return $next($request);
    }
}
