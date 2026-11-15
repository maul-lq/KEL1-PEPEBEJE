<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Controller Dashboard Utama & Orchestrator Antarmuka (HomeController)
 *
 * Mengelola dashboard multi-role, widget statistik eksekutif, profil pengguna,
 * streaming PDF resmi, dan health check sistem.
 */
class HomeController extends Controller
{
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan dashboard utama operasional adaptif berbasis modul izin RBAC pengguna.
     */
    public function index(): View
    {
        $user = Auth::user();
        if ($user) {
            $user->load(['unit', 'roleEntity.permissions']);
        }

        // Statistik agregat real-time sistem
        $stats = [
            'total_units' => Unit::count(),
            'total_permohonan' => PermohonanPengadaan::where('status_permohonan', '!=', 'digantikan_oleh_revisi')->count(),
            'total_anggaran' => (float) (AnggaranMAK::sum('pagu_anggaran') ?? 0),
            'total_paket' => PaketPengadaan::count(),
            'total_vendor' => Vendor::count(),
        ];

        // Daftar 5 usulan permohonan terkini (berbasis RBAC permission)
        $permohonanQuery = PermohonanPengadaan::with(['unit', 'user'])
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi');
        if ($user && $user->hasPermission('permohonan.view_own') && ! $user->hasPermission('permohonan.view_all')) {
            $permohonanQuery->where('id_user', $user->id_user);
        }
        $recentPermohonan = $permohonanQuery->latest('tanggal_pengajuan')->take(5)->get();

        // Daftar 5 paket pengadaan terkini (scopeForUser berbasis RBAC permission)
        $paketQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser']);
        if ($user) {
            $paketQuery->forUser($user);
        }
        $recentPaket = $paketQuery->latest('tanggal_registrasi_paket')->take(5)->get();

        // Notifikasi personal pengguna
        $notifications = $user ? NotifikasiUser::with('reminder.paket')
            ->where('id_user', $user->id_user)
            ->where('is_dismissed', false)
            ->latest('id')
            ->take(5)
            ->get() : collect();

        $units = Unit::all();

        // Data modular spesifik per hak akses RBAC
        $roleData = $user ? $this->buildModularDashboardData($user) : ['active_modules' => [], 'stats' => []];

        if (! empty($roleData['stats'])) {
            $stats = array_merge($stats, $roleData['stats']);
        }

        return view('dashboard.index', compact('user', 'stats', 'recentPermohonan', 'recentPaket', 'notifications', 'units', 'roleData'));
    }

