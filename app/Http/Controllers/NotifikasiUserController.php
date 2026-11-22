<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller NotifikasiUserController ini untuk mengelola feed kotak masuk notifikasi personal, tab jadwal pengingat berkala, penandaan baca/dismiss, serta endpoint JSON polling real-time.
 */

namespace App\Http\Controllers;

use App\Models\NotifikasiUser;
use App\Models\Reminder;
use App\Models\User;
use App\Services\PengadaanNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controller Feed Notifikasi & Jadwal Pengingat (NotifikasiUserController)
 *
 * memisahkan penyajian kotak masuk notifikasi per pengguna dari jadwal pengingat aktif (tab `jadwal`)
 * dan menyediakan API polling ringan `/api/notifikasi-terbaru` untuk pembaruan lonceng serta toast OS Desktop.
 *
 * Parameter konstruktor: `$notificationService` (opsional, disuntikkan melalui DI Laravel).
 * Nilai kembali         : Instance controller yang melayani grup rute `notifikasi.*`.
 *
 * Contoh pemanggilan:
 * `Route::get('/notifikasi', [NotifikasiUserController::class, 'notifikasiIndex'])->name('notifikasi.index');`
 */
class NotifikasiUserController extends Controller
{
    /**
     * menyuntikkan PengadaanNotificationService secara opsional agar pengujian atau pemanggilan tanpa container tetap aman.
     *
     * @param  PengadaanNotificationService|null  $notificationService  menerima layanan orkestrasi notifikasi pengadaan.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$controller = app(NotifikasiUserController::class);`
     */
    public function __construct(
        public ?PengadaanNotificationService $notificationService = null
    ) {}

