<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiUser;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controller Feed Notifikasi Personal Pengguna (Modul 9)
 *
 * Mengelola feed notifikasi dan peringatan aktif yang ditujukan kepada setiap pengguna:
 * - Menampilkan daftar notifikasi (dapat disaring khusus yang belum dibaca/perlu tindakan/pengingat).
 * - Menandai notifikasi telah dibaca (satuan atau tandai semua).
 * - Mengabaikan atau mengarsipkan notifikasi dari tampilan feed.
 * - Endpoint polling notifikasi terbaru untuk notifikasi desktop/browser.
 */
class NotifikasiUserController extends Controller
{
    public function __construct(
        public ?PengadaanNotificationService $notificationService = null
    ) {}

    /**
     * Menampilkan antarmuka daftar feed Notifikasi dan Pengingat (Reminder) seluruh role.
     */
    public function notifikasiIndex(Request $request): View
    {
        $user = Auth::user();

        $tabCounts = [
            'semua' => NotifikasiUser::where('id_user', $user->id_user)->count(),
            'belum_dibaca' => NotifikasiUser::where('id_user', $user->id_user)->where('is_read', false)->count(),
            'perlu_tindakan' => NotifikasiUser::where('id_user', $user->id_user)
                ->whereHas('reminder', function ($q) {
                    $q->where('tipe_reminder', 'verifikasi')
                        ->orWhere('judul_alert', 'like', '%revisi%')
                        ->orWhere('judul_alert', 'like', '%tindakan%')
                        ->orWhere('pesan_alert', 'like', '%tindak lanjut%')
                        ->orWhere('pesan_alert', 'like', '%revisi%');
                })->count(),
            'pengingat' => NotifikasiUser::where('id_user', $user->id_user)
                ->whereHas('reminder', function ($q) {
                    $q->whereIn('tipe_reminder', ['pengiriman', 'h_minus_2_termin', 'bulanan_tahunan'])
                        ->orWhere('judul_alert', 'like', '%pengingat%');
                })->count(),
        ];

        $query = NotifikasiUser::where('id_user', $user->id_user)
            ->with(['reminder.paket.permohonan', 'reminder.paket.kontrak', 'reminder.paket.pembayaran']);

        $currentTab = $request->query('tab', 'semua');
        if ($currentTab === 'belum_dibaca') {
            $query->where('is_read', false);
        } elseif ($currentTab === 'perlu_tindakan') {
            $query->whereHas('reminder', function ($q) {
                $q->where('tipe_reminder', 'verifikasi')
                    ->orWhere('judul_alert', 'like', '%revisi%')
                    ->orWhere('judul_alert', 'like', '%tindakan%')
                    ->orWhere('pesan_alert', 'like', '%tindak lanjut%')
                    ->orWhere('pesan_alert', 'like', '%revisi%');
            });
        } elseif ($currentTab === 'pengingat') {
            $query->whereHas('reminder', function ($q) {
                $q->whereIn('tipe_reminder', ['pengiriman', 'h_minus_2_termin', 'bulanan_tahunan'])
                    ->orWhere('judul_alert', 'like', '%pengingat%');
            });
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('reminder', function ($rq) use ($keyword) {
                    $rq->where('judul_alert', 'like', "%{$keyword}%")
                        ->orWhere('pesan_alert', 'like', "%{$keyword}%")
                        ->orWhere('nomor_paket', 'like', "%{$keyword}%")
                        ->orWhereHas('paket', function ($pq) use ($keyword) {
                            $pq->where('nama_paket', 'like', "%{$keyword}%")
                                ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                                ->orWhereHas('permohonan', function ($prq) use ($keyword) {
                                    $prq->where('judul_pengadaan', 'like', "%{$keyword}%");
                                });
                        });
                });
            });
        }

        if ($request->filled('jenis') && $request->query('jenis') !== 'semua') {
            $jenis = (string) $request->query('jenis');
            if ($jenis === 'pengingat') {
                $query->whereHas('reminder', function ($q) {
                    $q->whereIn('tipe_reminder', ['pengiriman', 'h_minus_2_termin', 'bulanan_tahunan'])
                        ->orWhere('judul_alert', 'like', '%pengingat%');
                });
            } elseif ($jenis === 'perlu_tindakan') {
                $query->whereHas('reminder', function ($q) {
                    $q->where('tipe_reminder', 'verifikasi')
                        ->orWhere('judul_alert', 'like', '%revisi%')
                        ->orWhere('judul_alert', 'like', '%tindakan%')
                        ->orWhere('pesan_alert', 'like', '%tindak lanjut%');
                });
            } elseif ($jenis === 'verifikasi') {
                $query->whereHas('reminder', function ($q) {
                    $q->where('tipe_reminder', 'verifikasi')
                        ->orWhere('judul_alert', 'like', '%verifikasi%')
                        ->orWhere('judul_alert', 'like', '%telaah%');
                });
            } elseif ($jenis === 'pembayaran') {
                $query->whereHas('reminder', function ($q) {
                    $q->where('tipe_reminder', 'pembayaran')
                        ->orWhere('judul_alert', 'like', '%pembayaran%')
                        ->orWhere('judul_alert', 'like', '%termin%')
                        ->orWhere('judul_alert', 'like', '%sp2d%');
                });
            } elseif ($jenis === 'pengiriman') {
                $query->whereHas('reminder', function ($q) {
                    $q->where('tipe_reminder', 'pengiriman')
                        ->orWhere('judul_alert', 'like', '%pengiriman%');
                });
            } elseif ($jenis === 'spk') {
                $query->whereHas('reminder', function ($q) {
                    $q->where('judul_alert', 'like', '%spk%')
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

        return view('notifikasi.index', compact('notifikasiList', 'tabCounts', 'currentTab'));
    }

    public function index(Request $request): View
    {
        return $this->notifikasiIndex($request);
    }

    /**
     * Menandai satu notifikasi tertentu telah dibaca oleh pengguna.
     */
    public function markAsReadWeb(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $notif = NotifikasiUser::where('id', $id)->where('id_user', $user->id_user)->firstOrFail();
        $notif->is_read = true;
        $notif->waktu_dibaca = now();
        $notif->save();

        return back()->with('success', 'Notifikasi berhasil ditandai sebagai dibaca.');
    }

    public function markAsRead(Request $request, int $id): RedirectResponse
    {
        return $this->markAsReadWeb($request, $id);
    }

    /**
     * Menandai seluruh notifikasi yang belum dibaca milik pengguna aktif sebagai telah dibaca.
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

    public function markAllRead(Request $request): RedirectResponse
    {
        return $this->markAllReadWeb($request);
    }

    /**
     * Mengabaikan (dismiss) notifikasi dari feed.
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

    public function dismiss(Request $request, int $id): RedirectResponse
    {
        return $this->dismissNotificationWeb($request, $id);
    }

    /**
     * Mengirimkan notifikasi dan email simulasi uji coba ke akun pengguna yang sedang aktif.
     */
    public function kirimNotifikasiUjiCoba(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'jenis' => ['nullable', 'string', 'in:pengajuan_baru,persetujuan_wadir,penolakan_wadir,tembusan_spk,barang_sampai,memo_bayar,spp_terbit,sp2d_cair'],
        ]);

        $jenis = $validated['jenis'] ?? 'pengajuan_baru';
        $notif = $this->notificationService?->kirimUjiCoba($user, $jenis);

        return back()->with(
            'success',
            "Notifikasi simulasi '{$notif?->judul}' berhasil dibangkitkan dan dikirimkan ke surel {$user->email}."
        );
    }

    /**
     * Mengembalikan daftar notifikasi belum dibaca terbaru khusus untuk pemicu notifikasi native OS Desktop.
     */
    public function notifikasiTerbaru(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'unauthenticated', 'data' => []], 401);
        }

        $query = NotifikasiUser::where('id_user', $user->id_user)
            ->where('is_read', false)
            ->where('is_dismissed', false)
            ->with(['reminder.paket.permohonan']);

        if ($request->filled('since_id')) {
            $query->where('id', '>', (int) $request->since_id);
        }

        $list = $query->latest('id')->take(10)->get()->map(function (NotifikasiUser $n) {
            return [
                'id' => $n->id,
                'judul' => $n->judul,
                'pesan' => $n->pesan,
                'link' => $n->link,
                'created_at' => $n->created_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $list,
            'unread_count' => NotifikasiUser::where('id_user', $user->id_user)->where('is_read', false)->count(),
        ]);
    }
}
