<?php

namespace App\Http\Middleware;

use App\Services\ReminderSchedulerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware TriggerDueReminders
 *
 * Menjalankan pemeriksaan ringan berjeda cache terhadap jadwal reminder yang sudah jatuh tempo
 * saat pengguna terautentikasi mengakses aplikasi, sehingga pengingat berkala tetap bekerja
 * secara otomatis walaupun daemon cron tidak berjalan di lingkungan pengembangan lokal.
 */
class TriggerDueReminders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! app()->runningUnitTests()) {
            app(ReminderSchedulerService::class)->runIfDueCached();
        }

        return $next($request);
    }
}
