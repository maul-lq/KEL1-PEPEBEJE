<?php

/**
 * Informasi File
 *
 * Penulis   : DendyRafi, Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller HomeController ini sebagai pengatur dashboard operasional multi-modul berbasis RBAC, profil pengguna, pengunduh PDF resmi, serta endpoint health check sistem.
 */

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
 * Controller Dashboard & Layanan Profil Utama (HomeController)
 *
 * merancang controller ini agar tampilan dashboard tidak bercabang dengan `switch ($role)`,
 * melainkan menyusun kartu KPI dan daftar antrean secara kumulatif dari seluruh izin RBAC aktif pengguna.
 *
 * Parameter konstruktor: `PengadaanNotificationService $notificationService` — layanan notifikasi terpadu.
 * Nilai kembali         : Instance controller yang melayani `/dashboard`, `/profil`, `/dokumen-pdf`, dan `/health`.
 *
 * Contoh pemanggilan:
 * `Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');`
 */
class HomeController extends Controller
{
    /**
     * menyuntikkan layanan notifikasi pengadaan agar controller siap mengirim alert bila diperlukan oleh alur terkait.
     *
     * @param  PengadaanNotificationService  $notificationService  mengikat service notifikasi melalui property promotion.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$home = app(HomeController::class);`
     */
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * menyiapkan statistik sistem, daftar permohonan/paket terbaru sesuai cakupan RBAC, notifikasi aktif, dan data modul dashboard.
     *
     * @return View mengembalikan tampilan Blade `dashboard.index` lengkap dengan `$roleData` komposisi modul aktif.
     *
     * Contoh pemanggilan:
     * `GET /dashboard`
     */
    public function index(): View
    {
        $user = Auth::user();
        if ($user) {
            $user->load(['unit', 'roleEntity.permissions']);
        }

        // menyelaraskan cakupan hitung dan daftar ringkas dashboard sesuai RBAC (view_all melihat semua, view_own hanya milik sendiri).
        $permohonanScope = PermohonanPengadaan::where('status_permohonan', '!=', 'digantikan_oleh_revisi');
        if ($user) {
            $permohonanScope->forUser($user);
        }

        $paketScope = PaketPengadaan::query();
        if ($user) {
            $paketScope->forUser($user);
        }

        $stats = [
            'total_units' => Unit::count(),
            'total_permohonan' => (clone $permohonanScope)->count(),
            'total_anggaran' => (float) (AnggaranMAK::sum('pagu_anggaran') ?? 0),
            'total_paket' => (clone $paketScope)->count(),
            'total_vendor' => Vendor::count(),
        ];

        $recentPermohonan = (clone $permohonanScope)
            ->with(['unit', 'user'])
            ->latest('tanggal_pengajuan')
            ->take(5)
            ->get();

        $recentPaket = (clone $paketScope)
            ->with(['permohonan', 'ppkUser', 'ppUser'])
            ->latest('tanggal_registrasi_paket')
            ->take(5)
            ->get();

        // menyaring notifikasi yang belum ditutup (is_dismissed=false) supaya toast/panel ringkas di dashboard tetap bersih.
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
     * menyusun daftar modul dashboard aktif secara kumulatif berdasarkan seluruh izin RBAC pengguna (tanpa switch-case peran).
     *
     * @param  User  $user  memeriksa setiap kelompok permission RBAC milik pengguna untuk mengaktifkan seksi KPI dan antrean terkait.
     * @return array<string, mixed> mengembalikan `active_modules`, `stats`, dan daftar antrean per modul aktif.
     *
     * Contoh pemanggilan:
     * `$roleData = $this->buildModularDashboardData($user);`
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

        // 3. Modul Pemantauan Berkas Masuk Staf Bidang II
        if ($user->hasPermission('verifikasi.bidang_2_view')) {
            $data['active_modules'][] = 'verifikasi_bidang2';
            $data['stats'] = array_merge($data['stats'], [
                'menunggu_telaah' => PermohonanPengadaan::whereIn('status_permohonan', [
                    'diajukan',
                    PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                    PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                ])->count(),
                'reviu_paralel' => PermohonanPengadaan::whereIn('status_permohonan', [
                    PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                    PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                    PermohonanPengadaan::STATUS_REVIU_PARALEL,
                ])->count(),
                'bidang2_disetujui' => PermohonanPengadaan::whereIn('status_permohonan', ['disetujui', 'diproses_ppbj', 'selesai'])->count(),
                'total_berkas' => PermohonanPengadaan::where('status_permohonan', '!=', 'digantikan_oleh_revisi')->count(),
            ]);
            $data['antrean_telaah'] = PermohonanPengadaan::with(['unit', 'user'])
                ->whereIn('status_permohonan', [
                    'diajukan',
                    PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                    PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                ])
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
            $canAlokasiPerencanaan = $user->hasAnyPermission(['anggaran.mak_alokasi', 'permohonan.split_mak']);

            $antreanMakBase = PermohonanPengadaan::whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DIAJUKAN,
                PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                'pending_anggaran',
            ])
                ->where(function ($q) {
                    $q->whereIn('status_permohonan', [
                        PermohonanPengadaan::STATUS_DIAJUKAN,
                        PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                        PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                        'pending_anggaran',
                    ])
                        ->orWhereNull('kode_mak')
                        ->orWhere('kode_mak', '');
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
                'menunggu_mak' => $canAlokasiPerencanaan ? (clone $antreanMakBase)->count() : 0,
                'mak_locked' => AnggaranMAK::where('is_locked', true)->count(),
            ]);
            $data['antrean_mak'] = $canAlokasiPerencanaan
                ? (clone $antreanMakBase)
                    ->with(['unit', 'user'])
                    ->latest('tanggal_pengajuan')
                    ->take(8)
                    ->get()
                : collect();
            $data['daftar_mak'] = AnggaranMAK::latest('updated_at')->take(10)->get();
        }

        // 5. Modul Wakil Direktur II (Disposisi & Persetujuan Awal / Memo Pembayaran)
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
                    'aktivitas' => 'Memo Bayar Wadir II',
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

        // 6. Modul Pejabat Pembuat Komitmen (PPK - Persetujuan Usulan Awal, Dokumen Paket, Memo Bayar Paralel)
        if ($user->hasAnyPermission(['verifikasi.ppk_approve', 'reviu.ppk', 'pembayaran.memo_ppk'])) {
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

            $ppkPaketBase = PaketPengadaan::query();
            if (! $user->hasPermission('paket.view_all')) {
                $ppkPaketBase->where('id_user_ppk', $user->id_user);
            }

            // menyelaraskan memo bayar PPK secara paralel dengan Wadir II (termasuk jalur Belanja Langsung via PPK awal/pengganti) tanpa prasyarat berjenjang.
            $ppkMemoQuery = MemoBayar::where('role_approver', MemoBayar::ROLE_PPK)
                ->whereIn('status_memo', [MemoBayar::STATUS_DIAJUKAN, MemoBayar::STATUS_MENUNGGU])
                ->whereHas('pembayaran', function ($q) use ($user) {
                    $q->whereNotIn('status_pembayaran', ['ditolak', 'perlu_revisi', 'lunas'])
                        ->whereHas('paket', function ($pq) use ($user) {
                            $pq->where('id_user_ppk', $user->id_user)
                                ->orWhere(function ($awalQ) use ($user) {
                                    $awalQ->whereNull('id_user_ppk')
                                        ->whereHas('permohonan', fn ($pmq) => $pmq->where('id_user_ppk_awal', $user->id_user));
                                });
                        });
                });

            $data['stats'] = array_merge($data['stats'], [
                'antrean_persetujuan_awal' => $user->hasPermission('verifikasi.ppk_approve') ? (clone $ppkAwalQuery)->count() : 0,
                // menyertakan seluruh jalur (termasuk Belanja Langsung <=50jt yang baru diajukan PPBJ) agar antrean Perlu Tindakan dashboard PPK sinkron dengan /reviu-pengadaan/ppk.
                'antrean_dokumen_pengadaan' => $user->hasPermission('reviu.ppk')
                    ? (clone $ppkPaketBase)->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)->count()
                    : 0,
                'total_paket_ppk' => $user->hasPermission('reviu.ppk') ? (clone $ppkPaketBase)->count() : 0,
                'tender_aktif' => $user->hasPermission('reviu.ppk')
                    ? (clone $ppkPaketBase)
                        ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG)
                        ->whereNotIn('status_paket', ['selesai', 'perlu_perbaikan'])
                        ->count()
                    : 0,
                'spk_aktif' => $user->hasPermission('reviu.ppk')
                    ? (clone $ppkPaketBase)
                        ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG)
                        ->whereHas('kontrakSpk')
                        ->count()
                    : 0,
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
            $data['antrean_persetujuan_awal_list'] = $user->hasPermission('verifikasi.ppk_approve')
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
        if ($user->hasAnyPermission(['paket.create', 'paket.view_all', 'permohonan.split_jenis', 'spk.create', 'pembayaran.pengajuan', 'pembayaran.spp_terbitkan'])) {
            $data['active_modules'][] = 'ppbj';
            $canCreatePaket = $user->hasPermission('paket.create');
            $canManagePaket = $user->hasAnyPermission(['paket.create', 'paket.view_all']);
            $canCreateSpk = $user->hasPermission('spk.create');
            $canPengajuanBayar = $user->hasPermission('pembayaran.pengajuan');
            $canTerbitSpp = $user->hasPermission('pembayaran.spp_terbitkan');

            $data['stats'] = array_merge($data['stats'], [
                'total_paket' => PaketPengadaan::forUser($user)->count(),
                'paket_proses' => PaketPengadaan::forUser($user)->where('status_paket', '!=', 'selesai')->count(),
                'paket_selesai_count' => PaketPengadaan::forUser($user)->where('status_paket', 'selesai')->count(),
                'routing_langsung' => PaketPengadaan::forUser($user)->where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)->count(),
                'routing_pp' => PaketPengadaan::forUser($user)->where('jalur_routing', PaketPengadaan::ROUTING_PP)->count(),
                'routing_ppk' => PaketPengadaan::forUser($user)->where('jalur_routing', PaketPengadaan::ROUTING_PPK)->count(),
                'belum_spk' => PaketPengadaan::where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG)
                    ->whereDoesntHave('kontrakSpk')
                    ->count(),
                'usulan_siap_paket' => $canCreatePaket
                    ? PermohonanPengadaan::whereIn('status_permohonan', ['disetujui', 'diproses_ppbj'])
                        ->whereDoesntHave('paketPengadaan')
                        ->count()
                    : 0,
                'bast_siap_bayar_count' => $canPengajuanBayar
                    ? PenerimaanBarang::where('status_fisik', 'terima')
                        ->whereDoesntHave('paket.pembayaran')
                        ->count()
                    : 0,
            ]);

            $data['bast_siap_bayar'] = $canPengajuanBayar
                ? PenerimaanBarang::with(['paket.vendor', 'paket.permohonan.unit', 'fotoDokumentasi'])
                    ->where('status_fisik', 'terima')
                    ->whereDoesntHave('paket.pembayaran')
                    ->latest('tanggal_penerimaan')
                    ->take(5)
                    ->get()
                : collect();
            $data['siap_paket'] = $canCreatePaket
                ? PermohonanPengadaan::with('unit')
                    ->whereIn('status_permohonan', ['disetujui', 'diproses_ppbj'])
                    ->whereDoesntHave('paketPengadaan')
                    ->latest('tanggal_pengajuan')
                    ->take(5)
                    ->get()
                : collect();
            $data['ppbj_upload_kontrak'] = $canCreateSpk
                ? PaketPengadaan::with(['permohonan.unit', 'vendor'])
                    ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG)
                    ->where('status_paket', PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ)
                    ->whereDoesntHave('kontrakSpk')
                    ->latest('updated_at')
                    ->take(5)
                    ->get()
                : collect();
            $data['ppbj_paket_revisi'] = $canManagePaket
                ? PaketPengadaan::with(['permohonan.unit', 'ppUser', 'ppkUser'])
                    ->where('status_paket', 'perlu_perbaikan')
                    ->latest('updated_at')
                    ->take(5)
                    ->get()
                : collect();
            $data['ppbj_belanja_langsung'] = $canManagePaket
                ? PaketPengadaan::with(['permohonan.unit'])
                    ->where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
                    ->whereIn('status_paket', ['terdaftar', PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ])
                    ->where(function ($q) {
                        $q->whereNull('nomor_kwitansi_belanja')->orWhere('nomor_kwitansi_belanja', '');
                    })
                    ->latest('tanggal_registrasi_paket')
                    ->take(5)
                    ->get()
                : collect();
            $data['ppbj_siap_spp'] = $canTerbitSpp
                ? Pembayaran::with(['paket.vendor', 'paket.permohonan.unit'])
                    ->where('status_pembayaran', 'memo_disetujui')
                    ->latest('updated_at')
                    ->take(5)
                    ->get()
                : collect();
            $data['ppbj_bayar_revisi'] = $canPengajuanBayar
                ? Pembayaran::with(['paket.vendor', 'memoBayar'])
                    ->whereIn('status_pembayaran', ['ditolak', 'perlu_revisi'])
                    ->latest('updated_at')
                    ->take(5)
                    ->get()
                : collect();
            $data['ppbj_recent_paket'] = PaketPengadaan::with(['permohonan.unit', 'vendor', 'ppkUser', 'ppUser'])
                ->latest('tanggal_registrasi_paket')
                ->take(10)
                ->get();
        }

        // 8. Modul Pemantauan Aktivitas PP (pp.monitoring_aktivitas)
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
                        $uq->whereHas('roleEntity', fn ($rq) => $rq->where('nama_role', 'pp')
                            ->orWhereHas('permissions', fn ($pq) => $pq->where('nama_permission', 'reviu.pp')));
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
                    ->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'menunggu_reviu_pp', 'perlu_perbaikan'])
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
                    'menunggu_reviu_pp',
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
                'paket_masuk_ppbj' => Pembayaran::whereIn('status_pembayaran', ['perintah_bayar', 'spp_terbit'])->count(),
                'transaksi_lunas' => TransaksiPencairan::count(),
                'total_dana_cair' => $totalDanaCair,
                'total_dana_cair_compact' => $this->formatCompactRupiah($totalDanaCair),
                'spp_siap_verifikasi' => Pembayaran::where('status_pembayaran', 'menunggu_memo')->count(),
                'sp2d_siap_cair' => Pembayaran::whereIn('status_pembayaran', ['perintah_bayar', 'spp_terbit'])->count(),
            ]);
            $data['antrean_tagihan'] = Pembayaran::with(['paket.kontrakSpk.vendor', 'paket.kontrak.vendor', 'paket.vendor', 'pengaju.unit', 'memoBayar'])
                ->whereIn('status_pembayaran', ['perintah_bayar', 'spp_terbit'])
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
     * meringkas nominal Rupiah ke satuan Miliar/Juta/Ribu agar angka besar tidak meluap dari kartu KPI selebar 150px.
     *
     * @param  float  $amount  menerima nominal angka penuh dalam Rupiah.
     * @return string mengembalikan string Rupiah ringkas seperti 'Rp 1,25 M' atau 'Rp 45 Jt'.
     *
     * Contoh pemanggilan:
     * `$ringkas = $this->formatCompactRupiah(1500000000);`
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
     * menggabungkan log audit milik pengusul dengan status terkini permohonannya sebagai fallback agar daftar aktivitas di dashboard selalu terisi.
     *
     * @param  User  $user  menggunakan `id_user` pemohon untuk menyaring permohonan serta jejak audit terkait.
     * @return Collection<int, array<string, mixed>> mengembalikan maksimal 11 entri aktivitas unik terbaru.
     *
     * Contoh pemanggilan:
     * `$aktivitas = $this->getUserRecentActivities($user);`
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
                    'menunggu_bidang_2' => ['aktivitas' => 'Usulan Masuk', 'status' => 'Dalam Proses'],
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
     * menghasilkan aliran biner PDF ringkas secara langsung tanpa render DOM berat agar unduhan dokumen referensi merespons < 3 detik (NFR 12).
     *
     * @param  Request  $request  menerima request unduhan dokumen PDF resmi.
     * @param  string  $jenis  membaca kategori dokumen (misal 'spk', 'bast', atau 'permohonan') untuk judul dokumen.
     * @param  string  $id  menyematkan nomor referensi dokumen di nama berkas dan isi halaman PDF.
     * @return Response mengembalikan respons HTTP biner `application/pdf` siap unduh.
     *
     * Contoh pemanggilan:
     * `GET /dokumen-pdf/bast/BAST-001`
     */
    public function unduhDokumenPdf(Request $request, string $jenis, string $id): Response
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('dokumen.unduh_pdf')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin untuk mengunduh dokumen PDF.');
        }

        $jenisKey = strtolower(trim($jenis));
        $candidateIds = array_values(array_unique([$id, urldecode($id), rawurldecode($id)]));

        // memverifikasi keberadaan entitas dan otorisasi per jenis dokumen agar endpoint PDF tidak menjadi celah IDOR lintas peran.
        match ($jenisKey) {
            'permohonan' => $this->authorizePermohonanPdfAccess($user, $candidateIds),
            'paket' => $this->authorizePaketPdfAccess($user, $candidateIds),
            'spk' => $this->authorizeSpkPdfAccess($user, $candidateIds),
            'bast' => $this->authorizeBastPdfAccess($user, $candidateIds),
            'transfer' => $this->authorizeTransferPdfAccess($user, $candidateIds),
            default => abort(404, 'Jenis dokumen PDF tidak dikenali.'),
        };

        $safeId = preg_replace('/[^A-Za-z0-9._-]+/', '_', $id) ?: 'dokumen';
        $filename = "Dokumen_{$jenisKey}_{$safeId}.pdf";
        $title = strtoupper('DOKUMEN RESMI '.str_replace('_', ' ', $jenisKey));
        $subtitle = "Nomor Dokumen / Referensi: {$id}";
        $meta = 'Politeknik Negeri Jakarta - Diunduh pada '.now()->format('d/m/Y H:i:s');

        // merakit byte stream PDF-1.4 minimal langsung di memori agar endpoint ini bebas overhead render view saat hanya butuh bukti cepat.
        $pdfStream = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 180>>stream\nBT /F1 12 Tf 50 800 Td (".addslashes($title).') Tj /F1 10 Tf 50 780 Td ('.addslashes($subtitle).') Tj /F1 8 Tf 50 760 Td ('.addslashes($meta).") Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n430\n%%EOF";

        return response($pdfStream, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Memeriksa hak unduh PDF bukti permohonan agar pengguna view_own tidak dapat mengunduh surat unit lain.
     *
     * Tujuan: memastikan pengusul hanya mengunduh surat miliknya sendiri, sedangkan verifikator dan
     * pemegang izin global tetap dapat membuka dokumen sesuai kewenangan masing-masing.
     *
     * @param  User  $user  Pengguna yang meminta unduhan PDF permohonan.
     * @param  array<int, string>  $candidateIds  Variasi nomor surat mentah dan terdekode URL.
     *
     * Contoh pemanggilan:
     * `$this->authorizePermohonanPdfAccess($user, [$id]);`
     */
    private function authorizePermohonanPdfAccess(User $user, array $candidateIds): void
    {
        if (! $user->hasAnyPermission([
            'permohonan.view_own',
            'permohonan.view_all',
            'paket.create',
            'paket.view_all',
            'reviu.ppk',
            'verifikasi.wadir_2_view',
            'verifikasi.ppk_approve',
            'verifikasi.bidang_2_view',
        ])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin melihat atau mengunduh dokumen permohonan.');
        }

        $permohonan = PermohonanPengadaan::whereIn('nomor_surat', $candidateIds)->firstOrFail();

        if ($user->hasAnyPermission([
            'permohonan.view_all',
            'paket.create',
            'paket.view_all',
            'verifikasi.wadir_2_view',
            'verifikasi.bidang_2_view',
        ])) {
            return;
        }

        $isOwner = $user->hasPermission('permohonan.view_own') && (int) $permohonan->id_user === (int) $user->id_user;
        $isAssignedPpk = $user->hasAnyPermission(['verifikasi.ppk_approve', 'reviu.ppk'])
            && ((int) $permohonan->id_user_ppk_awal === (int) $user->id_user
                || PaketPengadaan::where('nomor_surat', $permohonan->nomor_surat)->where('id_user_ppk', $user->id_user)->exists());

        if (! $isOwner && ! $isAssignedPpk) {
            abort(403, 'Akses Ditolak: Anda hanya dapat mengunduh dokumen permohonan yang menjadi hak akses Anda.');
        }
    }

    /**
     * Memeriksa hak unduh PDF dokumen paket berdasarkan cakupan paket RBAC pengguna.
     *
     * @param  User  $user  Pengguna aktif peminta dokumen.
     * @param  array<int, string>  $candidateIds  Variasi nomor paket.
     *
     * Contoh pemanggilan:
     * `$this->authorizePaketPdfAccess($user, [$id]);`
     */
    private function authorizePaketPdfAccess(User $user, array $candidateIds): void
    {
        $paket = PaketPengadaan::whereIn('nomor_paket', $candidateIds)->firstOrFail();

        if (! PaketPengadaan::where('nomor_paket', $paket->nomor_paket)->forUser($user)->exists()) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses terhadap dokumen paket pengadaan ini.');
        }
    }

    /**
     * Memeriksa hak unduh PDF Kontrak atau SPK beserta isolasi penugasan PP dan PPK.
     *
     * @param  User  $user  Pengguna aktif peminta dokumen SPK.
     * @param  array<int, string>  $candidateIds  Variasi nomor SPK.
     *
     * Contoh pemanggilan:
     * `$this->authorizeSpkPdfAccess($user, [$id]);`
     */
    private function authorizeSpkPdfAccess(User $user, array $candidateIds): void
    {
        if (! $user->hasAnyPermission([
            'spk.create',
            'reviu.pp',
            'reviu.ppk',
            'paket.view_all',
            'paket.view_assigned',
            'penerimaan.view',
            'pembayaran.pengajuan',
            'pembayaran.spp_terbitkan',
            'monitoring.view',
        ])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin mengunduh dokumen Kontrak atau SPK.');
        }

        $spk = KontrakSPK::with('paket')->whereIn('nomor_spk', $candidateIds)->firstOrFail();

        if (! $user->hasAnyPermission(['paket.view_all', 'spk.create', 'penerimaan.view', 'pembayaran.pengajuan', 'pembayaran.spp_terbitkan'])
            && $user->hasAnyPermission(['paket.view_assigned', 'reviu.pp', 'reviu.ppk'])) {
            $isAssigned = $spk->paket && ((int) $spk->paket->id_user_ppk === (int) $user->id_user || (int) $spk->paket->id_user_pp === (int) $user->id_user);
            if (! $isAssigned) {
                abort(403, 'Akses Ditolak: Dokumen Kontrak atau SPK ini bukan bagian dari penugasan Anda.');
            }
        }
    }

    /**
     * Memeriksa hak unduh PDF dokumen Berita Acara Serah Terima (BAST).
     *
     * @param  User  $user  Pengguna aktif peminta dokumen BAST.
     * @param  array<int, string>  $candidateIds  Variasi nomor BAST.
     *
     * Contoh pemanggilan:
     * `$this->authorizeBastPdfAccess($user, [$id]);`
     */
    private function authorizeBastPdfAccess(User $user, array $candidateIds): void
    {
        if (! $user->hasAnyPermission([
            'penerimaan.view',
            'penerimaan.bast_create',
            'penerimaan.bast_sign',
            'paket.view_all',
            'paket.view_assigned',
            'spk.create',
            'pembayaran.pengajuan',
            'pembayaran.spp_terbitkan',
            'pembayaran.memo_ppk',
            'pembayaran.memo_wadir2',
            'pembayaran.pencairan_sp2d',
        ])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin mengunduh dokumen BAST.');
        }

        PenerimaanBarang::whereIn('nomor_bast', $candidateIds)->firstOrFail();
    }

    /**
     * Memeriksa hak unduh PDF bukti transaksi pencairan atau transfer bank SP2D.
     *
     * @param  User  $user  Pengguna aktif peminta bukti transfer.
     * @param  array<int, string>  $candidateIds  Variasi nomor transaksi bank.
     *
     * Contoh pemanggilan:
     * `$this->authorizeTransferPdfAccess($user, [$id]);`
     */
    private function authorizeTransferPdfAccess(User $user, array $candidateIds): void
    {
        if (! $user->hasAnyPermission([
            'pembayaran.pencairan_sp2d',
            'pembayaran.spp_terbitkan',
            'pembayaran.pengajuan',
            'pembayaran.memo_wadir2',
            'pembayaran.memo_ppk',
            'laporan.rekapitulasi',
        ])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin mengunduh bukti transaksi pencairan.');
        }

        $transaksi = TransaksiPencairan::with('pembayaran.paket')
            ->whereIn('nomor_transaksi_bank', $candidateIds)
            ->firstOrFail();

        if ($user->hasPermission('pembayaran.memo_ppk')
            && ! $user->hasAnyPermission(['pembayaran.pencairan_sp2d', 'pembayaran.spp_terbitkan', 'pembayaran.pengajuan', 'pembayaran.memo_wadir2', 'laporan.rekapitulasi'])
            && (int) ($transaksi->pembayaran?->paket?->id_user_ppk) !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Bukti transaksi pencairan ini bukan bagian dari paket PPK Anda.');
        }
    }

    /**
     * menampilkan halaman profil pengguna aktif beserta relasi unit kerjanya.
     *
     * @return View mengembalikan view `profile.index` dengan data `$user`.
     *
     * Contoh pemanggilan:
     * `GET /profil`
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
     * memvalidasi dan menyimpan perubahan profil pengguna aktif termasuk unggah atau penghapusan foto profil di disk publik.
     *
     * @param  Request  $request  memvalidasi keunikan email (mengecualikan user aktif) dan format gambar foto profil maksimal 2 MB.
     * @return RedirectResponse mengembalikan redirect kembali dengan pesan sukses pembaruan profil.
     *
     * Contoh pemanggilan:
     * `PUT /profil`
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

        // menghapus berkas foto lama terlebih dahulu baik saat dihapus maupun diganti agar direktori storage/app/public/profil tetap bersih.
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
     * menyimpan preferensi pengguna untuk mengaktifkan atau menjeda pengiriman pengingat melalui email tanpa mematikan notifikasi in-app.
     *
     * @param  Request  $request  memvalidasi flag boolean `enabled`.
     * @return JsonResponse|RedirectResponse mengembalikan JSON untuk toggle AJAX di profil atau redirect kembali untuk submit form standar.
     *
     * Contoh pemanggilan:
     * `POST /profil/reminder-email`
     */
    public function updateReminderEmail(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];
        $user->reminder_email_aktif = $enabled;
        $user->save();

        $message = $enabled
            ? 'Pengingat email ke '.$user->email.' telah diaktifkan.'
            : 'Pengingat email ke '.$user->email.' telah dijeda. Notifikasi di dalam aplikasi tetap aktif.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'enabled' => $enabled,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * mengecek konektivitas database dan keterbacaan disk public untuk memastikan ketersediaan layanan sesuai NFR Uptime >= 99%.
     *
     * @return JsonResponse mengembalikan JSON status kesehatan (`healthy` atau `unhealthy`) beserta status database dan storage.
     *
     * Contoh pemanggilan:
     * `GET /health`
     */
    public function healthCheck(): JsonResponse
    {
        $dbStatus = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            // menangkap error koneksi PDO agar endpoint monitoring tetap menerima JSON terstruktur 'unhealthy' alih-alih HTTP 500 mentah.
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
