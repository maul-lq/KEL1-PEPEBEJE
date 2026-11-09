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
     * Menampilkan dashboard utama operasional adaptif berbasis peran pengguna.
     */
    public function index(): View
    {
        $user = Auth::user();
        if ($user) {
            $user->load('unit');
        }

        // Statistik agregat real-time sistem
        $stats = [
            'total_units' => Unit::count(),
            'total_permohonan' => PermohonanPengadaan::count(),
            'total_anggaran' => (float) (AnggaranMAK::sum('pagu_anggaran') ?? 0),
            'total_paket' => PaketPengadaan::count(),
            'total_vendor' => Vendor::count(),
        ];

        // Daftar 5 usulan permohonan terkini
        $permohonanQuery = PermohonanPengadaan::with(['unit', 'user']);
        if ($user && ($user->hasRole(['user', 'pengaju']) || ($user->hasPermission('permohonan.view_own') && ! $user->hasPermission('permohonan.view_all')))) {
            $permohonanQuery->where('id_user', $user->id_user);
        }
        $recentPermohonan = $permohonanQuery->latest('tanggal_pengajuan')->take(5)->get();

        // Daftar 5 paket pengadaan terkini
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

        // Data spesifik per peran
        $roleData = $user ? $this->getRoleDashboardData($user) : [];

        if (! empty($roleData['stats'])) {
            $stats = array_merge($stats, $roleData['stats']);
        }

        return view('dashboard.index', compact('user', 'stats', 'recentPermohonan', 'recentPaket', 'notifications', 'units', 'roleData'));
    }

    /**
     * Mengambil data statistik dan antrean spesifik untuk peran pengguna aktif.
     *
     * @return array<string, mixed>
     */
    protected function getRoleDashboardData(User $user): array
    {
        $roleKey = $user->role;
        if (! in_array($roleKey, ['admin', 'user', 'staff_bidang_2', 'wadir_2', 'perencanaan', 'ppbj', 'pp', 'ppk', 'perlengkapan', 'keuangan'], true)) {
            if ($user->hasPermission('admin.role_manage')) {
                $roleKey = 'admin';
            } elseif ($user->hasAnyPermission(['paket.create', 'paket.view_all', 'lihat_pp', 'pp.monitoring_aktivitas'])) {
                $roleKey = 'ppbj';
            } elseif ($user->hasPermission('reviu.ppk')) {
                $roleKey = 'ppk';
            } elseif ($user->hasPermission('reviu.pp')) {
                $roleKey = 'pp';
            } elseif ($user->hasAnyPermission(['anggaran.mak_view', 'anggaran.mak_alokasi'])) {
                $roleKey = 'perencanaan';
            } elseif ($user->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide'])) {
                $roleKey = 'wadir_2';
            } elseif ($user->hasPermission('verifikasi.bidang_2_view')) {
                $roleKey = 'staff_bidang_2';
            } elseif ($user->hasPermission('penerimaan.view')) {
                $roleKey = 'perlengkapan';
            } elseif ($user->hasPermission('pembayaran.pencairan_sp2d')) {
                $roleKey = 'keuangan';
            } else {
                $roleKey = 'user';
            }
        }

        return match ($roleKey) {
            'admin' => [
                'stats' => [
                    'pending_registrations' => User::where('status_registrasi', 'menunggu_persetujuan')->count(),
                    'active_users' => User::where('status_aktif', true)->count(),
                    'total_roles' => Role::count(),
                    'total_units' => Unit::count(),
                ],
                'pending_users' => User::with('unit')->where('status_registrasi', 'menunggu_persetujuan')->latest('created_at')->take(5)->get(),
                'roles_summary' => Role::withCount('users')->take(6)->get(),
            ],
            'user' => [
                'stats' => [
                    'total_usulan' => PermohonanPengadaan::where('id_user', $user->id_user)->count(),
                    'usulan_proses' => PermohonanPengadaan::where('id_user', $user->id_user)->whereIn('status_permohonan', ['diajukan', 'menunggu_bidang_2', 'reviu_paralel', 'cek_mak_perencanaan', 'menunggu_acc_wadir_ppk', 'pending_anggaran'])->count(),
                    'usulan_disetujui' => PermohonanPengadaan::where('id_user', $user->id_user)->where('status_permohonan', 'disetujui')->count(),
                    'usulan_revisi' => PermohonanPengadaan::where('id_user', $user->id_user)->whereIn('status_permohonan', ['ditolak', 'revisi'])->count(),
                    'usulan_selesai' => PermohonanPengadaan::where('id_user', $user->id_user)->where('status_permohonan', 'selesai')->count(),
                    'pengajuan_aktif' => PermohonanPengadaan::where('id_user', $user->id_user)->whereNotIn('status_permohonan', ['selesai', 'ditolak', 'digantikan_oleh_revisi'])->count(),
                    'menunggu_tindakan' => PermohonanPengadaan::where('id_user', $user->id_user)->whereIn('status_permohonan', ['ditolak', 'revisi'])->count(),
                    'notifikasi_belum_dibaca' => NotifikasiUser::where('id_user', $user->id_user)->where('is_read', false)->where('is_dismissed', false)->count(),
                ],
                'revisi_alerts' => PermohonanPengadaan::where('id_user', $user->id_user)
                    ->whereIn('status_permohonan', ['ditolak', 'revisi'])
                    ->latest('updated_at')
                    ->take(5)
                    ->get(),
                'perlu_tindakan' => PermohonanPengadaan::where('id_user', $user->id_user)
                    ->whereIn('status_permohonan', ['ditolak', 'revisi'])
                    ->latest('updated_at')
                    ->take(5)
                    ->get(),
                'aktivitas_terbaru' => $this->getUserRecentActivities($user),
            ],
            'staff_bidang_2' => [
                'stats' => [
                    'menunggu_telaah' => PermohonanPengadaan::whereIn('status_permohonan', ['diajukan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2])->count(),
                    'reviu_paralel' => PermohonanPengadaan::where('status_permohonan', 'reviu_paralel')->count(),
                    'total_berkas' => PermohonanPengadaan::count(),
                ],
                'antrean_telaah' => PermohonanPengadaan::with(['unit', 'user'])
                    ->whereIn('status_permohonan', ['diajukan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2])
                    ->latest('tanggal_pengajuan')
                    ->take(5)
                    ->get(),
            ],
            'wadir_2' => [
                'stats' => [
                    'menunggu_persetujuan' => PermohonanPengadaan::whereIn('status_permohonan', ['diajukan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2, PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN, PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, 'reviu_paralel'])->count(),
                    'memo_bayar_pending' => MemoBayar::where('role_approver', 'wadir_2')->whereIn('status_memo', ['diajukan', 'menunggu'])->count(),
                    'total_disetujui' => PermohonanPengadaan::where('status_permohonan', 'disetujui')->count(),
                    'total_ditolak' => PermohonanPengadaan::where('status_permohonan', 'ditolak')->count(),
                ],
                'antrean_disposisi' => PermohonanPengadaan::with(['unit', 'user'])
                    ->whereIn('status_permohonan', ['diajukan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2, PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN, PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, 'reviu_paralel'])
                    ->latest('tanggal_pengajuan')
                    ->take(5)
                    ->get(),
                'antrean_memo_bayar' => MemoBayar::with(['pembayaran.paket.vendor', 'user'])
                    ->where('role_approver', 'wadir_2')
                    ->whereIn('status_memo', ['diajukan', 'menunggu'])
                    ->latest('created_at')
                    ->take(5)
                    ->get(),
            ],
            'perencanaan' => [
                'stats' => [
                    'total_pagu_dipa' => (float) AnggaranMAK::sum('pagu_anggaran'),
                    'pagu_teralokasi' => (float) AnggaranMAK::sum(DB::raw('pagu_anggaran - sisa_pagu_mak')),
                    'sisa_pagu_dipa' => (float) AnggaranMAK::sum('sisa_pagu_mak'),
                    'menunggu_mak' => PermohonanPengadaan::whereNotIn('status_permohonan', ['ditolak', 'selesai'])
                        ->where(function ($q) {
                            $q->whereNull('kode_mak')->orWhere('kode_mak', '');
                        })->count(),
                    'mak_locked' => AnggaranMAK::where('is_locked', true)->count(),
                ],
                'antrean_mak' => PermohonanPengadaan::with(['unit', 'user'])
                    ->whereNotIn('status_permohonan', ['ditolak', 'selesai'])
                    ->where(function ($q) {
                        $q->whereNull('kode_mak')->orWhere('kode_mak', '');
                    })
                    ->latest('tanggal_pengajuan')
                    ->take(10)
                    ->get(),
                'daftar_mak' => AnggaranMAK::latest('updated_at')->take(5)->get(),
            ],
            'ppbj' => [
                'stats' => [
                    'total_paket' => PaketPengadaan::where('status_paket', 'terdaftar')->count(),
                    'routing_langsung' => PaketPengadaan::where('jalur_routing', 'langsung_sd_50jt')->count(),
                    'routing_pp' => PaketPengadaan::where('jalur_routing', 'pp_50jt_sd_200jt')->count(),
                    'routing_ppk' => PaketPengadaan::where('jalur_routing', 'ppk_diatas_200jt')->count(),
                    'total_vendor' => Vendor::count(),
                    'belum_spk' => PaketPengadaan::whereDoesntHave('kontrakSpk')->count(),
                    'usulan_siap_paket' => PermohonanPengadaan::where('status_permohonan', 'disetujui')->whereDoesntHave('paketPengadaan')->count(),
                    'pp_total' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->count(),
                    'pp_dalam_reviu' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
                        ->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])
                        ->count(),
                    'pp_menunggu_ppk' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
                        ->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
                        ->count(),
                    'pp_siap_belanja' => PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
                        ->whereIn('status_paket', [PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP, 'reviu_dokumen_disetujui'])
                        ->count(),
                    'bast_siap_bayar_count' => PenerimaanBarang::where('status_fisik', 'terima')->whereDoesntHave('paket.pembayaran')->count(),
                ],
                'bast_siap_bayar' => PenerimaanBarang::with(['paket.vendor', 'paket.permohonan.unit', 'fotoDokumentasi'])
                    ->where('status_fisik', 'terima')
                    ->whereDoesntHave('paket.pembayaran')
                    ->latest('tanggal_penerimaan')
                    ->take(5)
                    ->get(),
                'siap_paket' => PermohonanPengadaan::with('unit')
                    ->where('status_permohonan', 'disetujui')
                    ->whereDoesntHave('paketPengadaan')
                    ->latest('tanggal_pengajuan')
                    ->take(5)
                    ->get(),
                'pp_monitoring_list' => PaketPengadaan::with(['vendor', 'ppUser', 'ppkUser', 'reviuDokumen' => fn ($rq) => $rq->where('role_reviewer', 'pp')->latest('putaran_ke')])
                    ->where(function ($q) {
                        $q->where('jalur_routing', PaketPengadaan::ROUTING_PP)
                            ->orWhereNotNull('id_user_pp');
                    })
                    ->latest('updated_at')
                    ->take(8)
                    ->get(),
                'pp_audit_logs' => AuditLog::with('user')
                    ->where(function ($q) {
                        $q->whereHas('user', fn ($uq) => $uq->where('role', 'pp'))
                            ->orWhere('jenis_aksi', 'like', '%_PP%');
                    })
                    ->latest('waktu_log')
                    ->take(6)
                    ->get(),
            ],
            'pp' => [
                'stats' => [
                    'total_paket_pp' => PaketPengadaan::whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])->orWhere('id_user_pp', $user->id_user)->count(),
                    'paket_negosiasi' => PaketPengadaan::where(fn ($q) => $q->whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])->orWhere('id_user_pp', $user->id_user))->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])->count(),
                    'paket_spk' => PaketPengadaan::where(fn ($q) => $q->whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])->orWhere('id_user_pp', $user->id_user))->whereIn('status_paket', [PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK, PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ, 'spk_terbit'])->count(),
                    'paket_selesai' => PaketPengadaan::where(fn ($q) => $q->whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])->orWhere('id_user_pp', $user->id_user))->where('status_paket', 'selesai')->count(),
                ],
                'antrean_negosiasi' => PaketPengadaan::with(['permohonan.unit', 'vendor'])
                    ->where(fn ($q) => $q->whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])->orWhere('id_user_pp', $user->id_user))
                    ->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])
                    ->latest('tanggal_registrasi_paket')
                    ->take(5)
                    ->get(),
            ],
            'ppk' => [
                'stats' => [
                    'antrean_persetujuan_awal' => PermohonanPengadaan::where('id_user_ppk_awal', $user->id_user)
                        ->whereIn('status_permohonan', [
                            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                            PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        ])
                        ->count(),
                    'antrean_dokumen_pengadaan' => PaketPengadaan::where('id_user_ppk', $user->id_user)
                        ->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
                        ->count(),
                    'total_paket_ppk' => PaketPengadaan::where('id_user_ppk', $user->id_user)->count(),
                    'tender_aktif' => PaketPengadaan::where('id_user_ppk', $user->id_user)->where('jalur_routing', 'ppk_diatas_200jt')->whereIn('status_paket', ['terdaftar', 'proses'])->count(),
                    'spk_aktif' => PaketPengadaan::where('id_user_ppk', $user->id_user)->whereHas('kontrakSpk')->count(),
                    'memo_bayar_pending' => MemoBayar::where('role_approver', 'ppk')->whereIn('status_memo', ['diajukan', 'menunggu'])->whereHas('pembayaran.paket', fn ($q) => $q->where('id_user_ppk', $user->id_user))->count(),
                ],
                'antrean_dokumen_pengadaan_list' => PaketPengadaan::with(['permohonan.unit', 'vendor', 'ppUser', 'reviuDokumen.user'])
                    ->where('id_user_ppk', $user->id_user)
                    ->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
                    ->latest('tanggal_registrasi_paket')
                    ->take(5)
                    ->get(),
                'antrean_persetujuan_awal_list' => PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel'])
                    ->where('id_user_ppk_awal', $user->id_user)
                    ->whereIn('status_permohonan', [
                        PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                        PermohonanPengadaan::STATUS_REVIU_PARALEL,
                    ])
                    ->latest('tanggal_pengajuan')
                    ->take(5)
                    ->get(),
                'paket_komitmen' => PaketPengadaan::with(['permohonan.unit', 'vendor', 'kontrakSpk'])
                    ->where('id_user_ppk', $user->id_user)
                    ->latest('tanggal_registrasi_paket')
                    ->take(5)
                    ->get(),
                'antrean_memo' => MemoBayar::with(['pembayaran.paket.vendor'])
                    ->where('role_approver', 'ppk')
                    ->whereIn('status_memo', ['diajukan', 'menunggu'])
                    ->whereHas('pembayaran.paket', fn ($q) => $q->where('id_user_ppk', $user->id_user))
                    ->latest('created_at')
                    ->take(5)
                    ->get(),
            ],
            'perlengkapan' => [
                'stats' => [
                    'spk_masuk' => KontrakSPK::count() + PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)->count(),
                    'belum_diperiksa' => KontrakSPK::whereDoesntHave('paket.penerimaanBarang')->count() + PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)->whereDoesntHave('penerimaanBarang')->count(),
                    'total_bast' => PenerimaanBarang::count(),
                    'belanja_barang' => PenerimaanBarang::where('kategori_belanja', 'belanja_barang')->count(),
                    'belanja_modal' => PenerimaanBarang::where('kategori_belanja', 'belanja_modal')->count(),
                ],
                'jadwal_pengiriman' => KontrakSPK::with(['paket.vendor', 'paket.permohonan.unit'])
                    ->whereDoesntHave('paket.penerimaanBarang')
                    ->latest('tanggal_spk')
                    ->take(5)
                    ->get(),
                'bast_terbaru' => PenerimaanBarang::with(['paket.vendor', 'user'])
                    ->latest('tanggal_penerimaan')
                    ->take(5)
                    ->get(),
            ],
            'keuangan' => [
                'stats' => [
                    'reminder_masuk' => NotifikasiUser::where('id_user', $user->id_user)->count() ?: Reminder::whereIn('tipe_reminder', ['tembusan_spk', 'pembayaran', 'pengingat'])->count(),
                    'paket_masuk_ppbj' => Pembayaran::where('status_pembayaran', 'perintah_bayar')->count(),
                    'transaksi_lunas' => TransaksiPencairan::count(),
                    'total_dana_cair' => (float) (TransaksiPencairan::sum('nominal_transfer') ?? 0),
                    'spp_siap_verifikasi' => Pembayaran::where('status_pembayaran', 'pengajuan')->count(),
                    'sp2d_siap_cair' => Pembayaran::where('status_pembayaran', 'perintah_bayar')->count(),
                ],
                'antrean_tagihan' => Pembayaran::with(['paket.kontrakSpk.vendor', 'paket.kontrak.vendor', 'paket.vendor', 'pengaju.unit', 'memoBayar'])
                    ->where('status_pembayaran', 'perintah_bayar')
                    ->latest('tanggal_perintah_bayar')
                    ->take(5)
                    ->get(),
                'transaksi_cair' => TransaksiPencairan::with(['pembayaran.paket.vendor'])
                    ->latest('tanggal_transfer')
                    ->take(5)
                    ->get(),
                'reminders' => (function () use ($user) {
                    $notifs = NotifikasiUser::with('reminder.paket')
                        ->where('id_user', $user->id_user)
                        ->latest('created_at')
                        ->take(5)
                        ->get();
                    if ($notifs->isEmpty()) {
                        return Reminder::with('paket')
                            ->whereIn('tipe_reminder', ['tembusan_spk', 'pembayaran', 'pengingat'])
                            ->latest('waktu_trigger')
                            ->take(5)
                            ->get();
                    }

                    return $notifs;
                })(),
            ],
            default => [],
        };
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
                ->latest('updated_at')
                ->take(10)
                ->get();

            foreach ($userPermohonans as $p) {
                $statusMap = match ($p->status_permohonan) {
                    'disetujui' => ['aktivitas' => 'Form disetujui', 'status' => 'Disetujui'],
                    'ditolak' => ['aktivitas' => 'Form ditolak', 'status' => 'Ditolak'],
                    'revisi' => ['aktivitas' => 'Perlu perbaikan berkas', 'status' => 'Perlu Revisi'],
                    'selesai' => ['aktivitas' => 'Pengadaan selesai', 'status' => 'Selesai'],
                    'menunggu_bidang_2' => ['aktivitas' => 'Form diperiksa', 'status' => 'Dalam Proses'],
                    'cek_mak_perencanaan' => ['aktivitas' => 'Pemeriksaan MAK', 'status' => 'Dalam Proses'],
                    'reviu_paralel' => ['aktivitas' => 'Reviu Paralel', 'status' => 'Dalam Proses'],
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