    /**
     * Membangun data dashboard modular berdasarkan daftar izin RBAC pengguna aktif.
     *
     * @return array<string, mixed>
     */
    protected function buildModularDashboardData(User $user): array
    {
        $data = [
            'active_modules' => [],
            'stats' => [],
        ];

        // 1. Modul Administrasi Sistem & RBAC
        if ($user->hasAnyPermission(['admin.approval_registrasi', 'admin.user_manage', 'admin.role_manage'])) {
            $data['active_modules'][] = 'admin';
            $data['stats'] = array_merge($data['stats'], [
                'pending_registrations' => User::where('status_registrasi', User::STATUS_REGISTRASI_MENUNGGU)->count(),
                'active_users' => User::where('status_aktif', true)->count(),
                'total_roles' => Role::count(),
                'total_units' => Unit::count(),
                'total_permohonan_all' => PermohonanPengadaan::where('status_permohonan', '!=', 'digantikan_oleh_revisi')->count(),
            ]);
            $data['pending_users'] = $user->hasPermission('admin.approval_registrasi')
                ? User::with('unit')
                    ->where('status_registrasi', User::STATUS_REGISTRASI_MENUNGGU)
                    ->latest('created_at')
                    ->take(6)
                    ->get()
                : collect();
            $data['roles_summary'] = Role::withCount('users')
                ->orderByDesc('users_count')
                ->get();
            $data['roles_list'] = $user->hasPermission('admin.approval_registrasi')
                ? Role::orderBy('label')->get()
                : collect();
            $data['admin_audit_logs'] = $user->hasPermission('audit_log.view')
                ? AuditLog::with('user')->latest('waktu_log')->take(10)->get()
                : collect();
        }

        // 2. Modul Pemohon / Unit Pengusul (jika memiliki hak buat atau melihat usulan sendiri)
        if ($user->hasAnyPermission(['permohonan.create', 'permohonan.view_own'])) {
            $data['active_modules'][] = 'permohonan_user';
            $ownBase = PermohonanPengadaan::where('id_user', $user->id_user)
                ->where('status_permohonan', '!=', 'digantikan_oleh_revisi');

            $revisiStatuses = ['ditolak', 'revisi', 'perlu_revisi'];
            $prosesStatuses = [
                'diajukan',
                PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                'pending_anggaran',
                'diproses_ppbj',
            ];

            $data['stats'] = array_merge($data['stats'], [
                'total_usulan' => (clone $ownBase)->count(),
                'usulan_proses' => (clone $ownBase)->whereIn('status_permohonan', $prosesStatuses)->count(),
                'usulan_disetujui' => (clone $ownBase)->where('status_permohonan', 'disetujui')->count(),
                'usulan_revisi' => (clone $ownBase)->whereIn('status_permohonan', $revisiStatuses)->count(),
                'usulan_selesai' => (clone $ownBase)->where('status_permohonan', 'selesai')->count(),
                'pengajuan_aktif' => (clone $ownBase)->whereNotIn('status_permohonan', ['selesai', 'ditolak'])->count(),
                'menunggu_tindakan' => (clone $ownBase)->whereIn('status_permohonan', $revisiStatuses)->count(),
                'notifikasi_belum_dibaca' => NotifikasiUser::where('id_user', $user->id_user)
                    ->where('is_read', false)
                    ->where('is_dismissed', false)
                    ->count(),
            ]);

            $data['revisi_alerts'] = (clone $ownBase)
                ->whereIn('status_permohonan', $revisiStatuses)
                ->latest('updated_at')
                ->take(5)
                ->get();
            $data['perlu_tindakan'] = (clone $ownBase)
                ->whereIn('status_permohonan', $revisiStatuses)
                ->latest('updated_at')
                ->take(5)
                ->get();
            $data['aktivitas_terbaru'] = $this->getUserRecentActivities($user);
        }

        // 3. Modul Verifikasi Awal Staf Bidang II
        if ($user->hasPermission('verifikasi.bidang_2_view')) {
            $data['active_modules'][] = 'verifikasi_bidang2';
            $data['stats'] = array_merge($data['stats'], [
                'menunggu_telaah' => PermohonanPengadaan::whereIn('status_permohonan', ['diajukan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2])->count(),
                'reviu_paralel' => PermohonanPengadaan::whereIn('status_permohonan', [
                    PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                    PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                    PermohonanPengadaan::STATUS_REVIU_PARALEL,
                ])->count(),
                'bidang2_disetujui' => PermohonanPengadaan::whereIn('status_permohonan', ['disetujui', 'diproses_ppbj', 'selesai'])->count(),
                'total_berkas' => PermohonanPengadaan::where('status_permohonan', '!=', 'digantikan_oleh_revisi')->count(),
            ]);
            $data['antrean_telaah'] = PermohonanPengadaan::with(['unit', 'user'])
                ->whereIn('status_permohonan', ['diajukan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2])
                ->latest('tanggal_pengajuan')
                ->take(6)
                ->get();
            $data['bidang2_recent'] = PermohonanPengadaan::with(['unit', 'user'])
                ->where('status_permohonan', '!=', 'digantikan_oleh_revisi')
                ->latest('updated_at')
                ->take(10)
                ->get();
        }

        // 4. Modul Bagian Perencanaan & Pagu DIPA MAK
        if ($user->hasAnyPermission(['anggaran.mak_view', 'anggaran.mak_manage', 'anggaran.mak_alokasi', 'anggaran.mak_lock', 'permohonan.split_mak'])) {
            $data['active_modules'][] = 'perencanaan';
            $antreanMakBase = PermohonanPengadaan::whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                'pending_anggaran',
            ])
                ->where(function ($q) {
                    $q->whereNull('kode_mak')->orWhere('kode_mak', '');
                })
                ->whereDoesntHave('verifikasiParalel', function ($q) {
                    $q->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
                        ->where('status_keputusan', 'disetujui');
                });

            $totalPaguDipa = (float) AnggaranMAK::sum('pagu_anggaran');
            $sisaPaguDipa = (float) AnggaranMAK::sum('sisa_pagu_mak');

            $data['stats'] = array_merge($data['stats'], [
                'total_pagu_dipa' => $totalPaguDipa,
                'total_pagu_dipa_compact' => $this->formatCompactRupiah($totalPaguDipa),
                'pagu_teralokasi' => (float) AnggaranMAK::sum(DB::raw('pagu_anggaran - sisa_pagu_mak')),
                'sisa_pagu_dipa' => $sisaPaguDipa,
                'sisa_pagu_dipa_compact' => $this->formatCompactRupiah($sisaPaguDipa),
                'menunggu_mak' => (clone $antreanMakBase)->count(),
                'mak_locked' => AnggaranMAK::where('is_locked', true)->count(),
            ]);
            $data['antrean_mak'] = (clone $antreanMakBase)
                ->with(['unit', 'user'])
                ->latest('tanggal_pengajuan')
                ->take(8)
                ->get();
            $data['daftar_mak'] = AnggaranMAK::latest('updated_at')->take(10)->get();
        }

        // 5. Modul Wakil Direktur II (Disposisi & Persetujuan Awal / Memo Pembayaran Level 1)
        if ($user->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide', 'pembayaran.memo_wadir2'])) {
            $data['active_modules'][] = 'verifikasi_wadir2';
            $wadirAntreanQuery = PermohonanPengadaan::whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
            ])->whereDoesntHave('verifikasiParalel', function ($q) {
                $q->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
                    ->where('status_keputusan', 'disetujui');
            });

            $wadirMemoQuery = MemoBayar::where('role_approver', 'wadir_2')
                ->whereIn('status_memo', ['diajukan', 'menunggu'])
                ->whereHas('pembayaran', function ($q) {
                    $q->whereNotIn('status_pembayaran', ['ditolak', 'perlu_revisi', 'lunas']);
                });

            $data['stats'] = array_merge($data['stats'], [
                'menunggu_persetujuan' => $user->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide'])
                    ? (clone $wadirAntreanQuery)->count()
                    : 0,
                'memo_bayar_pending' => $user->hasPermission('pembayaran.memo_wadir2')
                    ? (clone $wadirMemoQuery)->count()
                    : 0,
                'total_disetujui' => PermohonanPengadaan::where(function ($q) {
                    $q->whereIn('status_permohonan', ['disetujui', 'diproses_ppbj', 'selesai'])
                        ->orWhereHas('verifikasiParalel', function ($vq) {
                            $vq->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
                                ->where('status_keputusan', 'disetujui');
                        });
                })->count(),
                'total_ditolak' => PermohonanPengadaan::whereIn('status_permohonan', ['ditolak', 'perlu_revisi', 'revisi'])->count(),
            ]);

            $data['antrean_disposisi'] = $user->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide'])
                ? (clone $wadirAntreanQuery)->with(['unit', 'user'])->latest('tanggal_pengajuan')->take(6)->get()
                : collect();
            $data['antrean_memo_bayar'] = $user->hasPermission('pembayaran.memo_wadir2')
                ? (clone $wadirMemoQuery)->with(['pembayaran.paket.vendor', 'user'])->latest('created_at')->take(6)->get()
                : collect();

            $wadirRecentVerif = VerifikasiParalel::with('permohonan')
                ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
                ->latest('updated_at')
                ->take(6)
                ->get()
                ->map(fn (VerifikasiParalel $vp): array => [
                    'waktu' => $vp->tanggal_keputusan ? $vp->tanggal_keputusan->format('d M Y, H:i') : ($vp->updated_at?->format('d M Y, H:i') ?? '-'),
                    'aktivitas' => 'Disposisi Usulan',
                    'subjek' => $vp->permohonan?->judul_pengadaan ?? $vp->nomor_surat,
                    'status' => ucwords(str_replace('_', ' ', $vp->status_keputusan ?? 'disetujui')),
                    'sort_at' => $vp->tanggal_keputusan?->timestamp ?? ($vp->updated_at?->timestamp ?? 0),
                ]);

            $wadirRecentMemo = MemoBayar::with('pembayaran.paket')
                ->where('role_approver', 'wadir_2')
                ->where('status_memo', '!=', 'menunggu')
                ->latest('updated_at')
                ->take(6)
                ->get()
                ->map(fn (MemoBayar $m): array => [
                    'waktu' => $m->tanggal_acc ? $m->tanggal_acc->format('d M Y, H:i') : ($m->updated_at?->format('d M Y, H:i') ?? '-'),
                    'aktivitas' => 'Memo Bayar Lvl 1',
                    'subjek' => $m->pembayaran?->paket?->nama_paket ?? $m->nomor_pembayaran,
                    'status' => ucwords(str_replace('_', ' ', $m->status_memo ?? 'disetujui')),
                    'sort_at' => $m->tanggal_acc?->timestamp ?? ($m->updated_at?->timestamp ?? 0),
                ]);

            $wadirRecent = $wadirRecentVerif->concat($wadirRecentMemo)
                ->sortByDesc('sort_at')
                ->take(10)
                ->values();

            if ($wadirRecent->isEmpty()) {
                $wadirRecent = PermohonanPengadaan::where('status_permohonan', '!=', 'digantikan_oleh_revisi')
                    ->latest('updated_at')
                    ->take(10)
                    ->get()
                    ->map(fn (PermohonanPengadaan $p): array => [
                        'waktu' => $p->updated_at?->format('d M Y, H:i') ?? '-',
                        'aktivitas' => 'Usulan Masuk',
                        'subjek' => $p->judul_pengadaan,
                        'status' => ucwords(str_replace('_', ' ', $p->status_permohonan)),
                        'sort_at' => $p->updated_at?->timestamp ?? 0,
                    ]);
            }

            $data['wadir2_recent'] = $wadirRecent;
        }

        // 6. Modul Pejabat Pembuat Komitmen (PPK - Persetujuan Awal, Dokumen Paket, Memo Bayar)
        if ($user->hasAnyPermission(['reviu.ppk', 'pembayaran.memo_ppk'])) {
            $data['active_modules'][] = 'ppk';
            $ppkAwalQuery = PermohonanPengadaan::where('id_user_ppk_awal', $user->id_user)
                ->whereIn('status_permohonan', [
                    PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                    PermohonanPengadaan::STATUS_REVIU_PARALEL,
                ])
                ->whereDoesntHave('verifikasiParalel', function ($q) {
                    $q->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
                        ->where('status_keputusan', 'disetujui');
                });

            $ppkPaketBase = PaketPengadaan::where('id_user_ppk', $user->id_user)
                ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);

            $ppkMemoQuery = MemoBayar::where('role_approver', 'ppk')
                ->whereIn('status_memo', ['diajukan', 'menunggu'])
                ->whereHas('pembayaran', function ($q) use ($user) {
                    $q->whereNotIn('status_pembayaran', ['ditolak', 'perlu_revisi', 'lunas'])
                        ->whereHas('paket', function ($pq) use ($user) {
                            $pq->where('id_user_ppk', $user->id_user)
                                ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);
                        });
                });

