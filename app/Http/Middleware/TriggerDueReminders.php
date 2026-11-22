<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis middleware TriggerDueReminders ini untuk memicu pemrosesan reminder berkala berjeda cache saat pengguna aktif mengakses web.
 */

namespace App\Http\Middleware;

use App\Services\ReminderSchedulerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Pemicu Reminder Jatuh Tempo (TriggerDueReminders)
 *
 * menambahkan middleware ringan ini agar pengingat H-2 dan kontrak termin tetap terpicu otomatis
 * di lingkungan lokal/server yang belum menjalankan daemon cron `schedule:run`.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance middleware yang diproses oleh pipeline HTTP web.
 *
 * Contoh pemanggilan:
 * `$middleware->appendToGroup('web', TriggerDueReminders::class);`
 */
class TriggerDueReminders
{
    /**
     * memicu pengecekan jadwal pengingat berjeda cache sebelum meneruskan request ke controller.
     *
     * @param  Request  $request  menerima request HTTP masuk tanpa memodifikasinya.
     * @param  Closure(Request): (Response)  $next  selalu meneruskan request ke handler berikutnya.
     * @return Response mengembalikan respons HTTP dari lapisan pipeline selanjutnya.
     *
     * Contoh pemanggilan:
     * `$middleware->handle($request, $next);`
     */
    public function handle(Request $request, Closure $next): Response
    {
        // melewati eksekusi saat unit test berjalan agar test fitur yang tidak menguji reminder tidak mendapat side-effect query tambahan.
        if (Auth::check() && ! app()->runningUnitTests()) {
            app(ReminderSchedulerService::class)->runIfDueCached();
        }

        return $next($request);
    }
}