    /**
     * menampilkan halaman daftar notifikasi atau jadwal reminder aktif dengan filter tab, kata kunci, kategori, dan rentang waktu.
     *
     * @param  Request  $request  membaca query `tab`, `q`, `jenis`, dan `periode` untuk menyaring daftar notifikasi/jadwal.
     * @return View mengembalikan view `notifikasi.index` lengkap dengan hitungan badge tiap tab.
     *
     * Contoh pemanggilan:
     * `GET /notifikasi?tab=belum_dibaca&jenis=pembayaran`
     */
    public function notifikasiIndex(Request $request): View
    {
        $user = Auth::user();

        $scheduledTypes = array_merge(Reminder::SCHEDULED_TYPES, ['bulanan_tahunan']);
        $jadwalBaseQuery = $this->buildJadwalQueryForUser($user);

        $tabCounts = [
            'semua' => NotifikasiUser::where('id_user', $user->id_user)->count(),
            'belum_dibaca' => NotifikasiUser::where('id_user', $user->id_user)->where('is_read', false)->count(),
            'perlu_tindakan' => NotifikasiUser::where('id_user', $user->id_user)
                ->whereHas('reminder', function ($q): void {
                    $q->where('tipe_reminder', 'verifikasi')
                        ->orWhere('judul_alert', 'like', '%revisi%')
                        ->orWhere('judul_alert', 'like', '%tindakan%')
                        ->orWhere('pesan_alert', 'like', '%tindak lanjut%')
                        ->orWhere('pesan_alert', 'like', '%revisi%');
                })->count(),
            'pengingat' => NotifikasiUser::where('id_user', $user->id_user)
                ->whereHas('reminder', function ($q) use ($scheduledTypes): void {
                    $q->whereIn('tipe_reminder', $scheduledTypes)
                        ->orWhere('judul_alert', 'like', '%pengingat%')
                        ->orWhere('judul_alert', 'like', '%jatuh tempo%');
                })->count(),
            'jadwal' => (clone $jadwalBaseQuery)->where('is_active', true)->count(),
        ];

        $currentTab = (string) $request->query('tab', 'semua');

        if ($currentTab === 'jadwal') {
            $jadwalQuery = clone $jadwalBaseQuery;

            if ($request->filled('q')) {
                $keyword = trim((string) $request->query('q'));
                $jadwalQuery->where(function ($q) use ($keyword): void {
                    $q->where('judul_alert', 'like', "%{$keyword}%")
                        ->orWhere('pesan_alert', 'like', "%{$keyword}%")
                        ->orWhere('nomor_paket', 'like', "%{$keyword}%")
                        ->orWhere('nomor_pembayaran', 'like', "%{$keyword}%")
                        ->orWhereHas('paket', function ($pq) use ($keyword): void {
                            $pq->where('nama_paket', 'like', "%{$keyword}%")
                                ->orWhere('nomor_surat', 'like', "%{$keyword}%");
                        });
                });
            }

            $jadwalList = $jadwalQuery
                ->orderByDesc('is_active')
                ->orderBy('waktu_trigger')
                ->paginate(10)
                ->withQueryString();

            // mengembalikan paginator kosong berstruktur valid (`1 = 0`) agar komponen Blade notifikasi tidak perlu memeriksa null pada metadata paginasi.
            $notifikasiList = NotifikasiUser::where('id_user', $user->id_user)->whereRaw('1 = 0')->paginate(10);

            return view('notifikasi.index', compact('notifikasiList', 'jadwalList', 'tabCounts', 'currentTab'));
        }

        $query = NotifikasiUser::where('id_user', $user->id_user)
            ->with(['reminder.paket.permohonan', 'reminder.paket.kontrak', 'reminder.paket.pembayaran']);

        if ($currentTab === 'belum_dibaca') {
            $query->where('is_read', false);
        } elseif ($currentTab === 'perlu_tindakan') {
            $query->whereHas('reminder', function ($q): void {
                $q->where('tipe_reminder', 'verifikasi')
                    ->orWhere('judul_alert', 'like', '%revisi%')
                    ->orWhere('judul_alert', 'like', '%tindakan%')
                    ->orWhere('pesan_alert', 'like', '%tindak lanjut%')
                    ->orWhere('pesan_alert', 'like', '%revisi%');
            });
        } elseif ($currentTab === 'pengingat') {
            $query->whereHas('reminder', function ($q) use ($scheduledTypes): void {
                $q->whereIn('tipe_reminder', $scheduledTypes)
                    ->orWhere('judul_alert', 'like', '%pengingat%')
                    ->orWhere('judul_alert', 'like', '%jatuh tempo%');
            });
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));
            $query->where(function ($q) use ($keyword): void {
                $q->whereHas('reminder', function ($rq) use ($keyword): void {
                    $rq->where('judul_alert', 'like', "%{$keyword}%")
                        ->orWhere('pesan_alert', 'like', "%{$keyword}%")
                        ->orWhere('nomor_paket', 'like', "%{$keyword}%")
                        ->orWhere('nomor_pembayaran', 'like', "%{$keyword}%")
                        ->orWhereHas('paket', function ($pq) use ($keyword): void {
                            $pq->where('nama_paket', 'like', "%{$keyword}%")
                                ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                                ->orWhereHas('permohonan', function ($prq) use ($keyword): void {
                                    $prq->where('judul_pengadaan', 'like', "%{$keyword}%");
                                });
                        });
                });
            });
        }

        if ($request->filled('jenis') && $request->query('jenis') !== 'semua') {
            $jenis = (string) $request->query('jenis');
            if ($jenis === 'pengingat') {
                $query->whereHas('reminder', function ($q) use ($scheduledTypes): void {
                    $q->whereIn('tipe_reminder', $scheduledTypes)
                        ->orWhere('judul_alert', 'like', '%pengingat%');
                });
            } elseif ($jenis === 'perlu_tindakan') {
                $query->whereHas('reminder', function ($q): void {
                    $q->where('tipe_reminder', 'verifikasi')
                        ->orWhere('judul_alert', 'like', '%revisi%')
                        ->orWhere('judul_alert', 'like', '%tindakan%')
                        ->orWhere('pesan_alert', 'like', '%tindak lanjut%');
                });
            } elseif ($jenis === 'verifikasi') {
                $query->whereHas('reminder', function ($q): void {
                    $q->where('tipe_reminder', 'verifikasi')
                        ->orWhere('judul_alert', 'like', '%verifikasi%')
                        ->orWhere('judul_alert', 'like', '%telaah%');
                });
            } elseif ($jenis === 'pembayaran') {
                $query->whereHas('reminder', function ($q): void {
                    $q->whereIn('tipe_reminder', [
                        Reminder::TIPE_PEMBAYARAN,
                        Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN_H2,
                        Reminder::TIPE_JATUH_TEMPO_PEMBAYARAN,
                    ])
                        ->orWhere('judul_alert', 'like', '%pembayaran%')
                        ->orWhere('judul_alert', 'like', '%jatuh tempo%')
                        ->orWhere('judul_alert', 'like', '%termin%')
                        ->orWhere('judul_alert', 'like', '%sp2d%');
                });
            } elseif ($jenis === 'pengiriman') {
                $query->whereHas('reminder', function ($q): void {
                    $q->where('tipe_reminder', 'pengiriman')
                        ->orWhere('judul_alert', 'like', '%pengiriman%');
                });
            } elseif ($jenis === 'spk') {
                $query->whereHas('reminder', function ($q): void {
                    $q->whereIn('tipe_reminder', ['spk', 'tembusan_spk'])
                        ->orWhere('judul_alert', 'like', '%spk%')
                        ->orWhere('judul_alert', 'like', '%kontrak%');
                });
            }
        }

        if ($request->filled('periode') && $request->query('periode') !== 'semua') {
            $periode = (string) $request->query('periode');
            if ($periode === 'hari_ini') {
                $query->whereDate('created_at', now()->today());
            } elseif ($periode === '7_hari') {
                $query->where('created_at', '>=', now()->subDays(7));
            } elseif ($periode === '30_hari') {
                $query->where('created_at', '>=', now()->subDays(30));
            } elseif ($periode === 'bulan_ini') {
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
            }
        }

        $notifikasiList = $query->latest('id')->paginate(10)->withQueryString();
        $jadwalList = null;

        return view('notifikasi.index', compact('notifikasiList', 'jadwalList', 'tabCounts', 'currentTab'));
    }

    /**
     * menyediakan metode alias `index` yang meneruskan langsung ke `notifikasiIndex`.
     *
     * @param  Request  $request  meneruskan objek request beserta query filter ke `notifikasiIndex`.
     * @return View mengembalikan view `notifikasi.index`.
     *
     * Contoh pemanggilan:
     * `$controller->index($request);`
     */
    public function index(Request $request): View
    {
        return $this->notifikasiIndex($request);
    }

    /**
     * menandai satu notifikasi milik pengguna aktif sebagai sudah dibaca beserta stempel waktunya.
     *
     * @param  Request  $request  menerima request POST dari tombol 'Tandai Dibaca'.
     * @param  int  $id  menyaring berdasarkan `id` sekaligus `id_user` agar pengguna tidak bisa menandai notifikasi milik orang lain.
     * @return RedirectResponse mengembalikan redirect kembali dengan pesan sukses.
     *
     * Contoh pemanggilan:
     * `POST /notifikasi/{id}/read`
     */
    public function markAsReadWeb(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        // selalu mengunci query ke id_user pengguna login supaya penandaan ID acak tidak memengaruhi kotak masuk user lain (anti IDOR).
        $notif = NotifikasiUser::where('id', $id)->where('id_user', $user->id_user)->firstOrFail();
        $notif->is_read = true;
        $notif->waktu_dibaca = now();
        $notif->save();

        return back()->with('success', 'Notifikasi berhasil ditandai sebagai dibaca.');
    }

    /**
     * menyediakan metode alias `markAsRead` yang meneruskan ke `markAsReadWeb`.
     *
     * @param  Request  $request  meneruskan objek request HTTP.
     * @param  int  $id  meneruskan ID notifikasi pengguna.
     * @return RedirectResponse mengembalikan redirect kembali.
     *
     * Contoh pemanggilan:
     * `$controller->markAsRead($request, 12);`
     */
    public function markAsRead(Request $request, int $id): RedirectResponse
    {
        return $this->markAsReadWeb($request, $id);
    }

    /**
     * menandai seluruh notifikasi belum terbaca milik pengguna aktif sebagai telah dibaca dalam satu query massal.
     *
     * @param  Request  $request  menerima request POST dari tombol 'Tandai Semua Dibaca'.
     * @return RedirectResponse mengembalikan redirect kembali dengan pesan konfirmasi.
     *
     * Contoh pemanggilan:
     * `POST /notifikasi/mark-all-read`
     */
    public function markAllReadWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();
        NotifikasiUser::where('id_user', $user->id_user)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'waktu_dibaca' => now(),
            ]);

        return back()->with('success', 'Semua notifikasi berhasil ditandai sebagai telah dibaca.');
    }

    /**
     * menyediakan metode alias `markAllRead` yang meneruskan ke `markAllReadWeb`.
     *
     * @param  Request  $request  meneruskan objek request HTTP.
     * @return RedirectResponse mengembalikan redirect kembali.
     *
     * Contoh pemanggilan:
     * `$controller->markAllRead($request);`
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        return $this->markAllReadWeb($request);
    }

    /**
     * menyembunyikan (dismiss) notifikasi tertentu dari toast/pop-up sekaligus menandainya telah dibaca oleh pemiliknya.
     *
     * @param  Request  $request  menerima request POST dari aksi tutup notifikasi.
     * @param  int  $id  mencari record `NotifikasiUser` milik pengguna aktif.
     * @return RedirectResponse mengembalikan redirect kembali meski record sudah tidak ada agar aksi tetap idempoten.
     *
     * Contoh pemanggilan:
     * `POST /notifikasi/{id}/dismiss`
     */
    public function dismissNotificationWeb(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $notif = NotifikasiUser::where('id', $id)->where('id_user', $user->id_user)->first();
        if ($notif) {
            $notif->is_read = true;
            $notif->is_dismissed = true;
            $notif->waktu_dibaca = now();
            $notif->save();
        }

        return back()->with('success', 'Pemberitahuan telah ditandai.');
    }

    /**
     * menyediakan metode alias `dismiss` yang meneruskan ke `dismissNotificationWeb`.
     *
     * @param  Request  $request  meneruskan objek request HTTP.
     * @param  int  $id  meneruskan ID notifikasi pengguna.
     * @return RedirectResponse mengembalikan redirect kembali.
     *
     * Contoh pemanggilan:
     * `$controller->dismiss($request, 12);`
     */
    public function dismiss(Request $request, int $id): RedirectResponse
    {
        return $this->dismissNotificationWeb($request, $id);
    }

    /**
     * melayani polling JSON notifikasi terbaru bagi skrip JS header dan Service Worker tanpa me-refresh seluruh halaman.
     *
     * @param  Request  $request  membaca query `since_id` untuk hanya mengambil notifikasi baru yang belum pernah dilihat peramban.
     * @return JsonResponse mengembalikan `latest_id`, `unread_count`, item baru (`data`), dan 8 notifikasi terbaru (`recent`), atau 401 jika sesi berakhir.
     *
     * Contoh pemanggilan:
     * `GET /api/notifikasi-terbaru?since_id=45`
     */
    public function notifikasiTerbaru(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'unauthenticated', 'data' => []], 401);
        }

        $baseQuery = NotifikasiUser::where('id_user', $user->id_user)
            ->with(['reminder.paket.permohonan', 'user.roleEntity.permissions']);

        $latestId = (int) (NotifikasiUser::where('id_user', $user->id_user)->max('id') ?? 0);
        $unreadCount = NotifikasiUser::where('id_user', $user->id_user)->where('is_read', false)->count();

        $sinceId = $request->filled('since_id') ? (int) $request->query('since_id') : 0;

        $newItems = collect();
        // hanya mengisi 'data' saat klien mengirim since_id > 0 agar muat halaman awal tidak memicu toast/notifikasi OS berulang untuk riwayat lama.
        if ($sinceId > 0) {
            $newItems = (clone $baseQuery)
                ->where('is_read', false)
                ->where('is_dismissed', false)
                ->where('id', '>', $sinceId)
                ->orderBy('id')
                ->take(10)
                ->get()
                ->map(fn (NotifikasiUser $n) => $n->toApiPayload());
        }

        $recentNotifications = (clone $baseQuery)
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn (NotifikasiUser $n) => $n->toApiPayload());

        return response()->json([
            'status' => 'success',
            'latest_id' => $latestId,
            'unread_count' => $unreadCount,
            'data' => $newItems->values(),
            'recent' => $recentNotifications->values(),
        ]);
    }

    /**
     * menyusun query jadwal reminder induk (`parent_reminder_id IS NULL`) yang disaring sesuai cakupan izin RBAC pengguna aktif.
     *
     * @param  User  $user  memeriksa izin operasional lintas paket versus pengguna yang terisolasi pada paket penugasannya saja.
     * @return Builder<Reminder> mengembalikan query builder Reminder siap difilter dan dipaginasi.
     *
     * Contoh pemanggilan:
     * `$jadwalQuery = $this->buildJadwalQueryForUser($user);`
     */
    protected function buildJadwalQueryForUser(User $user): Builder
    {
        $query = Reminder::query()
            ->with(['paket.permohonan', 'paket.kontrakSpk', 'pembayaran'])
            ->whereNull('parent_reminder_id')
            ->whereIn('tipe_reminder', Reminder::SCHEDULED_TYPES);

        $canSeeAll = $user->hasAnyPermission([
            'paket.view_all',
            'spk.create',
            'penerimaan.view',
            'penerimaan.bast_create',
            'pembayaran.pencairan_sp2d',
            'pembayaran.spp_terbitkan',
            'pembayaran.pengajuan',
            'verifikasi.bidang_2_view',
            'verifikasi.wadir_2_view',
            'anggaran.mak_view',
        ]);

        if ($canSeeAll) {
            return $query;
        }

        // membatasi pemegang akses sempit (PPK/PP/Pemohon) agar hanya melihat jadwal pengingat dari paket yang terkait langsung dengannya.
        return $query->whereHas('paket', function ($pq) use ($user): void {
            $pq->where('id_user_ppk', $user->id_user)
                ->orWhere('id_user_pp', $user->id_user)
                ->orWhere('id_user_ppbj', $user->id_user)
                ->orWhereHas('permohonan', function ($prq) use ($user): void {
                    $prq->where('id_user', $user->id_user);
                });
        });
    }
}