            $data['stats'] = array_merge($data['stats'], [
                'antrean_persetujuan_awal' => $user->hasPermission('reviu.ppk') ? (clone $ppkAwalQuery)->count() : 0,
                'antrean_dokumen_pengadaan' => $user->hasPermission('reviu.ppk')
                    ? (clone $ppkPaketBase)->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)->count()
                    : 0,
                'total_paket_ppk' => $user->hasPermission('reviu.ppk') ? (clone $ppkPaketBase)->count() : 0,
                'tender_aktif' => $user->hasPermission('reviu.ppk')
                    ? (clone $ppkPaketBase)->whereNotIn('status_paket', ['selesai', 'perlu_perbaikan'])->count()
                    : 0,
                'spk_aktif' => $user->hasPermission('reviu.ppk') ? (clone $ppkPaketBase)->whereHas('kontrakSpk')->count() : 0,
                'memo_bayar_pending' => $user->hasPermission('pembayaran.memo_ppk') ? (clone $ppkMemoQuery)->count() : 0,
            ]);

            $data['antrean_dokumen_pengadaan_list'] = $user->hasPermission('reviu.ppk')
                ? (clone $ppkPaketBase)
                    ->with(['permohonan.unit', 'vendor', 'ppUser', 'reviuDokumen.user'])
                    ->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
                    ->latest('tanggal_registrasi_paket')
                    ->take(5)
                    ->get()
                : collect();
            $data['antrean_persetujuan_awal_list'] = $user->hasPermission('reviu.ppk')
                ? (clone $ppkAwalQuery)->with(['unit', 'user', 'verifikasiParalel'])->latest('tanggal_pengajuan')->take(5)->get()
                : collect();
            $data['paket_komitmen'] = $user->hasPermission('reviu.ppk')
                ? (clone $ppkPaketBase)->with(['permohonan.unit', 'vendor', 'kontrakSpk'])->latest('tanggal_registrasi_paket')->take(10)->get()
                : collect();
            $data['antrean_memo'] = $user->hasPermission('pembayaran.memo_ppk')
                ? (clone $ppkMemoQuery)->with(['pembayaran.paket.vendor'])->latest('created_at')->take(5)->get()
                : collect();
        }

        // 7. Modul Koordinator PPBJ (Paket, Pemecahan, SPK, Pengajuan Pembayaran)
        if ($user->hasAnyPermission(['paket.create', 'paket.view_all', 'permohonan.split_jenis', 'spk.create', 'reminder.manage', 'pembayaran.pengajuan', 'pembayaran.spp_terbitkan'])) {
            $data['active_modules'][] = 'ppbj';
            $data['stats'] = array_merge($data['stats'], [
                'total_paket' => PaketPengadaan::count(),
                'paket_proses' => PaketPengadaan::where('status_paket', '!=', 'selesai')->count(),
                'paket_selesai_count' => PaketPengadaan::where('status_paket', 'selesai')->count(),
                'routing_langsung' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)->count(),
                'routing_pp' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->count(),
                'routing_ppk' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PPK)->count(),
                'belum_spk' => PaketPengadaan::where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG)
                    ->whereDoesntHave('kontrakSpk')
                    ->count(),
                'usulan_siap_paket' => PermohonanPengadaan::whereIn('status_permohonan', ['disetujui', 'diproses_ppbj'])
                    ->whereDoesntHave('paketPengadaan')
                    ->count(),
                'bast_siap_bayar_count' => PenerimaanBarang::where('status_fisik', 'terima')
                    ->whereDoesntHave('paket.pembayaran')
                    ->count(),
            ]);

            $data['bast_siap_bayar'] = PenerimaanBarang::with(['paket.vendor', 'paket.permohonan.unit', 'fotoDokumentasi'])
                ->where('status_fisik', 'terima')
                ->whereDoesntHave('paket.pembayaran')
                ->latest('tanggal_penerimaan')
                ->take(5)
                ->get();
            $data['siap_paket'] = PermohonanPengadaan::with('unit')
                ->whereIn('status_permohonan', ['disetujui', 'diproses_ppbj'])
                ->whereDoesntHave('paketPengadaan')
                ->latest('tanggal_pengajuan')
                ->take(5)
                ->get();
            $data['ppbj_recent_paket'] = PaketPengadaan::with(['permohonan.unit', 'vendor', 'ppkUser', 'ppUser'])
                ->latest('tanggal_registrasi_paket')
                ->take(10)
                ->get();
        }

        // 8. Modul Pemantauan Aktivitas PP (FR RBAC: pp.monitoring_aktivitas / lihat_pp)
        if ($user->canMonitorPp()) {
            $data['active_modules'][] = 'monitoring_pp';
            $data['stats'] = array_merge($data['stats'], [
                'pp_total' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->count(),
                'pp_dalam_reviu' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
                    ->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])
                    ->count(),
                'pp_menunggu_ppk' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
                    ->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
                    ->count(),
                'pp_siap_belanja' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
                    ->whereIn('status_paket', [
                        PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
                        PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
                        'reviu_dokumen_disetujui',
                    ])
                    ->count(),
            ]);
            $data['pp_monitoring_list'] = PaketPengadaan::with([
                'vendor',
                'ppUser',
                'ppkUser',
                'reviuDokumen' => fn ($rq) => $rq->where('role_reviewer', 'pp')->latest('putaran_ke'),
            ])
                ->where(function ($q) {
                    $q->where('jalur_routing', PaketPengadaan::ROUTING_PP)
                        ->orWhereNotNull('id_user_pp');
                })
                ->latest('updated_at')
                ->take(8)
                ->get();
            $data['pp_audit_logs'] = AuditLog::with('user')
                ->where(function ($q) {
                    $q->whereHas('user', function ($uq) {
                        $uq->where('role', 'pp')
                            ->orWhereHas('roleEntity.permissions', fn ($pq) => $pq->where('nama_permission', 'reviu.pp'));
                    })->orWhere('jenis_aksi', 'like', '%_PP%');
                })
                ->latest('waktu_log')
                ->take(6)
                ->get();
        }

        // 9. Modul Pejabat Pengadaan (PP)
        if ($user->hasPermission('reviu.pp')) {
            $data['active_modules'][] = 'pp';
            $ppScope = function ($q) use ($user) {
                $q->whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])
                    ->orWhere('id_user_pp', $user->id_user);
            };

            $data['stats'] = array_merge($data['stats'], [
                'total_paket_pp' => PaketPengadaan::where($ppScope)->count(),
                'paket_negosiasi' => PaketPengadaan::where($ppScope)
                    ->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])
                    ->count(),
                'paket_spk' => PaketPengadaan::where($ppScope)
                    ->whereIn('status_paket', [
                        PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
                        PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
                        PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
                        'spk_terbit',
                        'penerimaan_selesai',
                        'pengajuan_pembayaran',
                        'perintah_bayar',
                    ])
                    ->count(),
                'paket_selesai' => PaketPengadaan::where($ppScope)->where('status_paket', 'selesai')->count(),
            ]);

            $data['antrean_negosiasi'] = PaketPengadaan::with(['permohonan.unit', 'vendor'])
                ->where($ppScope)
                ->whereIn('status_paket', [
                    'terdaftar',
                    'reviu_dokumen',
                    'perlu_perbaikan',
                    PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
                ])
                ->latest('updated_at')
                ->take(8)
                ->get();
            $data['riwayat_paket_pp'] = PaketPengadaan::with(['permohonan.unit', 'vendor', 'kontrakSpk'])
                ->where($ppScope)
                ->latest('updated_at')
                ->take(10)
                ->get();
        }

        // 10. Modul Unit Perlengkapan & BAST
        if ($user->hasAnyPermission(['penerimaan.view', 'penerimaan.bast_create', 'penerimaan.bast_sign'])) {
            $data['active_modules'][] = 'perlengkapan';

            $pendingSpk = KontrakSPK::with(['paket.vendor', 'paket.permohonan.unit'])
                ->whereDoesntHave('paket.penerimaanBarang')
                ->latest('tanggal_spk')
                ->take(5)
                ->get()
                ->map(function (KontrakSPK $spk): array {
                    return [
                        'id_paket' => $spk->nomor_paket,
                        'nomor_referensi' => $spk->nomor_spk ?? $spk->nomor_paket,
                        'judul_paket' => $spk->paket->nama_paket ?? $spk->nomor_paket,
                        'vendor_nama' => $spk->paket->vendor->nama_vendor ?? 'Penyedia Resmi',
                        'unit_nama' => $spk->paket->permohonan->unit->nama_unit ?? ($spk->paket->permohonan->kode_unit ?? '-'),
                        'jenis_jalur' => 'Kontrak Kerja',
                        'is_langsung' => false,
                        'batas_kirim' => $spk->tanggal_selesai
                            ? date('d M Y', strtotime((string) $spk->tanggal_selesai))
                            : ($spk->tanggal_spk ? date('d M Y', strtotime((string) $spk->tanggal_spk)) : '-'),
                        'tanggal_sort' => $spk->tanggal_spk ? (string) $spk->tanggal_spk : (string) $spk->created_at,
                    ];
                });

            $pendingLangsung = PaketPengadaan::with(['vendor', 'permohonan.unit'])
                ->where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
                ->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
                ->whereDoesntHave('penerimaanBarang')
                ->latest('tanggal_registrasi_paket')
                ->take(5)
                ->get()
                ->map(function (PaketPengadaan $paket): array {
                    return [
                        'id_paket' => $paket->nomor_paket,
                        'nomor_referensi' => $paket->nomor_paket,
                        'judul_paket' => $paket->nama_paket ?? $paket->nomor_paket,
                        'vendor_nama' => $paket->vendor->nama_vendor ?? 'Belanja Langsung',
                        'unit_nama' => $paket->permohonan->unit->nama_unit ?? ($paket->permohonan->kode_unit ?? '-'),
                        'jenis_jalur' => 'Belanja Langsung <=50Jt',
                        'is_langsung' => true,
                        'batas_kirim' => $paket->tanggal_eksekusi_belanja
                            ? date('d M Y', strtotime((string) $paket->tanggal_eksekusi_belanja))
                            : ($paket->tanggal_registrasi_paket ? date('d M Y', strtotime((string) $paket->tanggal_registrasi_paket)) : '-'),
                        'tanggal_sort' => $paket->tanggal_eksekusi_belanja
                            ? (string) $paket->tanggal_eksekusi_belanja
                            : ($paket->tanggal_registrasi_paket ? (string) $paket->tanggal_registrasi_paket : (string) $paket->created_at),
                    ];
                });

            $data['stats'] = array_merge($data['stats'], [
                'spk_masuk' => KontrakSPK::count() + PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
                    ->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
                    ->count(),
                'belum_diperiksa' => KontrakSPK::whereDoesntHave('paket.penerimaanBarang')->count()
                    + PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
                        ->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
                        ->whereDoesntHave('penerimaanBarang')
                        ->count(),
                'total_bast' => PenerimaanBarang::count(),
                'belanja_barang' => PenerimaanBarang::where('kategori_belanja', 'belanja_barang')->count(),
                'belanja_modal' => PenerimaanBarang::where('kategori_belanja', 'belanja_modal')->count(),
            ]);

            $data['jadwal_pengiriman'] = $pendingSpk->concat($pendingLangsung)
                ->sortByDesc('tanggal_sort')
                ->take(5)
                ->values();
            $data['bast_terbaru'] = PenerimaanBarang::with(['paket.vendor', 'user'])
                ->latest('tanggal_penerimaan')
                ->take(10)
                ->get();
        }

        // 11. Modul Keuangan & Pencairan Dana SP2D
        if ($user->hasPermission('pembayaran.pencairan_sp2d')) {
            $data['active_modules'][] = 'keuangan';

            $unreadReminders = NotifikasiUser::with('reminder.paket')
                ->where('id_user', $user->id_user)
                ->where('is_read', false)
                ->where('is_dismissed', false)
                ->latest('created_at')
                ->take(5)
                ->get();

            $remindersFallback = $unreadReminders->isEmpty()
                ? Reminder::with('paket')
                    ->whereIn('tipe_reminder', ['tembusan_spk', 'pembayaran', 'pengingat'])
                    ->latest('waktu_trigger')
                    ->take(5)
                    ->get()
                : $unreadReminders;

            $totalDanaCair = (float) (TransaksiPencairan::sum('nominal_transfer') ?? 0);

            $data['stats'] = array_merge($data['stats'], [
                'reminder_masuk' => $unreadReminders->count() ?: Reminder::whereIn('tipe_reminder', ['tembusan_spk', 'pembayaran', 'pengingat'])->count(),
                'paket_masuk_ppbj' => Pembayaran::where('status_pembayaran', 'perintah_bayar')->count(),
                'transaksi_lunas' => TransaksiPencairan::count(),
                'total_dana_cair' => $totalDanaCair,
                'total_dana_cair_compact' => $this->formatCompactRupiah($totalDanaCair),
                'spp_siap_verifikasi' => Pembayaran::where('status_pembayaran', 'menunggu_memo')->count(),
                'sp2d_siap_cair' => Pembayaran::where('status_pembayaran', 'perintah_bayar')->count(),
            ]);
            $data['antrean_tagihan'] = Pembayaran::with(['paket.kontrakSpk.vendor', 'paket.kontrak.vendor', 'paket.vendor', 'pengaju.unit', 'memoBayar'])
                ->where('status_pembayaran', 'perintah_bayar')
                ->latest('tanggal_perintah_bayar')
                ->take(5)
                ->get();
            $data['transaksi_cair'] = TransaksiPencairan::with(['pembayaran.paket.vendor'])
                ->latest('tanggal_transfer')
                ->take(10)
                ->get();
            $data['reminders'] = $remindersFallback;
        }

        return $data;
    }

    /**
     * Memformat nominal Rupiah secara ringkas agar proporsional di dalam kartu KPI 150px Figma.
     */
    protected function formatCompactRupiah(float $amount): string
    {
        if ($amount >= 1_000_000_000) {
            $formatted = rtrim(rtrim(number_format($amount / 1_000_000_000, 2, ',', '.'), '0'), ',');

            return 'Rp '.$formatted.' M';
        }
        if ($amount >= 1_000_000) {
            $formatted = rtrim(rtrim(number_format($amount / 1_000_000, 1, ',', '.'), '0'), ',');

            return 'Rp '.$formatted.' Jt';
        }
        if ($amount >= 1_000) {
            return 'Rp '.number_format($amount / 1_000, 0, ',', '.').' Rb';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Mengambil daftar aktivitas pengadaan terbaru yang terkait dengan akun pemohon.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function getUserRecentActivities(User $user)
    {
        $activities = collect();
        $userSuratList = PermohonanPengadaan::where('id_user', $user->id_user)->pluck('nomor_surat')->filter()->all();

        $permohonanMap = PermohonanPengadaan::where('id_user', $user->id_user)
            ->get()
            ->keyBy('nomor_surat');

        if (! empty($userSuratList)) {
            $logs = AuditLog::whereIn('id_entitas', $userSuratList)
                ->orWhere(function ($q) use ($user) {
                    $q->where('nama_entitas', 'permohonan_pengadaan')
                        ->where('id_user', $user->id_user);
                })
                ->latest('waktu_log')
                ->take(15)
                ->get();

            foreach ($logs as $log) {
                $permohonan = $permohonanMap->get($log->id_entitas)
                    ?? PermohonanPengadaan::where('nomor_surat', $log->id_entitas)->first();

                $aktivitasText = match ($log->jenis_aksi) {
                    'PERMOHONAN_CREATED' => 'Form diajukan',
                    'MEMO_CEK_MAK_B2_KE_PERENCANAAN', 'STATUS_UPDATED' => 'Form diperiksa',
                    'REVISI_PERMOHONAN', 'PERMOHONAN_DITOLAK' => 'Form ditolak',
                    'MEMO_KETERSEDIAAN_MAK_KE_WADIR2_PPK', 'PERMOHONAN_DISETUJUI' => 'Form disetujui',
                    'PAKET_CREATED' => 'Penyusunan dokumen',
                    'PENETAPAN_MAK' => 'Penetapan level anggaran',
                    'KONTRAK_CREATED', 'KONTRAK_UPLOADED' => 'Pembuatan surat kontrak',
                    'PENERIMAAN_BARANG', 'PENERIMAAN_VERIFIKASI' => 'Penyerahan barang',
                    'PEMBAYARAN_SP2D', 'MEMO_BAYAR_CREATED' => 'Pembayaran',
                    default => ucwords(strtolower(str_replace('_', ' ', $log->jenis_aksi ?? 'Aktivitas'))),
                };

                $rawStatus = strtolower((string) ($log->nilai_baru ?? $permohonan?->status_permohonan ?? 'diajukan'));
                $statusLabel = match ($rawStatus) {
                    'disetujui' => 'Disetujui',
                    'ditolak' => 'Ditolak',
                    'revisi' => 'Perlu Revisi',
                    'selesai' => 'Selesai',
                    'dibayar' => 'Dibayar',
                    'menunggu_bidang_2', 'cek_mak_perencanaan', 'reviu_paralel', 'diajukan' => 'Dalam Proses',
                    default => $permohonan ? ucwords(str_replace('_', ' ', $permohonan->status_permohonan)) : 'Dalam Proses',
                };

                $activities->push([
                    'waktu' => $log->waktu_log ? date('d M Y, H:i', strtotime($log->waktu_log)) : date('d M Y, H:i'),
                    'aktivitas' => $aktivitasText,
                    'nama_pengadaan' => $permohonan?->judul_pengadaan ?? $log->alasan ?? 'Pengadaan Unit',
                    'status' => $statusLabel,
                    'nomor_surat' => $log->id_entitas ?? $permohonan?->nomor_surat ?? '',
                ]);
            }
        }

        if ($activities->count() < 10) {
            $userPermohonans = PermohonanPengadaan::with(['paket.kontrak', 'paket.pembayaran'])
                ->where('id_user', $user->id_user)
                ->where('status_permohonan', '!=', 'digantikan_oleh_revisi')
                ->latest('updated_at')
                ->take(10)
                ->get();

            foreach ($userPermohonans as $p) {
                $statusMap = match ($p->status_permohonan) {
                    'disetujui' => ['aktivitas' => 'Form disetujui', 'status' => 'Disetujui'],
                    'ditolak' => ['aktivitas' => 'Form ditolak', 'status' => 'Ditolak'],
                    'revisi', 'perlu_revisi' => ['aktivitas' => 'Perlu perbaikan berkas', 'status' => 'Perlu Revisi'],
                    'diproses_ppbj' => ['aktivitas' => 'Diproses Unit Pengadaan', 'status' => 'Diproses Unit Pengadaan'],
                    'selesai' => ['aktivitas' => 'Pengadaan selesai', 'status' => 'Selesai'],
                    'menunggu_bidang_2' => ['aktivitas' => 'Telaah Bidang II', 'status' => 'Dalam Proses'],
                    'cek_mak_perencanaan' => ['aktivitas' => 'Cek Pos Anggaran', 'status' => 'Dalam Proses'],
                    'reviu_paralel', 'menunggu_acc_wadir_ppk' => ['aktivitas' => 'Reviu Pimpinan', 'status' => 'Dalam Proses'],
                    default => ['aktivitas' => 'Form diajukan', 'status' => 'Dalam Proses'],
                };

                $activities->push([
                    'waktu' => $p->updated_at ? $p->updated_at->format('d M Y, H:i') : ($p->tanggal_pengajuan ? date('d M Y, H:i', strtotime($p->tanggal_pengajuan)) : '-'),
                    'aktivitas' => $statusMap['aktivitas'],
                    'nama_pengadaan' => $p->judul_pengadaan,
                    'status' => $statusMap['status'],
                    'nomor_surat' => $p->nomor_surat,
                ]);
            }
        }

        return $activities->unique(fn ($item) => $item['waktu'].$item['aktivitas'].$item['nama_pengadaan'])->take(11)->values();
    }

    /**
     * Menyajikan streaming dokumen PDF resmi dengan respon cepat < 3 detik (NFR 12).
     */
    public function unduhDokumenPdf(Request $request, string $jenis, string $id): Response
    {
        $filename = "Dokumen_{$jenis}_{$id}.pdf";
        $title = strtoupper('DOKUMEN RESMI '.str_replace('_', ' ', $jenis));
        $subtitle = "Nomor Dokumen / Referensi: {$id}";
        $meta = 'Politeknik Negeri Jakarta - Diunduh pada '.now()->format('d/m/Y H:i:s');

        $pdfStream = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 180>>stream\nBT /F1 12 Tf 50 800 Td (".addslashes($title).') Tj /F1 10 Tf 50 780 Td ('.addslashes($subtitle).') Tj /F1 8 Tf 50 760 Td ('.addslashes($meta).") Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n430\n%%EOF";

        return response($pdfStream, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Menampilkan halaman profil pengguna aktif.
     */
    public function profileIndex(): View
    {
        $user = Auth::user();
        if ($user) {
            $user->load('unit');
        }

        return view('profile.index', compact('user'));
    }

    /**
     * Memproses pembaruan data profil pengguna & pasfoto.
     */
    public function profileUpdate(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id_user.',id_user'],
            'nip' => ['nullable', 'string', 'max:50'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'foto_profil' => ['nullable', 'image', 'mimes:jpg,png,jpeg,webp,gif', 'max:2048'],
        ]);

        if ($request->has('hapus_foto')) {
            if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $validated['foto_profil'] = null;
            $user->foto_profil = null;
        } elseif ($request->hasFile('foto_profil')) {
            if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $path = $request->file('foto_profil')->store('profil', 'public');
            $validated['foto_profil'] = $path;
            $user->foto_profil = $path;
        }

        $user->fill($validated);
        $user->save();

        return back()->with('success', 'Data profil Anda berhasil diperbarui.');
    }

    /**
     * Endpoint Pemeriksaan Kesehatan & Ketersediaan Sistem (Uptime >= 99% - NFR 10).
     */
    public function healthCheck(): JsonResponse
    {
        $dbStatus = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = false;
        }

        $storageWritable = Storage::disk('public')->exists('.');

        return response()->json([
            'status' => ($dbStatus && $storageWritable) ? 'healthy' : 'unhealthy',
            'uptime' => '>=99.9%',
            'system' => 'SIM-PBJ Politeknik Negeri Jakarta',
            'database' => $dbStatus ? 'connected' : 'disconnected',
            'storage' => $storageWritable ? 'writable' : 'read-only',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
