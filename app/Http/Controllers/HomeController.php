<?php

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\DokumentasiPenerimaanBarang;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\ReviuDokumen;
use App\Models\RiwayatPenugasanPPK;
use App\Models\Role;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use App\Services\PengadaanRoutingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller Antarmuka Program Riil Berbasis Peran (HomeController)
 *
 * Mengelola seluruh alur navigasi dan aksi pengguna pada antarmuka web semantik Zero-CSS:
 * - Menangani dashboard operasional dinamis untuk 9 peran institusi PNJ.
 * - Modul usulan permohonan pengadaan & revisi surat dinas Srikandi (User / Jurusan).
 * - Modul telaah administrasi berkas Staf Bidang II dan keputusan eksekutif mutlak Wadir II.
 * - Modul verifikasi anggaran MAK & penguncian pagu DIPA (Subbagian Perencanaan).
 * - Modul registrasi paket pengadaan, auto-routing HPS presisi 100%, dan alih tugas PPK (PPBJ).
 * - Modul penerbitan kontrak SPK untuk Pejabat Pengadaan (&le; 200jt) dan PPK (> 200jt terisolasi).
 * - Modul pemeriksaan fisik barang, upload dokumentasi foto, dan BAST (Perlengkapan).
 * - Modul verifikasi tagihan SPP, pencairan dana SP2D, dan upload slip transfer bank (Keuangan).
 * - Modul monitoring agregat 8 siklus pengadaan & jejak audit (Audit Trail).
 * - Helper pengunduhan dokumen resmi format PDF (< 3 detik).
 */
class HomeController extends Controller
{
    /**
     * Inisialisasi controller dengan injeksi layanan pengadaan & notifikasi.
     */
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

        // Daftar 5 usulan permohonan terkini (disaring untuk user pemohon)
        $permohonanQuery = PermohonanPengadaan::with(['unit', 'user']);
        if ($user && ($user->hasRole(['user', 'pengaju']) || ($user->hasPermission('permohonan.view_own') && ! $user->hasPermission('permohonan.view_all')))) {
            $permohonanQuery->where('id_user', $user->id_user);
        }
        $recentPermohonan = $permohonanQuery->latest('tanggal_pengajuan')->take(5)->get();

        // Daftar 5 paket pengadaan terkini (menerapkan isolasi data untuk 4 akun PPK)
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

        // Data spesifik per peran (dieksekusi terisolasi sesuai peran aktif pengguna)
        $roleData = $user ? $this->getRoleDashboardData($user) : [];

        // Perkaya $stats dengan statistik spesifik peran untuk kemudahan akses Blade
        if (! empty($roleData['stats'])) {
            $stats = array_merge($stats, $roleData['stats']);
        }

        return view('dashboard.index', compact('user', 'stats', 'recentPermohonan', 'recentPaket', 'notifications', 'units', 'roleData'));
    }

    /**
     * Mengambil data statistik dan antrean spesifik untuk peran pengguna yang sedang aktif.
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
                    // Stat monitoring aktivitas PP (FR RBAC Lihat PP)
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
                ],
                'siap_paket' => PermohonanPengadaan::with('unit')
                    ->where('status_permohonan', 'disetujui')
                    ->whereDoesntHave('paketPengadaan')
                    ->latest('tanggal_pengajuan')
                    ->take(5)
                    ->get(),
                // Seluruh daftar paket yang berada di bawah penanganan Pejabat Pengadaan (PP)
                'pp_monitoring_list' => PaketPengadaan::with(['vendor', 'ppUser', 'ppkUser', 'reviuDokumen' => fn ($rq) => $rq->where('role_reviewer', 'pp')->latest('putaran_ke')])
                    ->where(function ($q) {
                        $q->where('jalur_routing', PaketPengadaan::ROUTING_PP)
                            ->orWhereNotNull('id_user_pp');
                    })
                    ->latest('updated_at')
                    ->take(8)
                    ->get(),
                // Riwayat audit tindakan terakhir oleh Pejabat Pengadaan (PP)
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
                    'total_paket_ppk' => PaketPengadaan::where('id_user_ppk', $user->id_user)->count(),
                    'tender_aktif' => PaketPengadaan::where('id_user_ppk', $user->id_user)->where('jalur_routing', 'ppk_diatas_200jt')->whereIn('status_paket', ['terdaftar', 'proses'])->count(),
                    'spk_aktif' => PaketPengadaan::where('id_user_ppk', $user->id_user)->whereHas('kontrakSpk')->count(),
                    'memo_bayar_pending' => MemoBayar::where('role_approver', 'ppk')->whereIn('status_memo', ['diajukan', 'menunggu'])->whereHas('pembayaran.paket', fn ($q) => $q->where('id_user_ppk', $user->id_user))->count(),
                ],
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
     * Mengambil daftar aktivitas pengadaan terbaru yang terkait dengan akun pemohon (role user).
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function getUserRecentActivities(User $user)
    {
        $activities = collect();

        // 1. Ambil dari AuditLog terkait user atau nomor surat usulan user
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

        // 2. Jika aktivitas dari AuditLog masih kurang, tambahkan dari snapshot PermohonanPengadaan milik user
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

                if ($p->paket && $p->paket->kontrak) {
                    $activities->push([
                        'waktu' => $p->paket->kontrak->created_at ? $p->paket->kontrak->created_at->format('d M Y, H:i') : '-',
                        'aktivitas' => 'Pembuatan surat kontrak',
                        'nama_pengadaan' => $p->judul_pengadaan,
                        'status' => 'Berhasil',
                        'nomor_surat' => $p->nomor_surat,
                    ]);
                }
            }
        }

        return $activities->unique(fn ($item) => $item['waktu'].$item['aktivitas'].$item['nama_pengadaan'])->take(11)->values();
    }

    // =========================================================================
    // MODUL 1: PERMOHONAN PENGADAAN (USER / PEMOHON JURUSAN)
    // =========================================================================

    /**
     * Menampilkan riwayat usulan permohonan milik user login beserta jejak penolakan.
     */
    // app/Http/Controllers/HomeController.php

    public function permohonanIndex(Request $request): View
    {
        $user = Auth::user();
        $query = PermohonanPengadaan::with([
            'unit',
            'user',
            'verifikasiParalel.user',
            'suratAsalRevisi',
            'alokasiMak.anggaranMak',
            'paketPengadaan.reviuDokumen',
            'paketPengadaan.kontrakSpk',
            'paketPengadaan.penerimaanBarang',
            'paketPengadaan.pembayaran.transaksiPencairan',
            'paketPengadaan.reminders',
        ])
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi');

        // Jika user yang login hanya memiliki izin melihat usulan sendiri (tanpa view_all), filter berdasarkan ID user
        if ($user && ($user->hasRole(['user', 'pengaju']) || ($user->hasPermission('permohonan.view_own') && ! $user->hasPermission('permohonan.view_all')))) {
            $query->where('id_user', $user->id_user);
        }

        // Terapkan Filter Pencarian (Search / Status / Jenis Pengadaan)
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('judul_pengadaan', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status_permohonan', $request->status);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('jenis_pengadaan_split', $request->type);
        }

        $permohonanList = $query->latest('tanggal_pengajuan')->paginate(15);
        $units = Unit::all();
        $makList = AnggaranMAK::orderBy('kode_mak')->get();
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('permohonan.index', compact('permohonanList', 'user', 'units', 'makList', 'ppkUsers'));
    }

    /**
     * Menampilkan detail tracking & informasi permohonan pengadaan.
     */
    public function permohonanShow(string $id): View
    {
        $permohonan = PermohonanPengadaan::with([
            'unit',
            'user',
            'verifikasiParalel.user',
            'paket.kontrak.penerimaan',
        ])->where('nomor_surat', $id)
            ->firstOrFail();

        $makList = AnggaranMAK::orderBy('kode_mak')->get();
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('permohonan.show', compact('permohonan', 'makList', 'ppkUsers'));
    }

    /**
     * Menampilkan formulir pendaftaran usulan permohonan pengadaan baru berbasis Srikandi.
     */
    public function permohonanCreate(): View
    {
        $units = Unit::all();

        return view('permohonan.create', compact('units'));
    }

    /**
     * Memproses pengajuan usulan permohonan baru ke Bidang 2 (Alur Persetujuan Awal Berantai).
     */
    public function storePermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_pengadaan_ringkas' => ['nullable', 'string', 'max:500'],
            'anggaran_diajukan' => ['required', 'numeric', 'min:1'],
            'kode_unit' => ['required', 'string', 'exists:units,kode_unit'],
            'tanggal_pengajuan' => ['required', 'date'],
            'file_surat_permohonan' => ['nullable', DokumenUploadRule::pdfOnly()],
            'file_kak' => ['nullable', DokumenUploadRule::pdfOnly()],
            'file_spesifikasi_teknis' => ['nullable', DokumenUploadRule::pdfOnly()],
        ]);

        $filePermohonanPath = $request->hasFile('file_surat_permohonan')
            ? $request->file('file_surat_permohonan')->store('permohonan', 'public')
            : null;

        $fileKakPath = $request->hasFile('file_kak')
            ? $request->file('file_kak')->store('kak', 'public')
            : null;

        $fileSpekPath = $request->hasFile('file_spesifikasi_teknis')
            ? $request->file('file_spesifikasi_teknis')->store('spek', 'public')
            : null;

        $kodeUnit = $user->kode_unit ?? $validated['kode_unit'] ?? 'TIK';

        // Buat baris data permohonan pengadaan pada tahap awal Bidang 2
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat'],
            'judul_pengadaan' => $validated['perihal'],
            'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $validated['perihal'],
            'estimasi_total_pagu' => $validated['anggaran_diajukan'],
            'perihal' => $validated['perihal'],
            'anggaran_diajukan' => $validated['anggaran_diajukan'],
            'kode_unit' => $kodeUnit,
            'id_user' => $user->id_user,
            'tanggal_pengajuan' => $validated['tanggal_pengajuan'],
            'file_permohonan' => $filePermohonanPath ?? 'permohonan/dummy.pdf',
            'file_surat_permohonan' => $filePermohonanPath ?? 'permohonan/dummy.pdf',
            'file_kak' => $fileKakPath,
            'file_spesifikasi_teknis' => $fileSpekPath,
            'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            'status_anggaran' => 'pending',
            'status' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
        ]);

        // Inisialisasi antrean telaah untuk Wadir 2, Perencanaan, dan pemantauan Staf Bidang 2
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2,
            'status_keputusan' => 'menunggu',
        ]);
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2,
            'status_keputusan' => 'menunggu',
        ]);
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN,
            'status_keputusan' => 'menunggu',
        ]);

        // Catat ke jejak audit
        AuditLog::catat(
            $user->id_user,
            'AJUKAN_PERMOHONAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            null,
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            "Pengajuan usulan permohonan pengadaan nomor {$permohonan->nomor_surat} ke Bidang 2 oleh {$user->nama}"
        );

        // Memicu notifikasi siklus: Bidang 2 menerima alarm usulan baru
        $this->notificationService->notifyPengajuanBaru($permohonan);

        return redirect()->route('permohonan.index')->with(
            'success',
            "Usulan permohonan nomor {$permohonan->nomor_surat} berhasil diajukan ke Bidang II untuk pemeriksaan berkas dan penerbitan Memo Cek MAK."
        );
    }

    /**
     * Memproses pengajuan revisi surat usulan permohonan yang sebelumnya ditolak/perlu revisi secara in-place (Opsi A).
     * Mendukung pembaruan in-place maupun pewarisan ke nomor surat baru tanpa pemotongan ganda saldo pagu MAK.
     */
    public function revisiPermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat_lama' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['nullable', 'string', 'max:100'],
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_pengadaan_ringkas' => ['nullable', 'string', 'max:500'],
            'anggaran_diajukan' => ['required', 'numeric', 'min:1'],
            'keterangan_revisi' => ['nullable', 'string'],
            'file_kak' => ['nullable', DokumenUploadRule::pdfOnly()],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_lama']);
        $oldNomorSurat = $induk->nomor_surat;
        $targetNomorSurat = trim($validated['nomor_surat_baru'] ?? '');
        $isSameSurat = empty($targetNomorSurat) || ($targetNomorSurat === $oldNomorSurat);

        if (! $isSameSurat) {
            $isUsed = PermohonanPengadaan::where('nomor_surat', $targetNomorSurat)->exists();
            if ($isUsed) {
                return back()->withErrors(['nomor_surat_baru' => "Nomor surat {$targetNomorSurat} sudah terdaftar di sistem."])->withInput();
            }
        }

        $fileKakPath = $request->hasFile('file_kak')
            ? $request->file('file_kak')->store('kak', 'public')
            : $induk->file_kak;

        $nominalBaru = (float) $validated['anggaran_diajukan'];
        $nominalLama = (float) ($induk->anggaran_diajukan ?? $induk->estimasi_total_pagu ?? 0);
        $selisih = $nominalBaru - $nominalLama;
        $revisiKe = ($induk->revisi_ke ?? 0) + 1;

        // Cek dan kelola saldo MAK agar tidak terjadi pemotongan ganda
        $mak = $induk->kode_mak ? AnggaranMAK::find($induk->kode_mak) : null;
        $alokasiLama = $induk->kode_mak
            ? AlokasiMAK::where('nomor_surat', $oldNomorSurat)->where('kode_mak', $induk->kode_mak)->first()
            : null;

        if ($mak && $alokasiLama) {
            if ($selisih > 0) {
                if ((float) $mak->sisa_pagu_mak < $selisih) {
                    return back()->with('error', "Penyesuaian anggaran melebihi sisa pagu MAK {$mak->kode_mak} yang tersedia (tersisa Rp ".number_format($mak->sisa_pagu_mak, 0, ',', '.').').')->withInput();
                }
                $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $selisih);
                $mak->save();
            } elseif ($selisih < 0) {
                $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + abs($selisih);
                $mak->save();
            }
        }

        if ($isSameSurat) {
            // ==========================================
            // KASUS 1: In-Place Update pada Surat Sama
            // ==========================================
            DB::transaction(function () use ($induk, $validated, $nominalBaru, $fileKakPath, $revisiKe, $alokasiLama) {
                if ($alokasiLama) {
                    $alokasiLama->update(['nominal_alokasi' => $nominalBaru]);
                }

                $induk->update([
                    'judul_pengadaan' => $validated['perihal'],
                    'perihal' => $validated['perihal'],
                    'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $induk->tujuan_pengadaan_ringkas ?? $validated['perihal'],
                    'anggaran_diajukan' => $nominalBaru,
                    'estimasi_total_pagu' => $nominalBaru,
                    'file_kak' => $fileKakPath,
                    'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                    'status' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                    'revisi_ke' => $revisiKe,
                    'tanggal_revisi' => now(),
                    'alasan_revisi' => $validated['keterangan_revisi'] ?? 'Perbaikan dokumen KAK dan spesifikasi',
                ]);
            });

            $activePermohonan = $induk;
        } else {
            // ==========================================
            // KASUS 2: Surat Baru dengan Pewarisan MAK
            // ==========================================
            $activePermohonan = DB::transaction(function () use ($induk, $targetNomorSurat, $validated, $nominalBaru, $fileKakPath, $revisiKe, $user, $alokasiLama) {
                $revisi = PermohonanPengadaan::create([
                    'nomor_surat' => $targetNomorSurat,
                    'judul_pengadaan' => $validated['perihal'],
                    'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $induk->tujuan_pengadaan_ringkas ?? $validated['perihal'],
                    'estimasi_total_pagu' => $nominalBaru,
                    'perihal' => $validated['perihal'],
                    'anggaran_diajukan' => $nominalBaru,
                    'kode_unit' => $induk->kode_unit,
                    'id_user' => $user->id_user,
                    'tanggal_pengajuan' => now(),
                    'file_permohonan' => $induk->file_permohonan ?? 'permohonan/dummy.pdf',
                    'file_surat_permohonan' => $induk->file_surat_permohonan ?? $induk->file_permohonan ?? 'permohonan/dummy.pdf',
                    'file_kak' => $fileKakPath,
                    'status_permohonan' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                    'status' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                    'revisi_ke' => $revisiKe,
                    'nomor_surat_revisi' => $induk->nomor_surat,
                    'tanggal_revisi' => now(),
                    'alasan_revisi' => $validated['keterangan_revisi'] ?? 'Perbaikan dokumen KAK dan spesifikasi',
                    'kode_mak' => $induk->kode_mak,
                    'status_anggaran' => $induk->status_anggaran ?? 'pending',
                    'id_user_ppk_awal' => $induk->id_user_ppk_awal,
                    'nomor_memo_ketersediaan_mak' => $induk->nomor_memo_ketersediaan_mak,
                    'catatan_memo_ketersediaan_mak' => $induk->catatan_memo_ketersediaan_mak,
                ]);

                // Transfer alokasi MAK dari surat lama ke surat baru
                if ($alokasiLama) {
                    AlokasiMAK::create([
                        'nomor_surat' => $revisi->nomor_surat,
                        'kode_mak' => $alokasiLama->kode_mak,
                        'nominal_alokasi' => $nominalBaru,
                        'tanggal_alokasi' => now(),
                    ]);
                    $alokasiLama->delete();
                }

                // Tandai surat lama sebagai telah digantikan oleh revisi baru
                $induk->update([
                    'status_permohonan' => 'digantikan_oleh_revisi',
                    'status' => 'digantikan_oleh_revisi',
                    'kode_mak' => null,
                ]);

                return $revisi;
            });
        }

        // Inisialisasi antrean awal kembali di Staf Bidang 2
        VerifikasiParalel::updateOrCreate(
            ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2],
            [
                'id_user' => $user->id_user,
                'status_keputusan' => 'menunggu',
                'catatan_alasan' => null,
                'tanggal_keputusan' => null,
            ]
        );

        // Reset keputusan Wadir 2 dan PPK
        VerifikasiParalel::where('nomor_surat', $activePermohonan->nomor_surat)
            ->whereIn('role_verifikator', [VerifikasiParalel::ROLE_WADIR_2, VerifikasiParalel::ROLE_PPK])
            ->update([
                'status_keputusan' => 'menunggu',
                'catatan_alasan' => null,
                'tanggal_keputusan' => null,
            ]);

        // Catat jejak audit revisi
        AuditLog::catat(
            $user->id_user,
            'REVISI_PERMOHONAN',
            'permohonan_pengadaan',
            $activePermohonan->nomor_surat,
            $oldNomorSurat !== $activePermohonan->nomor_surat ? $oldNomorSurat : null,
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            "Revisi usulan permohonan ke-{$revisiKe} ({$activePermohonan->nomor_surat}) oleh {$user->nama}"
        );

        // Memicu notifikasi siklus revisi berkas usulan ke Bidang 2
        $this->notificationService->notifyPengajuanRevisi($activePermohonan);

        return redirect()->route('permohonan.index')->with(
            'success',
            "Usulan permohonan nomor {$activePermohonan->nomor_surat} berhasil direvisi (Revisi ke-{$activePermohonan->revisi_ke}) dan diajukan kembali ke antrean Bidang II."
        );
    }

    /**
     * Membatalkan dan menghapus usulan permohonan pengadaan status draft/diajukan/ditolak.
     */
    public function destroyPermohonan(string $id): RedirectResponse
    {
        $user = Auth::user();
        $permohonan = PermohonanPengadaan::where('nomor_surat', $id)
            ->orWhere('nomor_surat', urldecode($id))
            ->orWhere('nomor_surat', rawurldecode($id))
            ->firstOrFail();

        $isAdmin = $user->hasRole('admin') || $user->role === User::ROLE_ADMIN || ($user->roleEntity && $user->roleEntity->nama_role === User::ROLE_ADMIN);

        if (! $isAdmin) {
            if ((int) $permohonan->id_user !== (int) $user->id_user) {
                abort(403, 'Akses Ditolak: Anda hanya dapat membatalkan permohonan milik sendiri.');
            }

            if (! in_array($permohonan->status_permohonan, ['draft', 'diajukan', 'ditolak'], true)) {
                return redirect()->route('permohonan.index')->with('error', 'Permohonan yang telah diverifikasi atau diproses paket tidak dapat dibatalkan.');
            }
        }

        $nomor = $permohonan->nomor_surat;

        DB::transaction(function () use ($permohonan) {
            $permohonan->cascadeDelete();
        });

        AuditLog::catat(
            $user->id_user,
            'HAPUS_PERMOHONAN',
            'permohonan_pengadaan',
            $nomor,
            null,
            'dihapus',
            "Pembatalan dan penghapusan usulan permohonan nomor {$nomor} oleh {$user->nama}"
        );

        return redirect()->route('permohonan.index')->with('success', "Usulan permohonan nomor {$nomor} berhasil dibatalkan dan dihapus.");
    }

    // =========================================================================
    // MODUL 2: ALUR PERSETUJUAN AWAL BERANTAI (BIDANG 2 -> PERENCANAAN -> WADIR 2 & PPK)
    // =========================================================================

    /**
     * Menampilkan antrean telaah kelengkapan administrasi berkas & monitoring berkas bagi Staf Bidang II.
     */
    public function verifikasiBidang2(Request $request): View
    {
        $query = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel'])
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('nomor_surat', 'ilike', "%{$keyword}%")
                    ->orWhere('judul_pengadaan', 'ilike', "%{$keyword}%")
                    ->orWhere('perihal', 'ilike', "%{$keyword}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'Semua') {
            $query->where('status_permohonan', $request->status);
        }

        $permohonanMasuk = $query->latest('tanggal_pengajuan')->get();

        return view('verifikasi.bidang_2', compact('permohonanMasuk'));
    }

    /**
     * Memproses pemeriksaan berkas Staf Bidang 2 dan menerbitkan Memo Cek MAK ke Bagian Perencanaan.
     */
    public function teruskanStaffB2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_memo_cek_mak' => ['nullable', 'string', 'max:100'],
            'catatan_staff' => ['nullable', 'string'],
            'catatan_memo_cek_mak' => ['nullable', 'string'],
            'file_memo_cek_mak' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $nomorMemo = $validated['nomor_memo_cek_mak'] ?? ('MEMO-B2/CEK-MAK/'.date('Ymd').'/'.rand(100, 999));
        $catatan = $validated['catatan_memo_cek_mak'] ?? $validated['catatan_staff'] ?? 'Berkas KAK dan spesifikasi teknis lengkap, mohon cek ketersediaan MAK DIPA';

        $fileMemoPath = null;
        if ($request->hasFile('file_memo_cek_mak')) {
            $fileMemoPath = $request->file('file_memo_cek_mak')->store('memo_cek_mak', 'public');
        }

        // Perbarui entri verifikasi Staf Bidang 2
        $verifB2 = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_STAFF_BIDANG_2],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifB2->id_user = $user->id_user;
        $verifB2->status_keputusan = 'disetujui';
        $verifB2->catatan_alasan = $catatan;
        $verifB2->nomor_memo = $nomorMemo;
        if ($fileMemoPath) {
            $verifB2->file_memo = $fileMemoPath;
        }
        $verifB2->tanggal_keputusan = now();
        $verifB2->save();

        // Simpan Memo Cek MAK pada permohonan dan arahkan ke Perencanaan
        $statusLama = $permohonan->status_permohonan;
        $permohonan->nomor_memo_cek_mak = $nomorMemo;
        $permohonan->catatan_memo_cek_mak = $catatan;
        if ($fileMemoPath) {
            $permohonan->file_memo_cek_mak = $fileMemoPath;
        }
        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN;
        $permohonan->status = PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN;
        $permohonan->save();

        // Aktifkan antrean verifikasi bagi Bagian Perencanaan
        VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );

        AuditLog::catat(
            $user->id_user,
            'MEMO_CEK_MAK_B2_KE_PERENCANAAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $statusLama,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            "Staff Bidang 2 mengirim Memo Cek MAK ({$nomorMemo}) ke Perencanaan: {$catatan}"
        );

        // Memicu notifikasi ke Perencanaan untuk memeriksa/membuat MAK
        $this->notificationService->notifyMemoCekMak($permohonan, $nomorMemo, $catatan);

        return redirect()->route('verifikasi.bidang-2')->with(
            'success',
            "Memo Cek MAK ({$nomorMemo}) untuk surat {$permohonan->nomor_surat} berhasil dikirimkan ke Bagian Perencanaan."
        );
    }

    /**
     * Menampilkan antrean keputusan persetujuan kegiatan Wakil Direktur II (ACC Kegiatan / Tolak)
     * atas Memo Ketersediaan MAK dari Perencanaan.
     */
    public function verifikasiWadir2(): View
    {
        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user', 'ppkAwal', 'verifikasiParalel'])
            ->whereIn('status_permohonan', [
                'diajukan',
                PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
            ])
            ->latest('tanggal_pengajuan')
            ->get();

        return view('verifikasi.wadir_2', compact('permohonanAntrean'));
    }

    /**
     * Memproses keputusan Wadir 2 (ACC Kegiatan / Tolak) bersama PPK terpilih sebelum diteruskan ke PPBJ.
     */
    public function keputusanWadir2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'keputusan' => ['required', 'in:disetujui,ditolak,revisi'],
            'catatan_alasan' => ['required_if:keputusan,ditolak,revisi', 'nullable', 'string'],
            'nomor_memo' => ['nullable', 'string', 'max:100'],
            'file_memo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan penolakan/revisi wajib diisi saat Wadir 2 menolak atau meminta revisi usulan (FR 5.3).',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        if (! in_array($permohonan->status_permohonan, [
            PermohonanPengadaan::STATUS_DIAJUKAN,
            PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
        ], true)) {
            return redirect()->route('verifikasi.wadir-2')
                ->with('error', 'Permohonan yang telah disetujui atau diproses lanjut tidak dapat diubah keputusannya.');
        }

        // Simpan keputusan pada tabel verifikasi_paralel
        $verifWadir = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifWadir->id_user = $user->id_user;
        $verifWadir->status_keputusan = $validated['keputusan'];
        $verifWadir->catatan_alasan = $validated['catatan_alasan'] ?? null;
        if ($request->filled('nomor_memo')) {
            $verifWadir->nomor_memo = $validated['nomor_memo'];
        } elseif ($validated['keputusan'] === 'disetujui' && empty($verifWadir->nomor_memo)) {
            $verifWadir->nomor_memo = 'MEMO-WADIR2/'.date('Ymd').'/'.rand(100, 999);
        }
        if ($request->hasFile('file_memo')) {
            $verifWadir->file_memo = $request->file('file_memo')->store('memo_wadir2', 'public');
        }
        $verifWadir->tanggal_keputusan = now();
        $verifWadir->save();

        if ($validated['keputusan'] === 'revisi') {
            $permohonan->status = 'perlu_revisi';
            $permohonan->status_permohonan = 'perlu_revisi';
            $permohonan->alasan_revisi = $validated['catatan_alasan'];
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'WADIR2_MINTA_REVISI',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                'reviu_paralel',
                'perlu_revisi',
                "Wadir 2 meminta revisi usulan permohonan dengan catatan: {$validated['catatan_alasan']}"
            );

            // Memicu notifikasi revisi ke pengusul
            $this->notificationService->notifyKeputusanWadir2($permohonan, 'revisi', $validated['catatan_alasan']);

            return redirect()->back()->with(
                'success',
                "Permintaan revisi usulan {$permohonan->nomor_surat} telah disimpan dan dikembalikan ke pengusul."
            );
        }

        if ($validated['keputusan'] === 'ditolak') {
            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'ditolak';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
            $permohonan->alasan_revisi = $validated['catatan_alasan'];
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'WADIR2_TOLAK_USULAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'ditolak',
                "Wadir 2 menolak usulan permohonan dengan alasan: {$validated['catatan_alasan']}"
            );

            $this->notificationService->notifyKeputusanWadir2($permohonan, 'ditolak', $validated['catatan_alasan']);

            return redirect()->back()->with(
                'success',
                "Keputusan penolakan usulan {$permohonan->nomor_surat} telah disimpan dengan alasan resmi."
            );
        }

        // Evaluasi kelulusan paralel (AND-join): Perencanaan (MAK) + Wadir 2 (ACC Kegiatan) + PPK Terpilih (ACC Usulan)
        $perencanaanAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
            ->where('status_keputusan', 'disetujui')
            ->exists() || ! empty($permohonan->kode_mak);

        $ppkAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        if ($perencanaanAcc && $ppkAcc) {
            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'disetujui';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'PERSETUJUAN_PENUH_AWAL_KE_PPBJ',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'disetujui',
                'Usulan disetujui penuh oleh Perencanaan, Wadir 2, dan PPK terpilih; otomatis diteruskan ke PPBJ.'
            );

            $this->notificationService->notifyKeputusanWadir2($permohonan, 'disetujui');

            return redirect()->back()->with(
                'success',
                'Persetujuan Wadir 2 berhasil dicatat. Wadir 2 dan PPK telah menyetujui lengkap, usulan diteruskan ke PPBJ.'
            );
        }

        $permohonan->status_permohonan = $perencanaanAcc
            ? PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK
            : PermohonanPengadaan::STATUS_REVIU_PARALEL;
        $permohonan->status = $permohonan->status_permohonan;
        $permohonan->save();

        $this->notificationService->notifyKeputusanWadir2($permohonan, 'disetujui');

        return redirect()->back()->with(
            'success',
            'Persetujuan kegiatan (ACC) Wadir 2 telah dicatat. Menunggu persetujuan PPK terpilih / Perencanaan sebelum lanjut ke PPBJ.'
        );
    }

    /**
     * Menampilkan antrean persetujuan awal usulan pengadaan bagi akun PPK terpilih (Isolasi 4 Akun PPK).
     */
    public function verifikasiPpk(): View
    {
        $user = Auth::user();

        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user', 'ppkAwal', 'verifikasiParalel'])
            ->where('id_user_ppk_awal', $user->id_user)
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
            ])
            ->latest('tanggal_pengajuan')
            ->get();

        return view('verifikasi.ppk', compact('permohonanAntrean'));
    }

    /**
     * Memproses persetujuan awal usulan oleh PPK yang dipilih Perencanaan (bersama Wadir 2 sebelum masuk PPBJ).
     */
    public function keputusanPpkAwal(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'keputusan' => ['required', 'in:disetujui,ditolak'],
            'catatan_alasan' => ['required_if:keputusan,ditolak', 'nullable', 'string'],
            'nomor_memo' => ['nullable', 'string', 'max:100'],
            'file_memo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan penolakan wajib diisi saat PPK menolak usulan permohonan.',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        // Penegakan isolasi ketat 4 akun PPK pada persetujuan awal
        if ((int) $permohonan->id_user_ppk_awal !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak memproses persetujuan awal untuk usulan yang ditugaskan ke akun PPK lain.');
        }

        $verifPpk = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PPK],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifPpk->id_user = $user->id_user;
        $verifPpk->status_keputusan = $validated['keputusan'];
        $verifPpk->catatan_alasan = $validated['catatan_alasan'] ?? 'Usulan disetujui oleh PPK atas Memo Ketersediaan MAK Perencanaan';
        if ($request->filled('nomor_memo')) {
            $verifPpk->nomor_memo = $validated['nomor_memo'];
        } elseif ($validated['keputusan'] === 'disetujui' && empty($verifPpk->nomor_memo)) {
            $verifPpk->nomor_memo = 'MEMO-PPK/'.date('Ymd').'/'.rand(100, 999);
        }
        if ($request->hasFile('file_memo')) {
            $verifPpk->file_memo = $request->file('file_memo')->store('memo_ppk', 'public');
        }
        $verifPpk->tanggal_keputusan = now();
        $verifPpk->save();

        if ($validated['keputusan'] === 'ditolak') {
            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'ditolak';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
            $permohonan->alasan_revisi = $validated['catatan_alasan'];
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'PPK_AWAL_TOLAK_USULAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'ditolak',
                "PPK ({$user->nama}) menolak usulan awal permohonan: {$validated['catatan_alasan']}"
            );

            $this->notificationService->notifyKeputusanPpkAwal($permohonan, 'ditolak', $validated['catatan_alasan']);

            return redirect()->back()->with(
                'success',
                "Keputusan penolakan usulan {$permohonan->nomor_surat} oleh PPK telah disimpan."
            );
        }

        $perencanaanAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
            ->where('status_keputusan', 'disetujui')
            ->exists() || ! empty($permohonan->kode_mak);

        $wadirAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        if ($perencanaanAcc && $wadirAcc) {
            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'disetujui';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'PERSETUJUAN_PENUH_AWAL_KE_PPBJ',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'disetujui',
                "PPK ({$user->nama}) dan Wadir 2 telah menyetujui lengkap; berkas diteruskan ke PPBJ."
            );

            $this->notificationService->notifyKeputusanPpkAwal($permohonan, 'disetujui', $validated['catatan_alasan'] ?? null);

            return redirect()->back()->with(
                'success',
                "Persetujuan PPK dicatat. Wadir 2 dan PPK telah menyetujui lengkap, berkas {$permohonan->nomor_surat} diteruskan ke PPBJ."
            );
        }

        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->status = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->save();

        AuditLog::catat(
            $user->id_user,
            'PPK_AWAL_SETUJU_USULAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            "PPK ({$user->nama}) menyetujui usulan awal; menunggu ACC kegiatan Wadir 2."
        );

        $this->notificationService->notifyKeputusanPpkAwal($permohonan, 'disetujui', $validated['catatan_alasan'] ?? null);

        return redirect()->back()->with(
            'success',
            "Persetujuan awal PPK untuk surat {$permohonan->nomor_surat} berhasil dicatat. Menunggu ACC kegiatan oleh Wadir II."
        );
    }

    // =========================================================================
    // MODUL 3: VERIFIKASI ANGGARAN MAK & OTORITAS TUNGGAL LOCK/UNLOCK (PERENCANAAN)
    // =========================================================================

    /**
     * Menampilkan antrean cek ketersediaan MAK, pembuatan MAK, serta kontrol eksklusif Kunci/Buka Kunci MAK.
     */
    public function verifikasiPerencanaan(): View
    {
        // 1. Antrean usulan yang menunggu penetapan alokasi MAK DIPA
        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user', 'ppkAwal'])
            ->whereNotIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_SELESAI,
            ])
            ->where(function ($q) {
                $q->whereNull('kode_mak')
                    ->orWhere('kode_mak', '')
                    ->orWhereIn('status_permohonan', [
                        PermohonanPengadaan::STATUS_DIAJUKAN,
                        PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                        PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                        PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                    ]);
            })
            ->latest('tanggal_pengajuan')
            ->get();

        // 2. Daftar usulan yang telah memiliki MAK DIPA (untuk monitoring & penyesuaian MAK - BPMN Lane 2)
        $permohonanSudahMak = PermohonanPengadaan::with(['unit', 'user', 'anggaranMak', 'ppkAwal'])
            ->whereNotNull('kode_mak')
            ->where('kode_mak', '!=', '')
            ->whereNotIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DITOLAK,
            ])
            ->latest('updated_at')
            ->get();

        $makList = AnggaranMAK::orderBy('kode_mak')->get();
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('verifikasi.perencanaan', compact('permohonanAntrean', 'permohonanSudahMak', 'makList', 'ppkUsers'));
    }

    /**
     * Mendaftarkan rekening MAK DIPA baru melalui antarmuka web oleh Bagian Perencanaan (FR 6.1).
     */
    public function storeMakPerencanaan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_mak' => ['required', 'string', 'max:50', 'unique:anggaran_mak,kode_mak'],
            'uraian_mak' => ['required', 'string', 'max:255'],
            'pagu_anggaran' => ['required', 'numeric', 'min:1'],
            'sumber_dana' => ['required', 'string', 'max:100'],
            'tahun_anggaran' => ['required', 'integer', 'digits:4'],
            'catatan_mak' => ['nullable', 'string'],
        ]);

        $validated['sisa_pagu_mak'] = $validated['pagu_anggaran'];
        $validated['tanggal_penetapan'] = now();
        $validated['is_locked'] = false;
        $validated['id_user'] = Auth::id();

        $mak = AnggaranMAK::create($validated);

        AuditLog::catat(
            Auth::id() ?? 1,
            'PENETAPAN_MAK_WEB',
            'anggaran_mak',
            $mak->kode_mak,
            null,
            (string) $mak->pagu_anggaran,
            'Penetapan rekening MAK baru via web oleh Perencanaan: '.$mak->uraian_mak
        );

        return redirect()->route('verifikasi.perencanaan')
            ->with('success', "Rekening MAK {$mak->kode_mak} ({$mak->uraian_mak}) berhasil ditambahkan ke DIPA kampus.");
    }

    /**
     * Mengunci rekening MAK secara eksklusif oleh Bagian Perencanaan via antarmuka web.
     */
    public function lockMakWeb(AnggaranMAK $anggaran_mak): RedirectResponse
    {
        $anggaran_mak->lock();

        AuditLog::catat(
            Auth::id() ?? 1,
            'KUNCI_MAK_PERENCANAAN',
            'anggaran_mak',
            $anggaran_mak->kode_mak,
            'unlocked',
            'locked',
            "Bagian Perencanaan mengunci rekening MAK {$anggaran_mak->kode_mak}."
        );

        return redirect()->route('verifikasi.perencanaan')
            ->with('success', "Rekening MAK {$anggaran_mak->kode_mak} berhasil dikunci oleh Bagian Perencanaan.");
    }

    /**
     * Membuka kunci rekening MAK secara eksklusif oleh Bagian Perencanaan via antarmuka web.
     */
    public function unlockMakWeb(AnggaranMAK $anggaran_mak): RedirectResponse
    {
        $anggaran_mak->unlock();

        AuditLog::catat(
            Auth::id() ?? 1,
            'BUKA_KUNCI_MAK_PERENCANAAN',
            'anggaran_mak',
            $anggaran_mak->kode_mak,
            'locked',
            'unlocked',
            "Bagian Perencanaan membuka kunci rekening MAK {$anggaran_mak->kode_mak}."
        );

        return redirect()->route('verifikasi.perencanaan')
            ->with('success', "Kunci rekening MAK {$anggaran_mak->kode_mak} berhasil dibuka oleh Bagian Perencanaan.");
    }

    /**
     * Memproses pemeriksaan ketersediaan MAK, pemilihan 1 dari 4 akun PPK, dan pengiriman Memo Ketersediaan MAK
     * ke Bidang 2 (Wadir 2) serta PPK terpilih.
     */
    public function alokasiMakPerencanaan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'nominal_alokasi' => ['required', 'numeric', 'min:1'],
            'keputusan_anggaran' => ['required', 'in:setuju,tahan'],
            'id_user_ppk_awal' => ['nullable', 'exists:users,id_user'],
            'nomor_memo_ketersediaan_mak' => ['nullable', 'string', 'max:100'],
            'catatan_anggaran' => ['nullable', 'string'],
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $mak = AnggaranMAK::findOrFail($validated['kode_mak']);

        if ($validated['keputusan_anggaran'] === 'tahan') {
            $verifPerencanaan = VerifikasiParalel::firstOrCreate(
                ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN],
                ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
            );
            $verifPerencanaan->id_user = $user->id_user;
            $verifPerencanaan->status_keputusan = 'pending_anggaran';
            $verifPerencanaan->catatan_alasan = $validated['catatan_anggaran'] ?? 'Pagu anggaran belum tersedia di DIPA kampus (Hold).';
            $verifPerencanaan->tanggal_keputusan = now();
            $verifPerencanaan->save();

            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'pending_anggaran';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_PENDING_ANGGARAN;
            $permohonan->status_anggaran = 'hold';
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'HOLD_ANGGARAN_PERMOHONAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                'Perencanaan menahan (Hold/Pending) usulan karena anggaran belum tersedia (FR 6.2): '.($validated['catatan_anggaran'] ?? 'Anggaran belum siap')
            );

            $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'tahan', $validated['catatan_anggaran'] ?? null);

            return redirect()->route('verifikasi.perencanaan')->with(
                'error',
                "Usulan {$permohonan->nomor_surat} ditandai pending anggaran (Hold) oleh Bagian Perencanaan (FR 6.2)."
            );
        }

        // Validasi pemilihan 1 dari 4 akun PPK untuk menerima Memo Ketersediaan MAK
        $idPpkAwal = $validated['id_user_ppk_awal']
            ?? $permohonan->id_user_ppk_awal
            ?? User::where('role', 'ppk')->where('status_aktif', true)->first()?->id_user;

        if ($idPpkAwal) {
            $ppkTarget = User::find($idPpkAwal);
            if (! $ppkTarget || $ppkTarget->role !== 'ppk') {
                return back()->withErrors(['id_user_ppk_awal' => 'Akun PPK awal yang dipilih harus memiliki peran PPK.'])->withInput();
            }
        }

        $nomorMemoMak = $validated['nomor_memo_ketersediaan_mak'] ?? ('MEMO-PRC/MAK/'.date('Ymd').'/'.rand(100, 999));

        $nominalAlokasi = (float) $validated['nominal_alokasi'];

        // Cek jika usulan sebelumnya telah dialokasikan ke pos MAK yang berbeda (Perbaikan Jenis MAK - BPMN Lane 2)
        if (! empty($permohonan->kode_mak) && $permohonan->kode_mak !== $mak->kode_mak) {
            $oldMak = AnggaranMAK::find($permohonan->kode_mak);
            $oldAlokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)
                ->where('kode_mak', $permohonan->kode_mak)
                ->first();
            if ($oldMak && $oldAlokasi) {
                $oldMak->sisa_pagu_mak = (float) $oldMak->sisa_pagu_mak + (float) $oldAlokasi->nominal_alokasi;
                $oldMak->save();
                $oldAlokasi->delete();
            }
        }

        // Cek alokasi yang sudah ada untuk usulan ini pada kode_mak terkait (cegah double deduction jika alokasi ulang)
        $existingAlokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)
            ->where('kode_mak', $mak->kode_mak)
            ->first();
        $nominalLamaAlokasi = $existingAlokasi ? (float) $existingAlokasi->nominal_alokasi : 0;
        $netDeduction = $nominalAlokasi - $nominalLamaAlokasi;

        // Validasi ketersediaan saldo sisa pagu MAK sebelum alokasi
        if ($netDeduction > (float) $mak->sisa_pagu_mak) {
            return redirect()->back()
                ->with('error', 'Nominal alokasi melebihi sisa pagu MAK yang tersedia.');
        }

        // Catat alokasi MAK
        AlokasiMAK::updateOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'kode_mak' => $mak->kode_mak],
            ['nominal_alokasi' => $nominalAlokasi]
        );

        // Sesuaikan sisa pagu MAK jika terdapat perubahan bersih (net deduction)
        if ($netDeduction != 0) {
            $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $netDeduction);
            $mak->save();
        }

        // Perbarui kode_mak dan status anggaran usulan
        $permohonan->kode_mak = $mak->kode_mak;
        $permohonan->status_anggaran = 'allocated';

        // Catat keputusan ketersediaan MAK oleh Perencanaan
        $verifPerencanaan = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifPerencanaan->id_user = $user->id_user;
        $verifPerencanaan->status_keputusan = 'disetujui';
        $verifPerencanaan->catatan_alasan = $validated['catatan_anggaran'] ?? 'MAK tersedia di DIPA dan Memo Ketersediaan MAK diterbitkan ke Bidang 2 & PPK.';
        $verifPerencanaan->tanggal_keputusan = now();
        $verifPerencanaan->save();

        // Simpan metadata Memo Ketersediaan MAK dan PPK terpilih pada permohonan
        $statusLama = $permohonan->status_permohonan;
        $permohonan->kode_mak = $mak->kode_mak;
        $permohonan->status_anggaran = 'tersedia';
        $permohonan->id_user_ppk_awal = $idPpkAwal;
        $permohonan->nomor_memo_ketersediaan_mak = $nomorMemoMak;
        $permohonan->catatan_memo_ketersediaan_mak = $validated['catatan_anggaran'] ?? 'MAK tersedia di DIPA kampus.';

        // Aktifkan antrean persetujuan bagi Wadir 2 dan PPK terpilih
        VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );

        if ($idPpkAwal) {
            $verifPpk = VerifikasiParalel::firstOrCreate(
                ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PPK],
                ['id_user' => $idPpkAwal, 'status_keputusan' => 'menunggu']
            );
            if ($verifPpk->status_keputusan === 'menunggu') {
                $verifPpk->id_user = $idPpkAwal;
                $verifPpk->save();
            }
        }

        // Evaluasi apakah Wadir 2 DAN PPK terpilih keduanya sudah menyetujui
        $wadirAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        $ppkAcc = ! $idPpkAwal || VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
            ->where('status_keputusan', 'disetujui')
            ->exists();

        if ($wadirAcc && $ppkAcc) {
            $permohonan->status = 'disetujui';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'ALOKASI_MAK_DISETUJUI_PENUH',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                'disetujui',
                "MAK {$mak->kode_mak} tersedia (Memo: {$nomorMemoMak}), Wadir 2 dan PPK telah ACC, diteruskan ke PPBJ."
            );

            $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'setuju', $validated['catatan_anggaran'] ?? null);

            return redirect()->route('verifikasi.perencanaan')->with(
                'success',
                "MAK {$mak->kode_mak} berhasil dialokasikan. Wadir 2 dan PPK telah menyetujui, berkas diteruskan ke PPBJ."
            );
        }

        $permohonan->status = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->status_permohonan = PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK;
        $permohonan->save();

        AuditLog::catat(
            $user->id_user,
            'MEMO_KETERSEDIAAN_MAK_KE_WADIR2_PPK',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $statusLama,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            "Perencanaan menerbitkan Memo Ketersediaan MAK ({$nomorMemoMak}) pada {$mak->kode_mak} ke Bidang 2 (Wadir 2) dan PPK."
        );

        // Memicu notifikasi Memo Ketersediaan MAK ke Wadir 2 dan PPK terpilih
        $this->notificationService->notifyAlokasiMak($permohonan, $mak, 'setuju', $validated['catatan_anggaran'] ?? null);

        return redirect()->route('verifikasi.perencanaan')->with(
            'success',
            "MAK {$mak->kode_mak} tersedia dan Memo Ketersediaan MAK ({$nomorMemoMak}) telah dikirimkan ke Wadir II serta PPK terpilih untuk disetujui."
        );
    }

    /**
     * Memproses pemecahan (split) permohonan pengadaan berdasarkan pos anggaran MAK (FR 6.3).
     */
    public function splitMakPerencanaan(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $nomorInduk = $request->input('nomor_surat_induk');

        if ($request->has('items') && is_array($request->input('items'))) {
            $validated = $request->validate([
                'nomor_surat_induk' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
                'alasan_pemecahan' => ['required', 'string'],
                'items' => ['required', 'array', 'min:2'],
                'items.*.nomor_surat_pecahan' => ['required', 'string', 'max:100', 'distinct', 'unique:permohonan_pengadaan,nomor_surat'],
                'items.*.judul_pengadaan' => ['required', 'string', 'max:255'],
                'items.*.kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
                'items.*.estimasi_pagu_pecahan' => ['required', 'numeric', 'min:1'],
            ]);

            $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_induk']);
            $totalSplit = array_sum(array_column($validated['items'], 'estimasi_pagu_pecahan'));

            if ($totalSplit > (float) ($induk->estimasi_total_pagu ?? $induk->anggaran_diajukan)) {
                return back()->with('error', 'Total alokasi pagu pecahan melebihi pagu usulan induk.')->withInput();
            }

            DB::transaction(function () use ($validated, $induk, $user, $totalSplit) {
                $induk->estimasi_total_pagu = max(0, (float) ($induk->estimasi_total_pagu ?? 0) - $totalSplit);
                $induk->save();

                foreach ($validated['items'] as $item) {
                    $anak = PermohonanPengadaan::create([
                        'nomor_surat' => $item['nomor_surat_pecahan'],
                        'judul_pengadaan' => $item['judul_pengadaan'],
                        'tujuan_pengadaan_ringkas' => $induk->tujuan_pengadaan_ringkas,
                        'file_permohonan' => $induk->file_permohonan,
                        'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        'status_anggaran' => 'pending',
                        'revisi_ke' => 0,
                        'estimasi_total_pagu' => $item['estimasi_pagu_pecahan'],
                        'kode_unit' => $induk->kode_unit,
                        'id_user' => $induk->id_user,
                        'nomor_surat_induk_split' => $induk->nomor_surat,
                        'tanggal_pemecahan' => now(),
                        'alasan_pemecahan' => $validated['alasan_pemecahan'],
                        'kode_mak' => $item['kode_mak'],
                    ]);

                    VerifikasiParalel::create([
                        'id_user' => $anak->id_user,
                        'nomor_surat' => $anak->nomor_surat,
                        'role_verifikator' => 'perencanaan',
                        'status_keputusan' => 'menunggu',
                    ]);

                    VerifikasiParalel::create([
                        'id_user' => $anak->id_user,
                        'nomor_surat' => $anak->nomor_surat,
                        'role_verifikator' => 'wadir_2',
                        'status_keputusan' => 'menunggu',
                    ]);

                    AuditLog::catat(
                        $user->id_user,
                        'SPLIT_PERMOHONAN_MAK',
                        'permohonan_pengadaan',
                        $anak->nomor_surat,
                        $induk->nomor_surat,
                        PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        "Pemecahan usulan MAK {$item['kode_mak']} (FR 6.3): {$validated['alasan_pemecahan']}"
                    );
                }
            });

            return redirect()->route('verifikasi.perencanaan')
                ->with('success', 'Usulan permohonan berhasil dipecah menjadi '.count($validated['items']).' berkas pecahan baru.');
        }
        if (! $request->filled('nomor_surat_baru') && $nomorInduk) {
            $countChild = PermohonanPengadaan::where('nomor_surat_induk_split', $nomorInduk)->count();
            $request->merge(['nomor_surat_baru' => $nomorInduk.'.'.($countChild + 1)]);
        }
        if (! $request->filled('estimasi_total_pagu') && $request->filled('nominal_split')) {
            $request->merge(['estimasi_total_pagu' => $request->input('nominal_split')]);
        }
        if (! $request->filled('judul_pengadaan') && $nomorInduk) {
            $indukRef = PermohonanPengadaan::find($nomorInduk);
            $judul = ($indukRef?->judul_pengadaan ?? $indukRef?->perihal ?? 'Pecahan Pengadaan').' (Pecahan MAK '.$request->input('kode_mak').')';
            $request->merge(['judul_pengadaan' => $judul]);
        }

        $validated = $request->validate([
            'nomor_surat_induk' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'judul_pengadaan' => ['required', 'string', 'max:255'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'alasan_pemecahan' => ['required', 'string'],
            'estimasi_total_pagu' => ['required', 'numeric', 'min:1'],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_induk']);
        $nominalSplit = (float) $validated['estimasi_total_pagu'];
        $paguInduk = (float) ($induk->estimasi_total_pagu ?? $induk->anggaran_diajukan ?? 0);

        // Batas Atas Integritas Anggaran: Alokasi pecahan dilarang melebihi pagu usulan induk
        if (round($nominalSplit, 2) > round($paguInduk, 2)) {
            return back()->with('error', 'Alokasi pagu pecahan melebihi pagu usulan induk.')->withInput();
        }

        $anak = DB::transaction(function () use ($validated, $induk, $nominalSplit, $paguInduk, $user) {
            // Kurangi pagu permohonan induk
            $induk->estimasi_total_pagu = max(0, $paguInduk - $nominalSplit);
            $induk->save();

            $anak = PermohonanPengadaan::create([
                'nomor_surat' => $validated['nomor_surat_baru'],
                'judul_pengadaan' => $validated['judul_pengadaan'],
                'tujuan_pengadaan_ringkas' => $induk->tujuan_pengadaan_ringkas,
                'file_permohonan' => $induk->file_permohonan,
                'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
                'status_anggaran' => 'pending',
                'revisi_ke' => 0,
                'estimasi_total_pagu' => $validated['estimasi_total_pagu'],
                'kode_unit' => $induk->kode_unit,
                'id_user' => $induk->id_user,
                'nomor_surat_induk_split' => $induk->nomor_surat,
                'tanggal_pemecahan' => now(),
                'alasan_pemecahan' => $validated['alasan_pemecahan'],
                'kode_mak' => $validated['kode_mak'],
            ]);

            // Inisialisasi verifikasi paralel untuk surat pecahan baru
            VerifikasiParalel::create([
                'id_user' => $anak->id_user,
                'nomor_surat' => $anak->nomor_surat,
                'role_verifikator' => 'perencanaan',
                'status_keputusan' => 'menunggu',
            ]);

            VerifikasiParalel::create([
                'id_user' => $anak->id_user,
                'nomor_surat' => $anak->nomor_surat,
                'role_verifikator' => 'wadir_2',
                'status_keputusan' => 'menunggu',
            ]);

            AuditLog::catat(
                $user->id_user,
                'SPLIT_PERMOHONAN_MAK',
                'permohonan_pengadaan',
                $anak->nomor_surat,
                $induk->nomor_surat,
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                'Pemecahan usulan permohonan oleh Perencanaan berdasarkan MAK '.$validated['kode_mak'].' (FR 6.3 & NFR 9): '.$validated['alasan_pemecahan']
            );

            return $anak;
        });

        return redirect()->route('verifikasi.perencanaan')->with(
            'success',
            "Permohonan berhasil dipecah menjadi surat baru {$anak->nomor_surat} dengan alokasi MAK {$validated['kode_mak']} (FR 6.3)."
        );
    }

    // =========================================================================
    // MODUL 4: PAKET PENGADAAN, METODE PENGADAAN, & BELANJA LANGSUNG <= 50 JT (PPBJ)
    // =========================================================================

    /**
     * Menampilkan daftar paket pengadaan, metode pengadaan, form Belanja Langsung <= 50jt, dan mutasi PPK.
     */
    /**
     * Memeriksa dan menyelaraskan status permohonan yang telah disetujui paralel lengkap
     * (MAK teralokasi di Perencanaan, Wadir 2 menyetujui, dan PPK menyetujui)
     * agar otomatis berstatus 'disetujui' dan siap diproses oleh PPBJ.
     */
    public function syncAllPermohonanDisetujuiParalel(): void
    {
        $kandidat = PermohonanPengadaan::with(['verifikasiParalel'])
            ->whereNotIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_SELESAI,
                'digantikan_oleh_revisi',
            ])
            ->whereNotNull('kode_mak')
            ->get();

        foreach ($kandidat as $p) {
            $perencanaanAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty() || ! empty($p->kode_mak);

            $wadirAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty();

            // Evaluasi kelulusan paralel (AND-join): Perencanaan (MAK) + Wadir 2 (ACC) + PPK (ACC)
            $ppkAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty();

            if ($perencanaanAcc && $wadirAcc && $ppkAcc) {
                $statusLama = $p->status_permohonan;
                $p->status = 'disetujui';
                $p->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
                $p->save();

                AuditLog::catat(
                    Auth::id() ?? $p->id_user,
                    'PERSETUJUAN_PENUH_AWAL_KE_PPBJ',
                    'permohonan_pengadaan',
                    $p->nomor_surat,
                    $statusLama,
                    'disetujui',
                    'Sinkronisasi otomatis: Usulan disetujui penuh oleh Perencanaan, Wadir 2, dan PPK terpilih; diteruskan ke PPBJ.'
                );
            }
        }
    }

    public function paketIndex(Request $request): View
    {
        $user = Auth::user();
        $query = PaketPengadaan::with(['permohonan.unit', 'ppkUser', 'ppUser', 'vendor', 'pelaksanaBelanja', 'riwayatPpk.ppkBaru']);

        if ($user) {
            $query->forUser($user);
        }

        if ($request->filled('q') || $request->filled('search')) {
            $kw = $request->input('q', $request->input('search'));
            $query->where(function ($q) use ($kw) {
                $q->where('nomor_paket', 'like', "%{$kw}%")
                    ->orWhere('nama_paket', 'like', "%{$kw}%")
                    ->orWhere('nomor_surat', 'like', "%{$kw}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_paket', $request->input('status'));
        }

        if ($request->filled('jalur_routing')) {
            $query->where('jalur_routing', $request->input('jalur_routing'));
        }

        if ($request->filled('jenis_pengadaan')) {
            $query->where('jenis_pengadaan', $request->input('jenis_pengadaan'));
        }

        $this->syncAllPermohonanDisetujuiParalel();

        $paketList = $query->latest('tanggal_registrasi_paket')->paginate(15);
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();
        $riwayatPenugasan = RiwayatPenugasanPPK::with(['ppkLama', 'ppkBaru', 'userPenetap'])->latest()->take(20)->get();
        $permohonanDisetujui = PermohonanPengadaan::with([
            'unit',
            'ppkAwal',
            'anggaranMak',
            'alokasiMak.anggaranMak',
            'verifikasiParalel.user',
        ])
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            ])
            ->whereDoesntHave('paketPengadaan')
            ->latest('tanggal_pengajuan')
            ->get();
        $vendors = Vendor::all();

        // Paket yang dikembalikan untuk perbaikan (BPMN N18: Titik kembali jika ditolak PP N27 atau PPK N35)
        $paketPerluPerbaikan = PaketPengadaan::with([
            'permohonan.unit',
            'ppkUser',
            'ppUser',
            'vendor',
            'reviuDokumen.user',
        ])
            ->where('status_paket', 'perlu_perbaikan')
            ->latest()
            ->get();

        return view('paket.index', compact('paketList', 'ppkUsers', 'riwayatPenugasan', 'permohonanDisetujui', 'vendors', 'paketPerluPerbaikan'));
    }

    /**
     * Memproses pemecahan (split) berkas permohonan berdasarkan jenis pengadaan (Barang / Jasa / Konstruksi) oleh PPBJ (FR 7.2).
     */
    public function splitJenisPpbj(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $nomorInduk = $request->input('nomor_surat_induk');
        if (! $request->filled('nomor_surat_baru') && $nomorInduk) {
            $countChild = PermohonanPengadaan::where('nomor_surat_induk_split', $nomorInduk)->count();
            $request->merge(['nomor_surat_baru' => $nomorInduk.'.PBJ.'.($countChild + 1)]);
        }

        $validated = $request->validate([
            'nomor_surat_induk' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'judul_pengadaan' => ['required', 'string', 'max:255'],
            'jenis_pengadaan_split' => ['required', 'string', 'in:barang,jasa,konstruksi,jasa_lainnya,jasa_konsultansi,pekerjaan_konstruksi'],
            'estimasi_total_pagu' => ['required', 'numeric', 'min:1'],
            'alasan_pemecahan' => ['required', 'string'],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_induk']);

        // Kurangi pagu permohonan induk secara proporsional
        $nominalSplit = (float) $validated['estimasi_total_pagu'];
        $induk->estimasi_total_pagu = max(0, (float) ($induk->estimasi_total_pagu ?? 0) - $nominalSplit);
        $induk->save();

        $anak = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'perihal' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $induk->tujuan_pengadaan_ringkas,
            'file_permohonan' => $induk->file_permohonan,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => $induk->status_anggaran ?? 'tersedia',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'],
            'anggaran_diajukan' => $validated['estimasi_total_pagu'],
            'kode_unit' => $induk->kode_unit,
            'id_user' => $induk->id_user,
            'id_user_ppk_awal' => $induk->id_user_ppk_awal,
            'nomor_memo_cek_mak' => $induk->nomor_memo_cek_mak,
            'nomor_memo_ketersediaan_mak' => $induk->nomor_memo_ketersediaan_mak,
            'nomor_surat_induk_split' => $induk->nomor_surat,
            'tanggal_pemecahan' => now(),
            'alasan_pemecahan' => $validated['alasan_pemecahan'],
            'kode_mak' => $induk->kode_mak,
            'jenis_pengadaan_split' => $validated['jenis_pengadaan_split'],
        ]);

        AuditLog::catat(
            $user->id_user,
            'SPLIT_BERKAS_JENIS_PPBJ',
            'permohonan_pengadaan',
            $anak->nomor_surat,
            $induk->nomor_surat,
            PermohonanPengadaan::STATUS_DISETUJUI,
            'Pemecahan berkas oleh PPBJ berdasarkan jenis pengadaan '.$validated['jenis_pengadaan_split'].' (FR 7.2): '.$validated['alasan_pemecahan']
        );

        return redirect()->route('paket.index')->with(
            'success',
            "Berkas berhasil dipecah menjadi surat baru {$anak->nomor_surat} untuk jenis pengadaan ".strtoupper($validated['jenis_pengadaan_split']).' (FR 7.2).'
        );
    }

    /**
     * Menampilkan form pendaftaran paket pengadaan baru & penentuan metode pengadaan.
     */
    public function paketCreate(): View
    {
        $this->syncAllPermohonanDisetujuiParalel();

        $permohonanDisetujui = PermohonanPengadaan::with([
            'unit',
            'ppkAwal',
            'anggaranMak',
            'alokasiMak.anggaranMak',
            'verifikasiParalel.user',
        ])
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            ])
            ->get();

        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->take(4)->get();
        $ppUsers = User::where('role', 'pp')->where('status_aktif', true)->get();
        $vendors = Vendor::where('is_active', true)->orderBy('nama_vendor')->get();

        return view('paket.create', compact('permohonanDisetujui', 'ppkUsers', 'ppUsers', 'vendors'));
    }

    /**
     * Memproses penyimpanan paket pengadaan baru, penentuan metode pengadaan (Belanja Langsung / Non-Tender / Tender SPSE),
     * auto-routing 3 jalur (<= 50jt, > 50jt s.d. 200jt, > 200jt), serta sinkronisasi saldo sisa pagu MAK jika HPS disesuaikan.
     */
    public function storePaket(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required', 'string', 'max:100', 'unique:paket_pengadaan,nomor_paket'],
            'nama_paket' => ['required', 'string', 'max:255'],
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'jenis_pengadaan' => ['required', 'string'],
            'metode_pengadaan' => ['required', 'string'],
            'nilai_hps' => ['required', 'numeric', 'gt:0'],
            'nominal_harga_perkiraan' => ['nullable', 'numeric', 'min:0'],
            'nominal_harga_akhir' => ['nullable', 'numeric', 'min:0'],
            'nama_vendor' => ['nullable', 'string', 'max:255'],
            'id_vendor' => ['nullable'],
            'npwp_vendor' => ['nullable'],
            'nomor_memo_ppbj' => ['nullable', 'string', 'max:100'],
            'id_user_ppk' => ['nullable', 'exists:users,id_user'],
            'id_user_pp' => ['nullable', 'exists:users,id_user'],
        ]);

        $permohonan = PermohonanPengadaan::with('anggaranMak')->findOrFail($validated['nomor_surat']);
        $hps = (float) $validated['nilai_hps'];
        $paguAwal = (float) ($permohonan->estimasi_total_pagu ?? 0);
        $selisih = $hps - $paguAwal;
        $mak = $permohonan->kode_mak ? AnggaranMAK::find($permohonan->kode_mak) : null;

        // Sinkronisasi sisa pagu MAK terhadap selisih HPS yang ditetapkan PPBJ
        if ($mak) {
            if ($selisih > 0) {
                if ((float) $mak->sisa_pagu_mak < $selisih) {
                    return back()->withErrors([
                        'nilai_hps' => 'Penetapan HPS (Rp '.number_format($hps, 0, ',', '.').') melebihi pagu awal permohonan (Rp '.number_format($paguAwal, 0, ',', '.').') dan sisa pagu MAK '.$mak->kode_mak.' tidak mencukupi (tersisa Rp '.number_format($mak->sisa_pagu_mak, 0, ',', '.').').',
                    ])->withInput();
                }
                $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $selisih);
                $mak->save();
            } elseif ($selisih < 0) {
                // Efisiensi anggaran dikembalikan ke rekening sisa pagu MAK
                $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + abs($selisih);
                $mak->save();
            }

            $alokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)
                ->where('kode_mak', $mak->kode_mak)
                ->first();
            if ($alokasi) {
                $alokasi->nominal_alokasi = $hps;
                $alokasi->save();
            }
        }

        // Sinkronkan pagu tercatat pada permohonan ke HPS baru
        $permohonan->estimasi_total_pagu = $hps;
        $permohonan->save();

        $jalur = PaketPengadaan::tentukanRouting($hps);

        // Warisi otomatis PPK dari persetujuan awal Perencanaan jika PPBJ tidak mengubahnya
        $inheritedPpk = $validated['id_user_ppk'] ?? $permohonan->id_user_ppk_awal;

        // Validasi penugasan PPK jika nilai > 200 juta
        if ($jalur === PaketPengadaan::ROUTING_PPK && empty($inheritedPpk)) {
            return back()->withErrors(['id_user_ppk' => 'Nilai HPS > Rp 200 Juta wajib menugaskan salah satu dari 4 akun PPK (NFR 4).'])->withInput();
        }

        // Aturan bisnis: Paket > 200 Juta (jalur PPK) tidak menggunakan memo pengadaan PPBJ
        $nomorMemoPpbj = ($jalur === PaketPengadaan::ROUTING_PPK)
            ? null
            : ($validated['nomor_memo_ppbj'] ?? ('MEMO-PPBJ/'.date('Y').'/'.rand(100, 999)));

        // Penentuan pejabat PP dan PPK sesuai jalur nominal Notulensi PPBJ
        $idUserPp = null;
        $idUserPpk = null;

        if ($jalur === PaketPengadaan::ROUTING_LANGSUNG) {
            $metodePengadaan = PaketPengadaan::METODE_BELANJA_LANGSUNG;
        } elseif ($jalur === PaketPengadaan::ROUTING_PP) {
            $idUserPp = $validated['id_user_pp'] ?? User::where('role', 'pp')->where('status_aktif', true)->first()?->id_user;
            $idUserPpk = $inheritedPpk ?? User::where('role', 'ppk')->where('status_aktif', true)->first()?->id_user;
            $metodePengadaan = $validated['metode_pengadaan'];
        } else { // ROUTING_PPK
            $idUserPpk = $inheritedPpk;
            $metodePengadaan = $validated['metode_pengadaan'];
        }

        $platform = match ($metodePengadaan) {
            PaketPengadaan::METODE_E_KATALOG, PaketPengadaan::METODE_NON_TENDER, 'E-Purchasing', 'Surat Pesanan' => 'Inaproc / E-Katalog',
            PaketPengadaan::METODE_SPSE, PaketPengadaan::METODE_TENDER, 'Tender' => 'SPSE',
            PaketPengadaan::METODE_BELANJA_LANGSUNG, 'Pengadaan Langsung' => 'Belanja Langsung',
            default => 'SPSE/Inaproc',
        };

        $hargaPerkiraan = isset($validated['nominal_harga_perkiraan']) ? (float) $validated['nominal_harga_perkiraan'] : $hps;
        $hargaAkhir = isset($validated['nominal_harga_akhir']) ? (float) $validated['nominal_harga_akhir'] : null;

        // Tangani rekanan vendor jika diinput di awal (misal test case / API payload)
        $idVendor = $validated['id_vendor'] ?? null;
        $npwpVendor = $validated['npwp_vendor'] ?? null;
        if (! empty($validated['nama_vendor'])) {
            $v = Vendor::firstOrCreate(['nama_vendor' => trim($validated['nama_vendor'])], ['is_active' => true]);
            $idVendor = $v->id;
            $npwpVendor = $v->npwp;
        }

        $paket = PaketPengadaan::create([
            'kode_paket' => $validated['kode_paket'],
            'nomor_paket' => $validated['kode_paket'],
            'nama_paket' => $validated['nama_paket'],
            'nomor_surat' => $validated['nomor_surat'],
            'jenis_pengadaan' => Str::lower(str_replace(' ', '_', $validated['jenis_pengadaan'])),
            'platform_pengadaan' => $platform,
            'mekanisme_pengadaan' => $metodePengadaan,
            'metode_pengadaan' => $metodePengadaan,
            'nilai_hps' => $hps,
            'alokasi_pagu_paket' => $hps,
            'nominal_harga_perkiraan' => $hargaPerkiraan,
            'nominal_harga_akhir' => $hargaAkhir,
            'nilai_hasil_negosiasi' => $hargaAkhir,
            'id_vendor' => $idVendor,
            'npwp_vendor' => $npwpVendor,
            'jalur_routing' => $jalur,
            'nomor_memo_ppbj' => $nomorMemoPpbj,
            'id_user_ppbj' => $user->id_user,
            'id_user_ppk' => $idUserPpk,
            'id_user_pp' => $idUserPp,
            'tanggal_registrasi_paket' => now(),
            'status_paket' => 'terdaftar',
        ]);

        AuditLog::catat(
            $user->id_user,
            'REGISTRASI_PAKET',
            'paket_pengadaan',
            $paket->kode_paket,
            null,
            'terdaftar',
            "PPBJ mendaftarkan paket {$paket->kode_paket} metode {$metodePengadaan} HPS Rp ".number_format($hps)." jalur routing {$jalur}"
        );

        // Memicu notifikasi penugasan paket ke PPK / PP
        $this->notificationService->notifyPendaftaranPaket($paket);

        return redirect()->route('paket.index')->with(
            'success',
            "Paket pengadaan {$paket->kode_paket} berhasil diterbitkan dengan metode {$metodePengadaan} (jalur: ".strtoupper(str_replace('_', ' ', $jalur)).').'
        );
    }

    /**
     * Mengeksekusi Belanja Langsung untuk paket <= Rp 50.000.000 oleh PPBJ:
     * Output berupa Kwitansi (tanpa Kontrak/SPK/Surat Pesanan) dan opsional jadwal pengiriman (Reminder) ke Perlengkapan.
     */
    public function storeBelanjaLangsungWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'nomor_kwitansi_belanja' => ['required', 'string', 'max:100'],
            'nominal_harga_akhir' => ['nullable', 'numeric', 'gt:0'],
            'nama_vendor' => ['nullable', 'string', 'max:255'],
            'file_dokumen_legalitas' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'id_vendor' => ['nullable'],
            'npwp_vendor' => ['nullable'],
            'jadwal_pengiriman' => ['nullable', 'date'],
            'file_kwitansi_belanja' => ['nullable', DokumenUploadRule::pdfOrImage()],
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['nomor_paket'])->firstOrFail();

        if ($paket->jalur_routing !== PaketPengadaan::ROUTING_LANGSUNG) {
            return back()->withErrors([
                'nomor_paket' => 'Eksekusi Belanja Langsung (Kwitansi tanpa Kontrak/SPK) hanya berlaku untuk paket <= Rp 50.000.000.',
            ])->withInput();
        }

        $kwitansiPath = $request->hasFile('file_kwitansi_belanja')
            ? $request->file('file_kwitansi_belanja')->store('kwitansi_belanja', 'public')
            : ('kwitansi_belanja/kwitansi_'.str_replace(['/', '\\'], '_', $paket->nomor_paket).'.pdf');

        $hargaAkhir = isset($validated['nominal_harga_akhir'])
            ? (float) $validated['nominal_harga_akhir']
            : (float) ($paket->nominal_harga_akhir ?: $paket->nilai_hps);

        // Rekanan vendor diinput secara inline saat belanja langsung
        if (! empty($validated['nama_vendor'])) {
            $legalitasPath = $request->hasFile('file_dokumen_legalitas')
                ? $request->file('file_dokumen_legalitas')->store('vendor_legalitas', 'public')
                : null;

            $vendor = Vendor::firstOrCreate(
                ['nama_vendor' => trim($validated['nama_vendor'])],
                [
                    'file_dokumen_legalitas' => $legalitasPath,
                    'is_active' => true,
                ]
            );
            if ($legalitasPath && empty($vendor->file_dokumen_legalitas)) {
                $vendor->file_dokumen_legalitas = $legalitasPath;
                $vendor->save();
            }
            $paket->id_vendor = $vendor->id;
            $paket->npwp_vendor = $vendor->npwp;
        } elseif (! empty($validated['id_vendor'])) {
            $paket->id_vendor = $validated['id_vendor'];
        } elseif (! empty($validated['npwp_vendor'])) {
            $paket->npwp_vendor = $validated['npwp_vendor'];
        }

        $statusLama = $paket->status_paket;
        $paket->nomor_kwitansi_belanja = $validated['nomor_kwitansi_belanja'];
        $paket->file_kwitansi_belanja = $kwitansiPath;
        $paket->nominal_harga_akhir = $hargaAkhir;
        $paket->nilai_hasil_negosiasi = $hargaAkhir;
        $paket->dieksekusi_belanja_oleh = $user->id_user;
        $paket->tanggal_eksekusi_belanja = now();
        $paket->status_paket = PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI;
        $paket->save();

        // Bangkitkan pengingat (reminder) dan tembusan persiapan BAST ke Perlengkapan & Keuangan
        PengadaanRoutingService::generateBelanjaLangsungReminder($paket, $validated['jadwal_pengiriman'] ?? null);

        AuditLog::catat(
            $user->id_user,
            'EKSEKUSI_BELANJA_LANGSUNG_KWITANSI',
            'paket_pengadaan',
            $paket->nomor_paket,
            $statusLama,
            PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI,
            "PPBJ mengeksekusi Belanja Langsung paket {$paket->nomor_paket} dengan Kwitansi {$paket->nomor_kwitansi_belanja} (tanpa Kontrak/SPK)."
        );

        return redirect()->route('paket.index')->with(
            'success',
            "Belanja Langsung paket {$paket->nomor_paket} berhasil dieksekusi dengan Kwitansi {$paket->nomor_kwitansi_belanja} (tanpa Kontrak/SPK). Siap pemeriksaan fisik & BAST Perlengkapan."
        );
    }

    /**
     * Memproses alih tugas (reassign) paket ke PPK lain oleh PPBJ (tetap didukung meski PPK awal dipilih Perencanaan).
     */
    public function reassignPpkWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required'],
            'id_user_ppk_baru' => ['required', 'exists:users,id_user'],
            'alasan_pengalihan' => ['required', 'string'],
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['kode_paket'])
            ->firstOrFail();

        $ppkBaru = User::findOrFail($validated['id_user_ppk_baru']);
        if ($ppkBaru->role !== 'ppk') {
            return back()->withErrors(['id_user_ppk_baru' => 'User tujuan mutasi harus berstatus akun PPK.']);
        }

        $ppkLama = $paket->id_user_ppk;
        $paket->id_user_ppk = $ppkBaru->id_user;
        $paket->save();

        RiwayatPenugasanPPK::create([
            'kode_paket' => $paket->kode_paket,
            'nomor_paket' => $paket->nomor_paket,
            'id_ppk_lama' => $ppkLama,
            'ppk_lama_user_id' => $ppkLama,
            'id_ppk_baru' => $ppkBaru->id_user,
            'ppk_baru_user_id' => $ppkBaru->id_user,
            'id_user_penetap' => $user->id_user,
            'diubah_oleh_user_id' => $user->id_user,
            'alasan_pengalihan' => $validated['alasan_pengalihan'],
            'alasan_perubahan' => $validated['alasan_pengalihan'],
            'tanggal_penugasan' => now(),
        ]);

        AuditLog::catat(
            $user->id_user,
            'REASSIGN_PPK',
            'paket_pengadaan',
            $paket->kode_paket,
            (string) $ppkLama,
            (string) $ppkBaru->id_user,
            "Alih tugas PPK paket {$paket->kode_paket} ke {$ppkBaru->nama}: {$validated['alasan_pengalihan']}"
        );

        $this->notificationService->notifyReassignPpk($paket, $ppkBaru, $validated['alasan_pengalihan']);

        return redirect()->route('paket.index')->with(
            'success',
            "Penugasan paket {$paket->kode_paket} berhasil dialihkan ke PPK {$ppkBaru->nama}."
        );
    }

    /**
     * Menampilkan form revisi/perbaikan dokumen pengadaan oleh PPBJ (BPMN N18).
     * Pemicu: Paket dikembalikan dengan catatan revisi oleh PP (N27: Tidak) atau PPK (N35: Tidak).
     */
    public function paketEdit(string $id): View
    {
        $paket = PaketPengadaan::with([
            'permohonan.unit',
            'permohonan.anggaranMak',
            'permohonan.verifikasiParalel.user',
            'reviuDokumen.user',
            'ppkUser',
            'ppUser',
            'vendor',
        ])->where('nomor_paket', $id)->firstOrFail();

        $user = Auth::user();
        if ($user && ! $user->hasRole('admin') && ! $user->hasRole('ppbj') && ! $user->hasAnyPermission(['paket.create', 'paket.view_all'])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk mengubah dokumen pengadaan.');
        }

        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->take(4)->get();
        $ppUsers = User::where('role', 'pp')->where('status_aktif', true)->get();
        $vendors = Vendor::where('is_active', true)->orderBy('nama_vendor')->get();

        return view('paket.edit', compact('paket', 'ppkUsers', 'ppUsers', 'vendors'));
    }

    /**
     * Memproses pembaruan dokumen pengadaan oleh PPBJ setelah perbaikan (BPMN N18 -> N22 -> N25 -> N28).
     */
    public function paketUpdate(Request $request, string $id): RedirectResponse
    {
        $user = Auth::user();
        $paket = PaketPengadaan::with('permohonan.anggaranMak')->where('nomor_paket', $id)->firstOrFail();

        $validated = $request->validate([
            'nama_paket' => ['required', 'string', 'max:255'],
            'jenis_pengadaan' => ['required', 'string'],
            'metode_pengadaan' => ['required', 'string'],
            'nilai_hps' => ['required', 'numeric', 'gt:0'],
            'nominal_harga_perkiraan' => ['nullable', 'numeric', 'min:0'],
            'nominal_harga_akhir' => ['nullable', 'numeric', 'min:0'],
            'nama_vendor' => ['nullable', 'string', 'max:255'],
            'id_vendor' => ['nullable'],
            'npwp_vendor' => ['nullable'],
            'id_user_ppk' => ['nullable', 'exists:users,id_user'],
            'id_user_pp' => ['nullable', 'exists:users,id_user'],
            'catatan_perbaikan_ppbj' => ['nullable', 'string'],
        ]);

        $hps = (float) $validated['nilai_hps'];
        $hpsLama = (float) $paket->nilai_hps;
        $selisih = $hps - $hpsLama;
        $mak = $paket->permohonan?->kode_mak ? AnggaranMAK::find($paket->permohonan->kode_mak) : null;

        if ($mak && $selisih != 0) {
            if ($selisih > 0) {
                if ((float) $mak->sisa_pagu_mak < $selisih) {
                    return back()->withErrors([
                        'nilai_hps' => 'Penyesuaian HPS melebihi sisa pagu MAK '.$mak->kode_mak.' yang tersedia (tersisa Rp '.number_format($mak->sisa_pagu_mak, 0, ',', '.').').',
                    ])->withInput();
                }
                $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $selisih);
                $mak->save();
            } else {
                $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + abs($selisih);
                $mak->save();
            }

            if ($paket->nomor_surat) {
                $alokasi = AlokasiMAK::where('nomor_surat', $paket->nomor_surat)
                    ->where('kode_mak', $mak->kode_mak)
                    ->first();
                if ($alokasi) {
                    $alokasi->nominal_alokasi = $hps;
                    $alokasi->save();
                }
            }
        }

        // Jalur routing baru berdasarkan nominal HPS (Gateway BPMN N28)
        $jalur = PaketPengadaan::tentukanRouting($hps);

        // Rekanan vendor
        $idVendor = $validated['id_vendor'] ?? $paket->id_vendor;
        $npwpVendor = $validated['npwp_vendor'] ?? $paket->npwp_vendor;
        if (! empty($validated['nama_vendor'])) {
            $v = Vendor::firstOrCreate(['nama_vendor' => trim($validated['nama_vendor'])], ['is_active' => true]);
            $idVendor = $v->id;
            $npwpVendor = $v->npwp;
        }

        $idUserPpk = $validated['id_user_ppk'] ?? $paket->id_user_ppk;
        $idUserPp = ($jalur === PaketPengadaan::ROUTING_PP)
            ? ($validated['id_user_pp'] ?? $paket->id_user_pp ?? User::where('role', 'pp')->where('status_aktif', true)->first()?->id_user)
            : null;

        $platform = match ($validated['metode_pengadaan']) {
            PaketPengadaan::METODE_E_KATALOG, PaketPengadaan::METODE_NON_TENDER, 'E-Purchasing', 'Surat Pesanan' => 'Inaproc / E-Katalog',
            PaketPengadaan::METODE_SPSE, PaketPengadaan::METODE_TENDER, 'Tender' => 'SPSE',
            PaketPengadaan::METODE_BELANJA_LANGSUNG, 'Pengadaan Langsung' => 'Belanja Langsung',
            default => 'SPSE/Inaproc',
        };

        // Reset status paket sesuai rute BPMN N28
        $statusBaru = ($jalur === PaketPengadaan::ROUTING_PPK)
            ? PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK
            : 'terdaftar';

        $paket->update([
            'nama_paket' => $validated['nama_paket'],
            'jenis_pengadaan' => Str::lower(str_replace(' ', '_', $validated['jenis_pengadaan'])),
            'platform_pengadaan' => $platform,
            'mekanisme_pengadaan' => $validated['metode_pengadaan'],
            'metode_pengadaan' => $validated['metode_pengadaan'],
            'nilai_hps' => $hps,
            'alokasi_pagu_paket' => $hps,
            'nominal_harga_perkiraan' => $validated['nominal_harga_perkiraan'] ?? $hps,
            'nominal_harga_akhir' => $validated['nominal_harga_akhir'] ?? null,
            'id_vendor' => $idVendor,
            'npwp_vendor' => $npwpVendor,
            'jalur_routing' => $jalur,
            'id_user_ppk' => $idUserPpk,
            'id_user_pp' => $idUserPp,
            'status_paket' => $statusBaru,
        ]);

        AuditLog::catat(
            $user->id_user,
            'PERBAIKAN_DOKUMEN_PENGADAAN',
            'paket_pengadaan',
            $paket->nomor_paket,
            'perlu_perbaikan',
            $statusBaru,
            'PPBJ menyusun ulang dokumen pengadaan (BPMN N18/N22/N25) dengan HPS Rp '.number_format($hps).' menuju jalur '.strtoupper(str_replace('_', ' ', $jalur)).': '.($validated['catatan_perbaikan_ppbj'] ?? 'Dokumen telah diperbaiki')
        );

        // Notifikasi ulang
        $this->notificationService->notifyPendaftaranPaket($paket);

        return redirect()->route('paket.index')->with(
            'success',
            "Dokumen paket {$paket->nomor_paket} berhasil diperbaiki dan diteruskan kembali ke jalur ".strtoupper(str_replace('_', ' ', $jalur)).' (BPMN N28).'
        );
    }

    /**
     * Menampilkan detail siklus paket pengadaan secara komprehensif (Feature 23).
     */
    public function paketShow(Request $request, string $id): View
    {
        $paket = PaketPengadaan::with([
            'permohonan.unit',
            'permohonan.ppkAwal',
            'permohonan.verifikasiParalel.user',
            'ppbjUser',
            'ppkUser',
            'ppUser',
            'vendor',
            'riwayatPpk.ppkLama',
            'riwayatPpk.ppkBaru',
            'riwayatPpk.diubahOleh',
            'kontrakSpk',
            'penerimaanBarang',
            'pembayaran.memoBayar.user',
            'reviuDokumen.user',
        ])->where('nomor_paket', $id)->firstOrFail();

        $user = Auth::user();
        if ($user && $user->cannot('view', $paket)) {
            abort(403, 'Akses Ditolak: Berkas paket pengadaan ini ditugaskan kepada PPK lain (Isolasi Berkas PPK).');
        }

        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('paket.show', compact('paket', 'ppkUsers'));
    }

    /**
     * Menampilkan form pemecahan jenis pengadaan (standalone page).
     */
    public function splitJenisCreate(): View
    {
        $permohonanDisetujui = PermohonanPengadaan::where('status_permohonan', PermohonanPengadaan::STATUS_DISETUJUI)->get();

        return view('paket.split_jenis', compact('permohonanDisetujui'));
    }

    /**
     * Menampilkan form alih tugas PPK (standalone page).
     */
    public function reassignPpkCreate(Request $request): View
    {
        $user = Auth::user();
        $query = PaketPengadaan::with(['ppkUser']);
        if ($user) {
            $query->forUser($user);
        }
        $paketList = $query->get();
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('paket.reassign', compact('paketList', 'ppkUsers'));
    }

    // =========================================================================
    // MODUL 5: REVIEW PP, APPROVAL PPK, EKSEKUSI BELANJA, & UPLOAD 1 DOKUMEN PERIKATAN
    // =========================================================================

    /**
     * Menampilkan daftar paket penugasan (> 50 Juta), panel Review PP, Approval PPK, Eksekusi Belanja,
     * dan Upload salah satu dokumen perikatan (Surat Pesanan / SPK / Surat Kontrak) oleh PPBJ.
     */
    public function kontrakIndex(Request $request): View
    {
        $user = Auth::user();
        $paketQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser', 'vendor', 'kontrakSpk', 'pelaksanaBelanja'])
            ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);

        if ($user) {
            $paketQuery->forUser($user);
        }

        $paketList = $paketQuery->get();

        $kontrakQuery = KontrakSPK::with(['paket.vendor', 'paket.ppkUser']);
        if ($user) {
            $kontrakQuery->forUser($user);
        }

        if ($request->filled('q') || $request->filled('search')) {
            $kw = $request->input('q', $request->input('search'));
            $kontrakQuery->where(function ($q) use ($kw) {
                $q->where('nomor_spk', 'like', "%{$kw}%")
                    ->orWhere('nomor_paket', 'like', "%{$kw}%")
                    ->orWhereHas('vendor', function ($vq) use ($kw) {
                        $vq->where('nama_vendor', 'like', "%{$kw}%");
                    });
            });
        }

        if ($request->filled('status_spk')) {
            $kontrakQuery->where('status_spk', $request->input('status_spk'));
        }

        $kontrakList = $kontrakQuery->latest()->paginate(15);
        $vendors = Vendor::all();

        // Ambil paket > 50jt yang sedang dalam proses review PP, approval PPK, atau eksekusi belanja
        $reviuQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser', 'vendor', 'reviuDokumen', 'kontrakSpk'])
            ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);
        if ($user) {
            $reviuQuery->forUser($user);
        }
        $paketReviu = $reviuQuery->whereIn('status_paket', [
            'terdaftar',
            'reviu_dokumen',
            'perlu_perbaikan',
            PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
            PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
            'reviu_dokumen_disetujui',
            PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
        ])->get();

        return view('kontrak.index', compact('paketList', 'kontrakList', 'vendors', 'paketReviu'));
    }

    /**
     * Menampilkan dashboard monitoring aktivitas Pejabat Pengadaan (PP)
     * untuk Koordinator PPBJ, Pengawas, dan Administrator (Feature: lihat_pp / pp.monitoring_aktivitas).
     */
    public function monitorPpIndex(Request $request): View
    {
        $user = Auth::user();

        $query = PaketPengadaan::with([
            'permohonan.unit',
            'ppUser',
            'ppkUser',
            'vendor',
            'reviuDokumen.user',
            'kontrakSpk',
        ])->where('jalur_routing', PaketPengadaan::ROUTING_PP);

        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('nomor_paket', 'like', "%{$kw}%")
                    ->orWhere('nama_paket', 'like', "%{$kw}%")
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('nama_vendor', 'like', "%{$kw}%"))
                    ->orWhereHas('ppUser', fn ($pq) => $pq->where('nama', 'like', "%{$kw}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status_paket', $request->status);
        }

        $paketPpList = $query->latest('tanggal_registrasi_paket')->paginate(15);
        $totalPaketPp = PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->count();
        $paketMenungguReviu = PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
            ->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])
            ->count();
        $paketSelesaiReviu = PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
            ->whereIn('status_paket', [
                PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
                PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
                'spk_terbit',
                'penerimaan_selesai',
            ])->count();
        $totalPaguPp = (float) PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->sum('nilai_hps');
        $totalNegosiasiPp = (float) PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->whereNotNull('nilai_hasil_negosiasi')->sum('nilai_hasil_negosiasi');
        $pejabatPpList = User::where('role', 'pp')
            ->orWhereHas('roleEntity.permissions', fn ($q) => $q->where('nama_permission', 'reviu.pp'))
            ->get();

        return view('pp.monitoring', compact(
            'paketPpList',
            'totalPaketPp',
            'paketMenungguReviu',
            'paketSelesaiReviu',
            'totalPaguPp',
            'totalNegosiasiPp',
            'pejabatPpList',
            'user'
        ));
    }

    /**
     * Memproses upload salah satu dokumen perikatan terpilih (Surat Pesanan / SPK / Surat Kontrak)
     * khusus untuk paket > Rp 50.000.000 beserta jadwal pengiriman (Reminder) tanpa mengunci MAK.
     */
    public function storeKontrak(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required'],
            'nama_vendor' => ['nullable', 'string', 'max:255'],
            'file_dokumen_legalitas' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'id_vendor' => ['nullable'],
            'npwp_vendor' => ['nullable'],
            'tipe_dokumen' => ['nullable', 'string', 'in:spk,surat_pesanan,surat_kontrak,SPK,Kontrak,Surat Pesanan,Surat Kontrak'],
            'nomor_kontrak_spk' => ['required', 'string', 'max:100', 'unique:kontrak_spk,nomor_spk'],
            'nilai_kontrak' => ['required', 'numeric', 'min:1'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'catatan_spk' => ['nullable', 'string'],
            'file_spk' => ['nullable', DokumenUploadRule::pdfOnly()],
            'sifat_kontrak' => ['nullable', 'string'],
            'jadwal_pengiriman' => ['nullable', 'date'],
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['kode_paket'])
            ->firstOrFail();

        // Larang Kontrak/SPK/Surat Pesanan untuk paket <= Rp 50 Juta (Belanja Langsung)
        $nominalPaket = (float) ($paket->nilai_hps ?: $paket->alokasi_pagu_paket);
        if ($paket->jalur_routing === PaketPengadaan::ROUTING_LANGSUNG || $nominalPaket <= 50000000) {
            return back()->withErrors([
                'kode_paket' => 'Paket di bawah atau sama dengan Rp 50.000.000 adalah Belanja Langsung (output Kwitansi, tidak ada Kontrak/SPK/Surat Pesanan).',
            ])->withInput();
        }

        // Larang upload lebih dari 1 dokumen perikatan per paket (pilih salah satu)
        if (KontrakSPK::where('nomor_paket', $paket->nomor_paket)->exists()) {
            return back()->withErrors([
                'kode_paket' => 'Paket ini sudah memiliki 1 dokumen perikatan. Pilih salah satu antara Surat Pesanan, SPK, atau Surat Kontrak (bukan diunggah keduanya).',
            ])->withInput();
        }

        // Rekanan vendor: bisa diinput secara inline (nama_vendor + file_dokumen_legalitas) atau dipilih
        if (! empty($validated['nama_vendor'])) {
            $legalitasPath = $request->hasFile('file_dokumen_legalitas')
                ? $request->file('file_dokumen_legalitas')->store('vendor_legalitas', 'public')
                : null;

            $vendor = Vendor::firstOrCreate(
                ['nama_vendor' => trim($validated['nama_vendor'])],
                [
                    'file_dokumen_legalitas' => $legalitasPath,
                    'is_active' => true,
                ]
            );
            if ($legalitasPath && empty($vendor->file_dokumen_legalitas)) {
                $vendor->file_dokumen_legalitas = $legalitasPath;
                $vendor->save();
            }
            $paket->id_vendor = $vendor->id;
            $paket->npwp_vendor = $vendor->npwp;
        } elseif (! empty($validated['id_vendor'])) {
            $paket->id_vendor = $validated['id_vendor'];
        } elseif (! empty($validated['npwp_vendor'])) {
            $paket->npwp_vendor = $validated['npwp_vendor'];
        }

        if (empty($paket->id_vendor) && empty($paket->npwp_vendor)) {
            return back()->withErrors(['nama_vendor' => 'Penyedia/Vendor pemenang wajib diisi atau dipilih sebelum mengunggah dokumen perikatan.'])->withInput();
        }

        $tipeInput = Str::lower(str_replace(' ', '_', $validated['tipe_dokumen'] ?? 'spk'));
        $tipeDokumen = match ($tipeInput) {
            'surat_pesanan' => KontrakSPK::DOKUMEN_SURAT_PESANAN,
            'kontrak', 'surat_kontrak' => KontrakSPK::DOKUMEN_SURAT_KONTRAK,
            default => KontrakSPK::DOKUMEN_SPK,
        };

        $fileSpkPath = 'spk/'.str_replace(['/', '\\'], '_', $validated['nomor_kontrak_spk']).'.pdf';
        if ($request->hasFile('file_spk')) {
            $fileSpkPath = $request->file('file_spk')->store('spk', 'public');
        }

        $kontrak = KontrakSPK::create([
            'nomor_spk' => $validated['nomor_kontrak_spk'],
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $user->id_user,
            'tipe_dokumen' => $tipeDokumen,
            'sifat_kontrak' => KontrakSPK::TIPE_LUMPSUM,
            'status_spk' => 'aktif',
            'tanggal_spk' => now(),
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'jadwal_pengiriman' => $validated['jadwal_pengiriman'] ?? $validated['tanggal_selesai'],
            'nilai_kontrak' => $validated['nilai_kontrak'],
            'file_spk' => $fileSpkPath,
        ]);

        $paket->status_paket = 'spk_terbit';
        $paket->save();

        // Bangkitkan tembusan dan reminder pengiriman barang ke Perlengkapan & Keuangan
        PengadaanRoutingService::generateReminders($paket, $kontrak);

        AuditLog::catat(
            $user->id_user,
            'UPLOAD_DOKUMEN_PERIKATAN_TUNGGAL',
            'kontrak_spk',
            $kontrak->nomor_spk,
            null,
            'aktif',
            "Unggah dokumen perikatan terpilih ({$tipeDokumen}) nomor {$kontrak->nomor_spk} nilai Rp ".number_format($kontrak->nilai_kontrak)
        );

        $this->notificationService->notifyPenerbitanKontrak($kontrak);

        return redirect()->route('kontrak.index')->with(
            'success',
            'Dokumen perikatan ('.strtoupper(str_replace('_', ' ', $tipeDokumen)).") nomor {$kontrak->nomor_spk} berhasil diunggah beserta jadwal Reminder dan tembusan ke Perlengkapan & Keuangan."
        );
    }

    /**
     * Memproses penelaahan/verifikasi dokumen pengadaan oleh PP (BPMN N21 -> N24 -> N27 -> N31)
     * atau persetujuan dokumen pengadaan oleh PPK (BPMN N30 -> N33 -> N35 -> N36).
     */
    public function storeReviuWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'status_reviu' => ['required', 'in:terverifikasi_pp,disetujui,perlu_perbaikan'],
            'catatan_perbaikan' => ['required_if:status_reviu,perlu_perbaikan', 'nullable', 'string'],
        ], [
            'catatan_perbaikan.required_if' => 'Catatan perbaikan / alasan penolakan wajib diisi saat meminta perbaikan dokumen pengadaan (BPMN N18).',
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['nomor_paket'])->firstOrFail();

        // Penegakan wewenang: PP tidak bisa klik Approval final (hanya PPK yang berwenang - BPMN N35)
        if (($user->hasRole('pp') || $user->hasPermission('reviu.pp')) && ! $user->hasRole('admin') && ! $user->hasPermission('reviu.ppk') && $validated['status_reviu'] === 'disetujui') {
            return back()->withErrors([
                'status_reviu' => 'Pejabat Pengadaan (PP) tidak memiliki wewenang persetujuan final (Approval). Persetujuan dokumen dilakukan oleh PPK (BPMN N35).',
            ])->withInput();
        }

        // Penegakan isolasi berkas 4 akun PPK
        if ($user->hasPermission('reviu.ppk') && ! $user->hasRole('admin') && $paket->id_user_ppk && (int) $paket->id_user_ppk !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak mereviu atau meng-approve paket yang ditugaskan kepada PPK lain (Isolasi Berkas PPK).');
        }

        $nextRound = (ReviuDokumen::where('nomor_paket', $paket->nomor_paket)->max('putaran_ke') ?? 0) + 1;
        $isPp = ($user->hasRole('pp') || $user->hasPermission('reviu.pp')) && ! ($user->hasPermission('reviu.ppk') && $validated['status_reviu'] === 'disetujui');

        ReviuDokumen::create([
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $user->id_user,
            'putaran_ke' => $nextRound,
            'role_reviewer' => $isPp ? 'pp' : 'ppk',
            'status_reviu' => $validated['status_reviu'],
            'catatan_perbaikan' => $validated['catatan_perbaikan'],
            'tanggal_reviu' => now(),
        ]);

        $statusLama = $paket->status_paket;

        // KASUS 1: PP Verifikasi Berhasil (BPMN N27: Iya -> N31: Kirim ke PPK N30)
        if ($validated['status_reviu'] === 'terverifikasi_pp') {
            $paket->status_paket = PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK;
            $paket->catatan_verifikasi_pp = $validated['catatan_perbaikan'] ?? 'Dokumen pengadaan lolos verifikasi teknis & HPS oleh PP (BPMN N27: Iya); berkas diteruskan ke PPK (N31 -> N30).';
            $paket->save();

            AuditLog::catat(
                $user->id_user,
                'REVIEW_DOKUMEN_PP_BERHASIL',
                'paket_pengadaan',
                $paket->nomor_paket,
                $statusLama,
                $paket->status_paket,
                "PP menyatakan verifikasi dokumen pengadaan paket {$paket->nomor_paket} berhasil (BPMN N27: Iya); dokumen diteruskan ke PPK (N31 -> N30)."
            );

            $this->notificationService->notifyReviewPpKePpk($paket, $validated['catatan_perbaikan'] ?? null);

            return redirect()->route('kontrak.index')->with(
                'success',
                "Verifikasi dokumen pengadaan paket {$paket->nomor_paket} berhasil (BPMN N27 & N31). Berkas diteruskan ke PPK untuk persetujuan (N30)."
            );
        }

        // KASUS 2: PPK Menyetujui Dokumen Pengadaan (BPMN N35: Iya -> N36: Teruskan ke PPBJ N32/N34/N37)
        if ($validated['status_reviu'] === 'disetujui') {
            $paket->status_paket = PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ;
            $paket->save();

            AuditLog::catat(
                $user->id_user,
                'APPROVAL_PENGADAAN_PPK',
                'paket_pengadaan',
                $paket->nomor_paket,
                $statusLama,
                $paket->status_paket,
                "PPK menyetujui dokumen pengadaan paket {$paket->nomor_paket} (BPMN N35: Iya); berkas diteruskan ke PPBJ untuk melakukan pengadaan & membuat SPK/Kontrak (N32/N34/N37)."
            );

            $this->notificationService->notifySiapUploadDokumenPpbj($paket, 'ppk');

            return redirect()->route('kontrak.index')->with(
                'success',
                "Dokumen pengadaan paket {$paket->nomor_paket} berhasil disetujui oleh PPK (BPMN N35). Berkas diteruskan ke PPBJ untuk pelaksanaan pengadaan & penerbitan SPK/Kontrak (N32 & N37)."
            );
        }

        // KASUS 3: Penolakan / Perlu Perbaikan (PP N27: Tidak ATAU PPK N35: Tidak -> N18 PPBJ Susun Ulang)
        $paket->status_paket = 'perlu_perbaikan';
        $paket->catatan_verifikasi_pp = ($isPp ? 'Catatan PP: ' : 'Catatan PPK: ').$validated['catatan_perbaikan'];
        $paket->save();

        AuditLog::catat(
            $user->id_user,
            'REVIU_DOKUMEN_PERBAIKAN',
            'paket_pengadaan',
            $paket->nomor_paket,
            $statusLama,
            $paket->status_paket,
            'Penelaah ('.($isPp ? 'PP' : 'PPK').") mengembalikan dokumen paket {$paket->nomor_paket} ke PPBJ untuk disusun ulang (BPMN N18): ".$validated['catatan_perbaikan']
        );

        $this->notificationService->notifyReviuDokumen($paket, 'perlu_perbaikan', $validated['catatan_perbaikan'] ?? null);

        return redirect()->route('kontrak.index')->with(
            'success',
            "Dokumen pengadaan paket {$paket->nomor_paket} dikembalikan ke PPBJ untuk perbaikan (BPMN N18)."
        );
    }

    // =========================================================================
    // MODUL 6: PENERIMAAN BARANG & BAST MURNI DI PERLENGKAPAN (<= 50JT & > 50JT)
    // =========================================================================

    /**
     * Menampilkan antrean pemeriksaan fisik barang dan daftar BAST (mendukung paket > 50jt ber-SPK/Kontrak/Surat Pesanan
     * maupun paket <= 50jt Belanja Langsung ber-Kwitansi).
     */
    public function penerimaanIndex(): View
    {
        $kontrakList = KontrakSPK::with(['paket.vendor'])
            ->whereIn('status_spk', ['aktif', 'terbit'])
            ->whereDoesntHave('paket.penerimaanBarang')
            ->get();
        $belanjaLangsungList = PaketPengadaan::with(['vendor', 'permohonan'])
            ->where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
            ->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
            ->whereDoesntHave('penerimaanBarang')
            ->get();
        $bastList = PenerimaanBarang::with(['kontrak', 'paket.vendor', 'fotoDokumentasi', 'user'])->latest()->paginate(15);

        return view('penerimaan.index', compact('kontrakList', 'belanjaLangsungList', 'bastList'));
    }

    /**
     * Memproses pemeriksaan fisik barang, penentuan Belanja Barang/Modal, upload foto fleksibel,
     * dan penerbitan BAST bertoken HMAC oleh Unit Perlengkapan (BPMN N46, N48, N49, N50).
     */
    public function storePenerimaan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (empty($request->nomor_bast)) {
            $request->merge(['nomor_bast' => 'BAST/'.date('Ymd').'/'.strtoupper(Str::random(6))]);
        }
        if ($request->filled('status_fisik') && ! $request->filled('kondisi_barang')) {
            $request->merge(['kondisi_barang' => $request->input('status_fisik')]);
        }
        if ($request->filled('kondisi_barang') && ! $request->filled('status_fisik')) {
            $request->merge(['status_fisik' => $request->input('kondisi_barang')]);
        }

        $validated = $request->validate([
            'nomor_kontrak_spk' => ['required_without:nomor_paket', 'nullable', 'exists:kontrak_spk,nomor_spk'],
            'nomor_paket' => ['required_without:nomor_kontrak_spk', 'nullable', 'exists:paket_pengadaan,nomor_paket'],
            'nomor_bast' => ['required', 'string', 'max:100', 'unique:penerimaan_barang,nomor_bast'],
            'tanggal_terima' => ['required', 'date'],
            'kondisi_barang' => ['nullable', 'string'],
            'status_fisik' => ['nullable', 'string'],
            'kategori_belanja' => ['nullable', 'string', 'in:belanja_barang,belanja_modal'],
            'foto_barang' => ['nullable'],
            'foto_barang.*' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_bast_signed' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_penerimaan_pihak3' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'catatan_pemeriksaan' => ['nullable', 'string'],
        ]);

        $kondisi = $validated['status_fisik'] ?? $validated['kondisi_barang'] ?? 'terima';

        $nomorPaket = $validated['nomor_paket'] ?? null;
        if (empty($nomorPaket)) {
            $spk = KontrakSPK::where('nomor_spk', $validated['nomor_kontrak_spk'])->firstOrFail();
            $nomorPaket = $spk->nomor_paket;
        }

        // 1. Simpan dokumen pihak ketiga jika ada
        $pihak3Path = null;
        if ($request->hasFile('file_penerimaan_pihak3')) {
            $pihak3Path = $request->file('file_penerimaan_pihak3')->store('pihak3', 'public');
        }

        // 2. Simpan naskah fisik BAST jika diunggah atau generate path dokumen resmi
        $bastSignedPath = 'bast/'.str_replace(['/', '\\'], '_', $validated['nomor_bast']).'.pdf';
        if ($request->hasFile('file_bast_signed')) {
            $bastSignedPath = $request->file('file_bast_signed')->store('bast', 'public');
        }

        // 3. Buat token HMAC SHA-256 untuk verifikasi keabsahan hukum dokumen BAST
        $tokenHmac = PenerimaanBarang::generateSignatureToken($validated['nomor_bast'], $user->id_user);

        // 4. Simpan data BAST (BPMN N48 & N49)
        $penerimaan = PenerimaanBarang::create([
            'nomor_bast' => $validated['nomor_bast'],
            'nomor_paket' => $nomorPaket,
            'id_user' => $user->id_user,
            'tanggal_penerimaan' => $validated['tanggal_terima'],
            'status_fisik' => $kondisi,
            'kategori_belanja' => $validated['kategori_belanja'] ?? PenerimaanBarang::KATEGORI_BELANJA_BARANG,
            'kondisi_barang' => $kondisi,
            'ttd_digital_bast' => $tokenHmac,
            'file_bast_signed' => $bastSignedPath,
            'file_penerimaan_pihak3' => $pihak3Path,
            'catatan_pemeriksaan' => $validated['catatan_pemeriksaan'],
            'waktu_selesai_inspeksi' => now(),
        ]);

        // 5. Simpan foto dokumentasi fisik barang (BPMN N46 & N49)
        if ($request->hasFile('foto_barang')) {
            $files = is_array($request->file('foto_barang')) ? $request->file('foto_barang') : [$request->file('foto_barang')];
            foreach ($files as $idx => $f) {
                $path = $f->store('dokumentasi_barang', 'public');
                DokumentasiPenerimaanBarang::create([
                    'id_penerimaan' => $penerimaan->id_penerimaan,
                    'foto_dokumentasi' => $path,
                    'keterangan_foto' => 'Foto fisik barang #'.($idx + 1),
                ]);
            }
        } else {
            DokumentasiPenerimaanBarang::create([
                'id_penerimaan' => $penerimaan->id_penerimaan,
                'foto_dokumentasi' => 'dokumentasi_barang/item_default.jpg',
                'keterangan_foto' => 'Foto fisik barang item terlihat',
            ]);
        }

        // 6. Perbarui status paket agar diterima di N51 PPBJ & siap dibuat dokumen pembayaran di N52
        $paket = PaketPengadaan::find($nomorPaket);
        $isTerima = in_array($kondisi, ['terima', 'diterima_lengkap', 'sesuai'], true);
        if ($paket) {
            $paket->status_paket = 'penerimaan_selesai';
            $paket->save();
        }

        AuditLog::catat(
            $user->id_user,
            'TERBITKAN_BAST_KIRIM_PPBJ',
            'penerimaan_barang',
            $penerimaan->nomor_bast,
            null,
            $penerimaan->status_fisik,
            "Unit Perlengkapan menerbitkan BAST {$penerimaan->nomor_bast} paket {$nomorPaket} dan mengirimkan dokumentasi ke PPBJ (BPMN N48 - N50)."
        );

        // Memicu notifikasi pengiriman BAST & dokumentasi ke PPBJ (BPMN N50 -> N51)
        if ($isTerima) {
            $this->notificationService->notifyPenerimaanBarang($penerimaan);
        }

        return redirect()->route('penerimaan.index')->with(
            'success',
            "Berita Acara Serah Terima (BAST) nomor {$penerimaan->nomor_bast} dan foto dokumentasi berhasil diterbitkan & dikirim ke PPBJ (BPMN N48 - N50)."
        );
    }

    // =========================================================================
    // MODUL 7: INFORMASI DOKUMEN PEMBAYARAN (SPK/PESANAN/KONTRAK + KWITANSI + INVOICE) & SP2D
    // =========================================================================

    /**
     * Menampilkan antrean dan riwayat alur pembayaran bertingkat beserta Informasi Dokumen Pembayaran.
     */
    public function pembayaranIndex(): View
    {
        $user = Auth::user();

        // 1. Paket dengan BAST selesai yang siap diajukan berkas pembayaran oleh PPBJ (BPMN N51 -> N52)
        $bastSiapBayar = PenerimaanBarang::with(['paket.kontrakSpk', 'paket.kontrak', 'fotoDokumentasi'])
            ->where('status_fisik', 'terima')
            ->whereDoesntHave('paket.pembayaran')
            ->latest('tanggal_penerimaan')
            ->get();

        // 2. Daftar pengajuan pembayaran SPP lengkap dengan dokumen perikatan, kwitansi, dan invoice
        $pembayaranList = Pembayaran::with(['paket.kontrakSpk', 'paket.kontrak.vendor', 'paket.vendor', 'pengaju', 'memoBayar.user', 'transaksiPencairan'])
            ->latest('tanggal_pengajuan')
            ->paginate(15);

        // 3. Disposisi memo menunggu persetujuan (Wadir II Level 1 & PPK Level 2 - FR 16 & BPMN N55/N54)
        $memoMenungguReview = collect();
        if ($user) {
            if ($user->hasRole('admin')) {
                $memoMenungguReview = MemoBayar::with(['pembayaran.paket.kontrakSpk', 'pembayaran.memoBayar'])
                    ->where('status_memo', 'menunggu')
                    ->get();
            } else {
                $canWadir = $user->hasPermission('pembayaran.memo_wadir2');
                $canPpk = $user->hasPermission('pembayaran.memo_ppk');

                if ($canWadir || $canPpk) {
                    $memoMenungguReview = MemoBayar::with(['pembayaran.paket.kontrakSpk', 'pembayaran.memoBayar'])
                        ->where('status_memo', 'menunggu')
                        ->where(function ($query) use ($canWadir, $canPpk, $user) {
                            if ($canWadir) {
                                $query->orWhere(function ($sub) {
                                    $sub->where('role_approver', 'wadir_2')
                                        ->where('urutan_level', 1);
                                });
                            }
                            if ($canPpk) {
                                $query->orWhere(function ($sub) use ($user) {
                                    $sub->where('role_approver', 'ppk')
                                        ->where('urutan_level', 2)
                                        ->where(function ($idQ) use ($user) {
                                            $idQ->where('id_user', $user->id_user)->orWhereNull('id_user');
                                        })
                                        ->whereHas('pembayaran.memoBayar', function ($parentQ) {
                                            $parentQ->where('role_approver', 'wadir_2')->where('status_memo', 'disetujui');
                                        });
                                });
                            }
                        })
                        ->get();
                }
            }
        }

        // 4. Pembayaran yang memo-nya sudah disetujui lengkap dan siap diterbitkan SPP oleh PPBJ (FR 17)
        $siapTerbitkanSpp = Pembayaran::with(['paket.kontrakSpk', 'paket.kontrak.vendor', 'memoBayar'])
            ->where('status_pembayaran', 'reviu_memo')
            ->get()
            ->filter(function ($p) {
                return $p->memoBayar->count() >= 2 && $p->memoBayar->where('status_memo', '!=', 'disetujui')->count() === 0;
            });

        // 5. Pembayaran dengan SPP terbit yang siap dicairkan oleh Keuangan (BPMN N59 & FR 18)
        $siapCairKeuangan = Pembayaran::with([
            'paket.kontrakSpk.vendor',
            'paket.kontrak.vendor',
            'paket.vendor',
            'paket.penerimaanBarang.fotoDokumentasi',
            'memoBayar.user',
            'pengaju.unit',
        ])
            ->where('status_pembayaran', 'perintah_bayar')
            ->get();

        // 6. Daftar transaksi SP2D yang telah dicairkan oleh Keuangan
        $transaksiList = TransaksiPencairan::with(['pembayaran.paket', 'user'])->latest('tanggal_transfer')->paginate(15);

        // 7. Berkas Pembayaran yang Ditolak (BPMN N60/N61 -> N52: Titik Kembali PPBJ untuk Penyusunan Ulang Dokumen)
        $pembayaranDitolakList = Pembayaran::with(['paket.kontrakSpk', 'paket.kontrak.vendor', 'paket.vendor', 'memoBayar.user'])
            ->where('status_pembayaran', 'ditolak')
            ->latest('updated_at')
            ->get();

        return view('pembayaran.index', compact(
            'bastSiapBayar',
            'pembayaranList',
            'memoMenungguReview',
            'siapTerbitkanSpp',
            'siapCairKeuangan',
            'transaksiList',
            'pembayaranDitolakList',
            'user'
        ));
    }

    /**
     * Memproses pengajuan berkas pembayaran oleh PPBJ setelah BAST terbit,
     * melengkapi Informasi Dokumen Pembayaran (SPK/Surat Pesanan/Surat Kontrak + Kwitansi + Invoice).
     */
    public function storePengajuanBayarWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (empty($request->nomor_pembayaran)) {
            $request->merge(['nomor_pembayaran' => 'SPP/'.date('Ymd').'/'.strtoupper(Str::random(6))]);
        }
        if (empty($request->tanggal_pengajuan)) {
            $request->merge(['tanggal_pengajuan' => now()->toDateString()]);
        }

        $validated = $request->validate([
            'nomor_pembayaran' => ['required', 'string', 'max:100', 'unique:pembayaran,nomor_pembayaran'],
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'tahap_termin' => ['required', 'integer', 'min:1'],
            'nominal_pengajuan' => ['required', 'numeric', 'min:1'],
            'tanggal_pengajuan' => ['required', 'date'],
            'jatuh_tempo' => ['nullable', 'date'],
            'nomor_memo' => ['nullable', 'string', 'max:100'],
            'nomor_kwitansi' => ['nullable', 'string', 'max:100'],
            'file_kwitansi' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'nomor_invoice' => ['nullable', 'string', 'max:100'],
            'file_invoice' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_tagihan_vendor' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_dokumen_penunjang' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_memo' => ['nullable', DokumenUploadRule::pdfOnly()],
        ]);

        $paket = PaketPengadaan::findOrFail($validated['nomor_paket']);

        $tagihanPath = $request->hasFile('file_tagihan_vendor')
            ? $request->file('file_tagihan_vendor')->store('tagihan', 'public')
            : 'tagihan/invoice_dummy.pdf';

        $penunjangPath = $request->hasFile('file_dokumen_penunjang')
            ? $request->file('file_dokumen_penunjang')->store('penunjang', 'public')
            : 'penunjang/resume_kontrak.pdf';

        $memoPath = $request->hasFile('file_memo')
            ? $request->file('file_memo')->store('memo', 'public')
            : 'memo/memo_pembayaran.pdf';

        $kwitansiPath = $request->hasFile('file_kwitansi')
            ? $request->file('file_kwitansi')->store('kwitansi', 'public')
            : ($paket->file_kwitansi_belanja ?? 'kwitansi/kwitansi_pembayaran.pdf');

        $invoicePath = $request->hasFile('file_invoice')
            ? $request->file('file_invoice')->store('invoice', 'public')
            : $tagihanPath;

        $nomorKwitansi = $validated['nomor_kwitansi']
            ?? $paket->nomor_kwitansi_belanja
            ?? ('KWT/'.date('Ymd').'/'.rand(100, 999));

        $nomorInvoice = $validated['nomor_invoice']
            ?? ('INV/'.date('Ymd').'/'.rand(100, 999));

        $pembayaran = Pembayaran::create([
            'nomor_pembayaran' => $validated['nomor_pembayaran'],
            'nomor_paket' => $validated['nomor_paket'],
            'id_user_pengaju' => $user->id_user,
            'tahap_termin' => $validated['tahap_termin'],
            'nominal_pengajuan' => $validated['nominal_pengajuan'],
            'tanggal_pengajuan' => $validated['tanggal_pengajuan'],
            'jatuh_tempo' => $validated['jatuh_tempo'] ?? now()->addDays(30)->toDateString(),
            'status_pembayaran' => 'reviu_memo',
            'nomor_kwitansi' => $nomorKwitansi,
            'file_kwitansi' => $kwitansiPath,
            'nomor_invoice' => $nomorInvoice,
            'file_invoice' => $invoicePath,
            'file_tagihan_vendor' => $tagihanPath,
            'file_dokumen_penunjang' => $penunjangPath,
            'nomor_memo' => $validated['nomor_memo'] ?? 'MEMO/BHP/'.date('Ymd'),
            'file_memo' => $memoPath,
        ]);

        $paket->status_paket = 'pengajuan_pembayaran';
        $paket->save();

        // Bentuk memo bertingkat: Level 1 (Wadir II) & Level 2 (PPK) (BPMN Pembayaran)
        $wadir = User::where('role', 'wadir_2')
            ->orWhereHas('roleEntity.permissions', fn ($q) => $q->where('nama_permission', 'pembayaran.memo_wadir2'))
            ->first();
        $memoLevel1 = MemoBayar::create([
            'id_user' => $wadir?->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'wadir_2',
            'urutan_level' => 1,
            'status_memo' => 'menunggu',
        ]);

        $defaultPpk = User::where('role', 'ppk')
            ->orWhereHas('roleEntity.permissions', fn ($q) => $q->where('nama_permission', 'pembayaran.memo_ppk'))
            ->first();
        $ppkUserId = $paket->id_user_ppk ?? $paket->permohonan?->id_user_ppk_awal ?? $defaultPpk?->id_user ?? $user->id_user;
        MemoBayar::create([
            'id_user' => $ppkUserId,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'urutan_level' => 2,
            'status_memo' => 'menunggu',
        ]);

        AuditLog::catat(
            $user->id_user,
            'PENGAJUAN_BERKAS_PEMBAYARAN',
            'pembayaran',
            $pembayaran->nomor_pembayaran,
            null,
            'reviu_memo',
            "PPBJ mengajukan berkas pembayaran {$pembayaran->nomor_pembayaran} (Kwitansi: {$nomorKwitansi}, Invoice: {$nomorInvoice}) termin {$pembayaran->tahap_termin} Rp ".number_format($pembayaran->nominal_pengajuan)
        );

        // Memicu notifikasi pengajuan memo bayar ke Wadir II (Level 1)
        $this->notificationService->notifyPengajuanBayar($pembayaran, $memoLevel1);

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Berkas pembayaran {$pembayaran->nomor_pembayaran} lengkap dengan Kwitansi & Invoice berhasil diajukan ke Wakil Direktur II (Level 1) serta PPK (Level 2) untuk peninjauan memo bertingkat."
        );
    }

    /**
     * Memproses perbaikan berkas dokumen pembayaran oleh PPBJ setelah ditolak Wadir II / PPK (BPMN N52 -> N53 -> N56).
     */
    public function pembayaranUpdate(Request $request, string $nomorPembayaran): RedirectResponse
    {
        $user = Auth::user();
        $pembayaran = Pembayaran::with(['paket', 'memoBayar'])->where('nomor_pembayaran', $nomorPembayaran)->firstOrFail();

        $validated = $request->validate([
            'nomor_surat_pesanan' => ['nullable', 'string', 'max:100'],
            'nomor_kwitansi' => ['nullable', 'string', 'max:100'],
            'nomor_invoice' => ['nullable', 'string', 'max:100'],
            'file_dokumen_perikatan' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_kwitansi' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_invoice' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_memo' => ['nullable', DokumenUploadRule::pdfOnly()],
            'nomor_rekening_tujuan' => ['nullable', 'string', 'max:100'],
            'nama_bank_tujuan' => ['nullable', 'string', 'max:100'],
            'nama_penerima_tujuan' => ['nullable', 'string', 'max:150'],
            'catatan_perbaikan_ppbj' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('file_dokumen_perikatan')) {
            $pembayaran->file_dokumen_perikatan = $request->file('file_dokumen_perikatan')->store('dokumen_perikatan', 'public');
        }
        if ($request->hasFile('file_kwitansi')) {
            $pembayaran->file_kwitansi = $request->file('file_kwitansi')->store('kwitansi_pembayaran', 'public');
        }
        if ($request->hasFile('file_invoice')) {
            $pembayaran->file_invoice = $request->file('file_invoice')->store('invoice_pembayaran', 'public');
        }
        if ($request->hasFile('file_memo')) {
            $pembayaran->file_memo = $request->file('file_memo')->store('memo_pembayaran', 'public');
        }

        if ($request->filled('nomor_surat_pesanan')) {
            $pembayaran->nomor_surat_pesanan = $validated['nomor_surat_pesanan'];
        }
        if ($request->filled('nomor_kwitansi')) {
            $pembayaran->nomor_kwitansi = $validated['nomor_kwitansi'];
        }
        if ($request->filled('nomor_invoice')) {
            $pembayaran->nomor_invoice = $validated['nomor_invoice'];
        }
        if ($request->filled('nomor_rekening_tujuan')) {
            $pembayaran->nomor_rekening_tujuan = $validated['nomor_rekening_tujuan'];
        }
        if ($request->filled('nama_bank_tujuan')) {
            $pembayaran->nama_bank_tujuan = $validated['nama_bank_tujuan'];
        }
        if ($request->filled('nama_penerima_tujuan')) {
            $pembayaran->nama_penerima_tujuan = $validated['nama_penerima_tujuan'];
        }

        $pembayaran->status_pembayaran = 'reviu_memo';
        $pembayaran->catatan_reviu_akhir = 'Diperbaiki oleh PPBJ: '.($validated['catatan_perbaikan_ppbj'] ?? 'Dokumen pembayaran dan bukti perikatan telah disesuaikan.');
        $pembayaran->save();

        // Reset Memo Bayar Wadir 2 (Level 1) & PPK (Level 2) menjadi status 'menunggu' untuk penelaahan ulang
        foreach ($pembayaran->memoBayar as $memo) {
            $memo->status_memo = 'menunggu';
            $memo->tanggal_acc = null;
            $memo->catatan_memo = null;
            $memo->save();
        }

        AuditLog::catat(
            $user->id_user,
            'PERBAIKAN_DOKUMEN_PEMBAYARAN',
            'pembayaran',
            $pembayaran->nomor_pembayaran,
            'ditolak',
            'reviu_memo',
            "PPBJ mengajukan ulang berkas pembayaran {$pembayaran->nomor_pembayaran} ke Wadir II (Level 1) & PPK (Level 2) [BPMN N52/N56]"
        );

        $memoLevel1 = $pembayaran->memoBayar->where('urutan_level', 1)->first();
        if ($memoLevel1) {
            $this->notificationService->notifyPengajuanBayar($pembayaran, $memoLevel1);
        }

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Berkas pembayaran {$pembayaran->nomor_pembayaran} berhasil diperbaiki dan diteruskan kembali ke Wadir II & PPK (BPMN N56)."
        );
    }

    /**
     * Memproses persetujuan/penolakan memo pembayaran oleh Wadir 2 atau PPK (BPMN N55/N54).
     */
    public function approveMemoBayarWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($request->has('id_memo') && ! $request->has('id_memo_bayar')) {
            $request->merge(['id_memo_bayar' => $request->id_memo]);
        }
        if ($request->has('catatan') && ! $request->has('catatan_memo')) {
            $request->merge(['catatan_memo' => $request->catatan]);
        }

        $validated = $request->validate([
            'id_memo_bayar' => ['required', 'exists:memo_bayar,id'],
            'status_memo' => ['required', 'in:disetujui,ditolak'],
            'catatan_memo' => ['nullable', 'string'],
            'nomor_memo' => ['nullable', 'string', 'max:100'],
            'file_memo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ]);

        $memo = MemoBayar::findOrFail($validated['id_memo_bayar']);

        // Verifikasi wewenang hak akses peran via RBAC
        if ($memo->role_approver === 'wadir_2' && ! $user->hasRole('admin') && ! $user->hasPermission('pembayaran.memo_wadir2')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk menyetujui memo Wakil Direktur II.');
        }

        if ($memo->role_approver === 'ppk' && ! $user->hasRole('admin') && ! $user->hasPermission('pembayaran.memo_ppk')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk menyetujui memo Pejabat Pembuat Komitmen.');
        }

        if ($memo->role_approver === 'ppk' && $user->hasPermission('pembayaran.memo_ppk') && ! $user->hasRole('admin') && $memo->id_user && (int) $memo->id_user !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda hanya berhak menyetujui memo pada paket yang ditugaskan kepada Anda.');
        }

        // Aturan berjenjang: Level 2 (PPK) hanya bisa di-ACC jika Level 1 (Wadir 2) sudah disetujui
        if ($memo->urutan_level === 2 && $validated['status_memo'] === 'disetujui') {
            $level1Disetujui = MemoBayar::where('nomor_pembayaran', $memo->nomor_pembayaran)
                ->where('urutan_level', 1)
                ->where('status_memo', 'disetujui')
                ->exists();
            if (! $level1Disetujui) {
                return back()->withErrors(['status_memo' => 'Persetujuan Level 2 (PPK) hanya dapat disahkan setelah Level 1 (Wadir II) disetujui terlebih dahulu.']);
            }
        }

        $memo->status_memo = $validated['status_memo'];
        $memo->catatan_memo = $validated['catatan_memo'];
        if ($request->filled('nomor_memo')) {
            $memo->nomor_memo = $validated['nomor_memo'];
        } elseif ($validated['status_memo'] === 'disetujui' && empty($memo->nomor_memo)) {
            $memo->nomor_memo = 'MEMO-BAYAR-LVL'.$memo->urutan_level.'/'.date('Ymd').'/'.rand(100, 999);
        }
        if ($request->hasFile('file_memo')) {
            $memo->file_memo = $request->file('file_memo')->store('memo_bayar', 'public');
        }
        $memo->tanggal_acc = ($validated['status_memo'] === 'disetujui') ? now() : null;
        $memo->save();

        // Jika ditolak, kembalikan dokumen pembayaran ke PPBJ (BPMN N60/N61 -> Tidak -> N52)
        if ($validated['status_memo'] === 'ditolak') {
            $pembayaran = $memo->pembayaran;
            if ($pembayaran) {
                $pembayaran->status_pembayaran = 'ditolak';
                $pembayaran->catatan_reviu_akhir = ($memo->role_approver === 'wadir_2' ? 'Wadir II: ' : 'PPK: ').($validated['catatan_memo'] ?? 'Dokumen pembayaran ditolak pada tahap disposisi memo bayar.');
                $pembayaran->save();
            }
        }

        AuditLog::catat(
            $user->id_user,
            'DISPOSISI_MEMO_PEMBAYARAN',
            'memo_bayar',
            (string) $memo->id,
            null,
            $memo->status_memo,
            "Persetujuan memo pembayaran tingkat {$memo->urutan_level} ({$memo->role_approver}) ditetapkan menjadi {$memo->status_memo}".($memo->nomor_memo ? " [{$memo->nomor_memo}]" : '')
        );

        // Memicu notifikasi alur disposisi memo bayar (ke PPK, PPBJ, atau Pengaju)
        $this->notificationService->notifyApproveMemoBayar($memo);

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Memo pembayaran tingkat {$memo->urutan_level} (".($memo->role_approver === 'wadir_2' ? 'Wadir II' : 'PPK').') berhasil di-'.strtoupper($memo->status_memo).'.'
        );
    }

    /**
     * Memproses penerbitan Surat Perintah Pembayaran (SPP) oleh PPBJ setelah seluruh memo disetujui (FR 17).
     */
    public function terbitkanSppWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_pembayaran' => ['required', 'exists:pembayaran,nomor_pembayaran'],
            'catatan_reviu_akhir' => ['nullable', 'string'],
            'file_spp' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_legalitas_vendor' => ['nullable', DokumenUploadRule::pdfOrImage()],
        ]);

        $pembayaran = Pembayaran::where('nomor_pembayaran', $validated['nomor_pembayaran'])->firstOrFail();

        // Validasi seluruh memo harus disetujui lengkap
        $pendingCount = $pembayaran->memoBayar()->where('status_memo', '!=', 'disetujui')->count();
        if ($pendingCount > 0) {
            return back()->withErrors(['nomor_pembayaran' => 'Perintah pembayaran belum dapat diterbitkan karena memo bayar belum disetujui lengkap oleh PPK dan Wadir 2.']);
        }

        // Simpan lampiran berkas SPP jika diunggah
        if ($request->hasFile('file_spp')) {
            $pembayaran->file_dokumen_penunjang = $request->file('file_spp')->store('spp', 'public');
        }

        // Simpan kelengkapan legalitas vendor sesuai alur BPMN PPBJ
        if ($request->hasFile('file_legalitas_vendor')) {
            $legalitasPath = $request->file('file_legalitas_vendor')->store('vendor_legalitas', 'public');
            $vendor = $pembayaran->paket?->vendor ?? $pembayaran->paket?->kontrakSpk?->vendor;
            if ($vendor) {
                $vendor->file_dokumen_legalitas = $legalitasPath;
                $vendor->save();
            }
        }

        $pembayaran->id_user_reviu = $user->id_user;
        $pembayaran->tanggal_perintah_bayar = now();
        $pembayaran->status_reviu_akhir = 'disetujui';
        $pembayaran->catatan_reviu_akhir = $validated['catatan_reviu_akhir'] ?? 'Dokumen pembayaran lengkap dan sah';
        $pembayaran->status_pembayaran = 'perintah_bayar';
        $pembayaran->save();

        $paket = PaketPengadaan::find($pembayaran->nomor_paket);
        if ($paket) {
            $paket->status_paket = 'perintah_bayar';
            $paket->save();
        }

        AuditLog::catat(
            $user->id_user,
            'TERBITKAN_PERINTAH_BAYAR_SPP',
            'pembayaran',
            $pembayaran->nomor_pembayaran,
            'reviu_memo',
            'perintah_bayar',
            "PPBJ menerbitkan Surat Perintah Pembayaran (SPP) {$pembayaran->nomor_pembayaran} ke Bagian Keuangan"
        );

        // Memicu notifikasi penerbitan SPP ke Bagian Keuangan
        $this->notificationService->notifyTerbitkanSpp($pembayaran);

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Surat Perintah Pembayaran (SPP) {$pembayaran->nomor_pembayaran} berhasil diterbitkan ke Bagian Keuangan."
        );
    }

    /**
     * Memproses eksekusi pencairan dana SP2D dan upload slip bukti transfer bank oleh Keuangan (FR 18 & NFR 8).
     */
    public function storePembayaran(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (empty($request->nomor_transaksi)) {
            $request->merge(['nomor_transaksi' => 'SP2D/'.date('Ymd').'/'.strtoupper(Str::random(6))]);
        }

        $validated = $request->validate([
            'nomor_pembayaran' => ['required', 'exists:pembayaran,nomor_pembayaran'],
            'nomor_transaksi' => ['required', 'string', 'max:100', 'unique:transaksi_pencairan,nomor_transaksi_bank'],
            'tanggal_cair' => ['required', 'date'],
            'nominal_cair' => ['required', 'numeric', 'min:1'],
            'bank_tujuan' => ['required', 'string'],
            'file_bukti_transfer' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'keterangan_cair' => ['nullable', 'string'],
        ]);

        $pembayaran = Pembayaran::where('nomor_pembayaran', $validated['nomor_pembayaran'])->firstOrFail();

        // NFR 8: Proteksi Anti-Duplicate Disbursement
        $alreadyDisbursed = TransaksiPencairan::where('nomor_pembayaran', $pembayaran->nomor_pembayaran)->exists();
        if ($alreadyDisbursed || $pembayaran->status_pembayaran === 'lunas') {
            return back()->withErrors(['nomor_pembayaran' => 'Tagihan pembayaran ini sudah dicairkan sebelumnya dan berstatus Lunas (NFR 8).']);
        }

        $filePath = $request->hasFile('file_bukti_transfer')
            ? $request->file('file_bukti_transfer')->store('transfer', 'public')
            : 'transfer/slip_'.time().'.pdf';

        $transaksi = TransaksiPencairan::create([
            'nomor_transaksi_bank' => $validated['nomor_transaksi'],
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'id_user' => $user->id_user,
            'tanggal_transfer' => $validated['tanggal_cair'],
            'nominal_transfer' => $validated['nominal_cair'],
            'file_bukti_transfer' => $filePath,
            'catatan_keuangan' => ($validated['keterangan_cair'] ?? '').' [Bank: '.$validated['bank_tujuan'].']',
        ]);

        AuditLog::catat(
            $user->id_user,
            'PENCAIRAN_SP2D_SELESAI',
            'transaksi_pencairan',
            $transaksi->nomor_transaksi_bank,
            'perintah_bayar',
            'lunas',
            "Keuangan mencairkan dana SP2D {$transaksi->nomor_transaksi_bank} sebesar Rp ".number_format($transaksi->nominal_transfer).' dan seluruh siklus pengadaan selesai.'
        );

        // Memicu notifikasi penyelesaian pencairan SP2D ke Pengusul dan PPBJ
        $this->notificationService->notifyPencairanSp2d($transaksi);

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Pencairan dana SP2D nomor {$transaksi->nomor_transaksi_bank} berhasil diselesaikan. Seluruh siklus pengadaan kini berstatus SELESAI 100%!"
        );
    }

    // =========================================================================
    // MODUL 8: LAPORAN REKAPITULASI TOTAL ANGGARAN & MONITORING (FR 20 & NFR 10)
    // =========================================================================

    // =========================================================================
    // MODUL 8: RIWAYAT DAN LAPORAN (FIGMA 515:863 & FIGMA 526:3083)
    // =========================================================================

    /**
     * Menampilkan antarmuka terpadu Riwayat dan Laporan (Tab Riwayat Aktivitas & Tab Laporan).
     * Menggantikan modul monitoring terpisah dan mendukung isolasi peran (Role-Based Scoping).
     */
    public function laporanIndex(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        $userRole = $user ? $user->role : 'user';

        if ($user && ! in_array($userRole, ['admin', 'ppbj', 'ppk', 'pp', 'wadir_2', 'perencanaan', 'staff_bidang_2', 'perlengkapan', 'keuangan', 'user'], true)) {
            if ($user->hasPermission('audit_log.view') || $user->hasPermission('paket.view_all')) {
                $userRole = 'ppbj';
            } elseif ($user->hasPermission('pembayaran.pencairan_sp2d')) {
                $userRole = 'keuangan';
            } elseif ($user->hasPermission('penerimaan.view')) {
                $userRole = 'perlengkapan';
            } elseif ($user->hasPermission('anggaran.mak_view')) {
                $userRole = 'perencanaan';
            } elseif ($user->hasPermission('verifikasi.wadir_2_view')) {
                $userRole = 'wadir_2';
            } elseif ($user->hasPermission('verifikasi.bidang_2_view')) {
                $userRole = 'staff_bidang_2';
            } elseif ($user->hasPermission('reviu.ppk')) {
                $userRole = 'ppk';
            } elseif ($user->hasPermission('reviu.pp')) {
                $userRole = 'pp';
            } else {
                $userRole = 'user';
            }
        }

        $tahun = (int) $request->input('tahun', date('Y'));
        $bulan = $request->input('bulan');
        $kodeUnit = $request->input('unit');
        $activeTab = $request->input('tab', 'riwayat');

        // =====================================================================
        // TAB 1: RIWAYAT AKTIVITAS (Figma 515:863)
        // =====================================================================
        $auditQuery = AuditLog::with('user')->latest('waktu_log');

        // Isolasi jejak audit berbasis peran pengguna (Role-Based Scoping)
        switch ($userRole) {
            case 'user':
                $userPermohonanNomor = PermohonanPengadaan::where('id_user', $user->id_user)
                    ->pluck('nomor_surat');
                $userPaketNomor = PaketPengadaan::whereIn('nomor_surat', $userPermohonanNomor)->pluck('nomor_paket');

                $auditQuery->where(function ($q) use ($user, $userPermohonanNomor, $userPaketNomor) {
                    $q->where('id_user', $user->id_user)
                        ->orWhere(function ($sub) use ($userPermohonanNomor) {
                            $sub->where('nama_entitas', 'permohonan_pengadaan')
                                ->whereIn('id_entitas', $userPermohonanNomor);
                        })
                        ->orWhere(function ($sub) use ($userPaketNomor) {
                            $sub->where('nama_entitas', 'paket_pengadaan')
                                ->whereIn('id_entitas', $userPaketNomor);
                        });
                });
                break;

            case 'pp':
                $ppPaketNomor = PaketPengadaan::where(function ($q) use ($user) {
                    $q->where('jalur_routing', PaketPengadaan::ROUTING_PP)
                        ->orWhere('id_user_pp', $user->id_user);
                })->pluck('nomor_paket');
                $ppPermohonanNomor = PaketPengadaan::whereIn('nomor_paket', $ppPaketNomor)->pluck('nomor_surat');

                $auditQuery->where(function ($q) use ($user, $ppPaketNomor, $ppPermohonanNomor) {
                    $q->where('id_user', $user->id_user)
                        ->orWhere('jenis_aksi', 'like', '%_PP%')
                        ->orWhere('jenis_aksi', 'like', '%NEGOSIASI%')
                        ->orWhere(function ($sub) use ($ppPaketNomor) {
                            $sub->where('nama_entitas', 'paket_pengadaan')
                                ->whereIn('id_entitas', $ppPaketNomor);
                        })
                        ->orWhere(function ($sub) use ($ppPermohonanNomor) {
                            $sub->where('nama_entitas', 'permohonan_pengadaan')
                                ->whereIn('id_entitas', $ppPermohonanNomor);
                        });
                });
                break;

            case 'ppk':
                $ppkPaketNomor = PaketPengadaan::where('id_user_ppk', $user->id_user)->pluck('nomor_paket');
                $ppkPermohonanNomor = PermohonanPengadaan::where('id_user_ppk_awal', $user->id_user)->pluck('nomor_surat');

                $auditQuery->where(function ($q) use ($user, $ppkPaketNomor, $ppkPermohonanNomor) {
                    $q->where('id_user', $user->id_user)
                        ->orWhere('jenis_aksi', 'like', '%PPK%')
                        ->orWhere('jenis_aksi', 'like', '%APPROVAL_LELANG%')
                        ->orWhere(function ($sub) use ($ppkPaketNomor) {
                            $sub->where('nama_entitas', 'paket_pengadaan')
                                ->whereIn('id_entitas', $ppkPaketNomor);
                        })
                        ->orWhere(function ($sub) use ($ppkPermohonanNomor) {
                            $sub->where('nama_entitas', 'permohonan_pengadaan')
                                ->whereIn('id_entitas', $ppkPermohonanNomor);
                        });
                });
                break;

            case 'perlengkapan':
                $auditQuery->where(function ($q) use ($user) {
                    $q->where('id_user', $user->id_user)
                        ->orWhereIn('nama_entitas', ['penerimaan_barang', 'kontrak_spk'])
                        ->orWhere('jenis_aksi', 'like', '%BAST%')
                        ->orWhere('jenis_aksi', 'like', '%PENERIMAAN%')
                        ->orWhere('jenis_aksi', 'like', '%BARANG%');
                });
                break;

            case 'keuangan':
                $auditQuery->where(function ($q) use ($user) {
                    $q->where('id_user', $user->id_user)
                        ->orWhereIn('nama_entitas', ['pembayaran', 'transaksi_pencairan'])
                        ->orWhere('jenis_aksi', 'like', '%BAYAR%')
                        ->orWhere('jenis_aksi', 'like', '%CAIR%')
                        ->orWhere('jenis_aksi', 'like', '%SP2D%')
                        ->orWhere('jenis_aksi', 'like', '%MEMO_BAYAR%');
                });
                break;

            case 'staff_bidang_2':
                $auditQuery->where(function ($q) use ($user) {
                    $q->where('id_user', $user->id_user)
                        ->orWhereIn('nama_entitas', ['permohonan_pengadaan', 'verifikasi_paralel'])
                        ->orWhere('jenis_aksi', 'like', '%BIDANG_2%')
                        ->orWhere('jenis_aksi', 'like', '%TELAAH%')
                        ->orWhere('jenis_aksi', 'like', '%AJUKAN%');
                });
                break;

            case 'perencanaan':
                $auditQuery->where(function ($q) use ($user) {
                    $q->where('id_user', $user->id_user)
                        ->orWhereIn('nama_entitas', ['anggaran_mak', 'alokasi_mak'])
                        ->orWhere('jenis_aksi', 'like', '%MAK%')
                        ->orWhere('jenis_aksi', 'like', '%ANGGARAN%');
                });
                break;

            case 'ppbj':
                $auditQuery->where(function ($q) use ($user) {
                    $q->where('id_user', $user->id_user)
                        ->orWhereIn('nama_entitas', ['paket_pengadaan', 'vendor', 'kontrak_spk', 'reminder_pengadaan'])
                        ->orWhere('jenis_aksi', 'like', '%PPBJ%')
                        ->orWhere('jenis_aksi', 'like', '%PAKET%')
                        ->orWhere('jenis_aksi', 'like', '%ROUTING%')
                        ->orWhere('jenis_aksi', 'like', '%_PP%');
                });
                break;

            case 'wadir_2':
                $auditQuery->where(function ($q) use ($user) {
                    $q->where('id_user', $user->id_user)
                        ->orWhere('jenis_aksi', 'like', '%WADIR%')
                        ->orWhere('jenis_aksi', 'like', '%TELAAH%')
                        ->orWhere('jenis_aksi', 'like', '%MEMO_BAYAR%')
                        ->orWhereIn('nama_entitas', ['permohonan_pengadaan', 'verifikasi_paralel']);
                });
                break;

            case 'admin':
                $auditQuery->where(function ($q) use ($user) {
                    $q->where('id_user', $user->id_user)
                        ->orWhereIn('nama_entitas', ['users', 'roles', 'permissions'])
                        ->orWhere('jenis_aksi', 'like', '%USER%')
                        ->orWhere('jenis_aksi', 'like', '%ROLE%')
                        ->orWhere('jenis_aksi', 'like', '%REGISTRASI%');
                });
                break;

            default:
                $auditQuery->where('id_user', $user->id_user);
                break;
        }

        $auditLogsRaw = $auditQuery->take(60)->get();
        $permohonanLookup = PermohonanPengadaan::with('unit')->get()->keyBy('nomor_surat');
        $paketLookup = PaketPengadaan::with('permohonan.unit')->get()->keyBy('nomor_paket');

        $riwayatList = [];
        foreach ($auditLogsRaw as $log) {
            $nomorSurat = null;
            $namaPengadaan = null;
            $kodeUnitLog = '';
            $link = route('permohonan.index');

            if ($log->nama_entitas === 'permohonan_pengadaan') {
                $nomorSurat = $log->id_entitas;
                $permohonan = $permohonanLookup[$nomorSurat] ?? null;
                $namaPengadaan = $permohonan?->judul_pengadaan;
                $kodeUnitLog = $permohonan?->kode_unit ?? '';
                $link = route('permohonan.index', ['selected' => $nomorSurat]);
            } elseif ($log->nama_entitas === 'paket_pengadaan') {
                $paket = $paketLookup[$log->id_entitas] ?? null;
                $nomorSurat = $paket?->nomor_surat ?? $log->id_entitas;
                $namaPengadaan = $paket?->nama_paket ?? $paket?->permohonan?->judul_pengadaan;
                $kodeUnitLog = $paket?->permohonan?->kode_unit ?? '';
                $link = route('paket.index', ['selected' => $log->id_entitas]);
            } elseif ($log->nama_entitas === 'penerimaan_barang') {
                $nomorSurat = $log->id_entitas;
                $namaPengadaan = $log->alasan ?: 'Pemeriksaan Fisik Barang & BAST';
                $link = route('penerimaan.index');
            } elseif ($log->nama_entitas === 'pembayaran' || $log->nama_entitas === 'transaksi_pencairan') {
                $nomorSurat = $log->id_entitas;
                $namaPengadaan = $log->alasan ?: 'Verifikasi Tagihan / SP2D Pencairan';
                $link = route('pembayaran.index');
            } elseif ($log->nama_entitas === 'anggaran_mak' || $log->nama_entitas === 'alokasi_mak') {
                $nomorSurat = $log->id_entitas;
                $namaPengadaan = $log->alasan ?: 'Alokasi Pagu MAK DIPA';
                $link = route('verifikasi.perencanaan');
            } elseif ($log->nama_entitas === 'users' || $log->nama_entitas === 'roles') {
                $nomorSurat = $log->id_entitas;
                $namaPengadaan = $log->alasan ?: 'Manajemen Akun / Hak Akses';
                $link = route('admin.persetujuan-registrasi.index');
            } else {
                $nomorSurat = $log->id_entitas;
                $namaPengadaan = $log->alasan ?: ($log->jenis_aksi.' pada '.$log->nama_entitas);
            }

            if (! $namaPengadaan) {
                $namaPengadaan = $log->alasan ?: ($log->jenis_aksi.' pada '.$log->nama_entitas);
            }

            $permohonan = $nomorSurat ? ($permohonanLookup[$nomorSurat] ?? null) : null;
            $revisiStr = 'Revisi ke-0';
            if ($permohonan && ! empty($permohonan->id_surat_asal_revisi)) {
                $revisiStr = 'Revisi ke-1';
            }

            $statusText = 'Dalam Proses';
            $statusColor = 'blue';
            if (stripos($log->jenis_aksi, 'ACC') !== false || stripos($log->alasan ?? '', 'disetujui') !== false) {
                $statusText = 'Disetujui';
                $statusColor = 'emerald';
            } elseif (stripos($log->jenis_aksi, 'TOLAK') !== false || stripos($log->alasan ?? '', 'ditolak') !== false) {
                $statusText = 'Ditolak';
                $statusColor = 'rose';
            } elseif ($permohonan && in_array($permohonan->status_permohonan, ['disetujui', 'selesai'])) {
                $statusText = 'Disetujui';
                $statusColor = 'emerald';
            }

            $waktuCarbon = $log->waktu_log ?? now();

            $riwayatList[] = [
                'id' => 'log_'.$log->id_log,
                'waktu' => $waktuCarbon->translatedFormat('d M Y, H:i'),
                'waktu_raw' => $waktuCarbon->toIso8601String(),
                'tahun' => (int) $waktuCarbon->format('Y'),
                'bulan' => (int) $waktuCarbon->format('m'),
                'nama_pengadaan' => $namaPengadaan,
                'nomor_surat' => $nomorSurat ?? '',
                'kode_unit' => $kodeUnitLog ?: ($permohonan?->kode_unit ?? ''),
                'revisi' => $revisiStr,
                'aktivitas' => $log->alasan ?: ($log->jenis_aksi.' pada '.$log->nama_entitas),
                'jenis_aktivitas' => $this->resolveJenisAktivitas($log->jenis_aksi),
                'status' => $statusText,
                'status_color' => $statusColor,
                'link' => $link,
            ];
        }

        // Sintesiskan entitas aktif jika baris audit log masih kosong agar data selalu terisi sesuai role
        if ($userRole === 'user') {
            $fallbackPermohonans = PermohonanPengadaan::with(['unit', 'user'])
                ->where('id_user', $user->id_user)
                ->latest('tanggal_pengajuan')
                ->take(10)
                ->get();
            foreach ($fallbackPermohonans as $p) {
                if (! collect($riwayatList)->contains('nomor_surat', $p->nomor_surat)) {
                    $pWaktu = $p->tanggal_pengajuan ? Carbon::parse($p->tanggal_pengajuan) : now();
                    $st = ($p->status_permohonan === 'ditolak') ? 'Ditolak' : (($p->status_permohonan === 'disetujui' || $p->status_permohonan === 'selesai') ? 'Disetujui' : 'Dalam Proses');
                    $col = ($st === 'Disetujui') ? 'emerald' : (($st === 'Ditolak') ? 'rose' : 'blue');
                    $riwayatList[] = [
                        'id' => 'permohonan_'.md5($p->nomor_surat),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $p->judul_pengadaan,
                        'nomor_surat' => $p->nomor_surat,
                        'kode_unit' => $p->kode_unit,
                        'revisi' => ! empty($p->id_surat_asal_revisi) ? 'Revisi ke-1' : 'Revisi ke-0',
                        'aktivitas' => 'Pemeriksaan pengajuan oleh bagian '.($p->status_permohonan === 'ditolak' ? 'Perencanaan (Ditolak)' : 'Perencanaan & Wadir 2'),
                        'jenis_aktivitas' => 'Verifikasi',
                        'status' => $st,
                        'status_color' => $col,
                        'link' => route('permohonan.index', ['selected' => $p->nomor_surat]),
                    ];
                }
            }
        } elseif ($userRole === 'pp') {
            $fallbackPakets = PaketPengadaan::with(['permohonan.unit', 'vendor'])
                ->where(fn ($q) => $q->where('jalur_routing', PaketPengadaan::ROUTING_PP)->orWhere('id_user_pp', $user->id_user))
                ->latest('created_at')
                ->take(10)
                ->get();
            foreach ($fallbackPakets as $pkt) {
                if (! collect($riwayatList)->contains('nomor_surat', $pkt->nomor_paket)) {
                    $pWaktu = $pkt->created_at ?? now();
                    $riwayatList[] = [
                        'id' => 'paket_'.md5($pkt->nomor_paket),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $pkt->nama_paket,
                        'nomor_surat' => $pkt->nomor_paket,
                        'kode_unit' => $pkt->permohonan?->kode_unit ?? '',
                        'revisi' => 'Revisi ke-0',
                        'aktivitas' => 'Penanganan paket oleh Pejabat Pengadaan (Status: '.ucwords(str_replace('_', ' ', $pkt->status_paket)).')',
                        'jenis_aktivitas' => 'Pengadaan Langsung (PP)',
                        'status' => in_array($pkt->status_paket, ['spk_terbit', 'selesai']) ? 'Disetujui' : 'Dalam Proses',
                        'status_color' => in_array($pkt->status_paket, ['spk_terbit', 'selesai']) ? 'emerald' : 'blue',
                        'link' => route('paket.index', ['selected' => $pkt->nomor_paket]),
                    ];
                }
            }
        } elseif ($userRole === 'ppk') {
            $fallbackPakets = PaketPengadaan::with(['permohonan.unit', 'vendor'])
                ->where('id_user_ppk', $user->id_user)
                ->latest('created_at')
                ->take(10)
                ->get();
            foreach ($fallbackPakets as $pkt) {
                if (! collect($riwayatList)->contains('nomor_surat', $pkt->nomor_paket)) {
                    $pWaktu = $pkt->created_at ?? now();
                    $riwayatList[] = [
                        'id' => 'paket_ppk_'.md5($pkt->nomor_paket),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $pkt->nama_paket,
                        'nomor_surat' => $pkt->nomor_paket,
                        'kode_unit' => $pkt->permohonan?->kode_unit ?? '',
                        'revisi' => 'Revisi ke-0',
                        'aktivitas' => 'Komitmen pengadaan PPK (Status: '.ucwords(str_replace('_', ' ', $pkt->status_paket)).')',
                        'jenis_aktivitas' => 'Kontrak',
                        'status' => in_array($pkt->status_paket, ['spk_terbit', 'selesai']) ? 'Disetujui' : 'Dalam Proses',
                        'status_color' => in_array($pkt->status_paket, ['spk_terbit', 'selesai']) ? 'emerald' : 'blue',
                        'link' => route('paket.index', ['selected' => $pkt->nomor_paket]),
                    ];
                }
            }
        } elseif ($userRole === 'perlengkapan') {
            $fallbackPenerimaan = PenerimaanBarang::with(['paket.vendor'])
                ->latest('tanggal_penerimaan')
                ->take(10)
                ->get();
            foreach ($fallbackPenerimaan as $pb) {
                $suratKey = $pb->nomor_bast ?: 'BAST-'.$pb->id_penerimaan;
                if (! collect($riwayatList)->contains('nomor_surat', $suratKey)) {
                    $pWaktu = $pb->tanggal_penerimaan ? Carbon::parse($pb->tanggal_penerimaan) : now();
                    $riwayatList[] = [
                        'id' => 'penerimaan_'.md5($suratKey),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $pb->paket?->nama_paket ?? 'Penerimaan Hasil Pengadaan',
                        'nomor_surat' => $suratKey,
                        'kode_unit' => 'LKP',
                        'revisi' => 'Revisi ke-0',
                        'aktivitas' => 'Penerbitan BAST & serah terima barang',
                        'jenis_aktivitas' => 'Penerimaan',
                        'status' => 'Disetujui',
                        'status_color' => 'emerald',
                        'link' => route('penerimaan.index'),
                    ];
                }
            }
        } elseif ($userRole === 'keuangan') {
            $fallbackPembayaran = Pembayaran::with(['paket.vendor'])
                ->latest('tanggal_pengajuan')
                ->take(10)
                ->get();
            foreach ($fallbackPembayaran as $byr) {
                if (! collect($riwayatList)->contains('nomor_surat', $byr->nomor_pembayaran)) {
                    $pWaktu = $byr->tanggal_pengajuan ? Carbon::parse($byr->tanggal_pengajuan) : now();
                    $riwayatList[] = [
                        'id' => 'bayar_'.md5($byr->nomor_pembayaran),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $byr->paket?->nama_paket ?? ('Tagihan '.($byr->paket?->vendor?->nama_vendor ?? 'Vendor')),
                        'nomor_surat' => $byr->nomor_pembayaran,
                        'kode_unit' => 'KEU',
                        'revisi' => 'Revisi ke-0',
                        'aktivitas' => 'Verifikasi tagihan & proses SP2D',
                        'jenis_aktivitas' => 'Pembayaran',
                        'status' => in_array($byr->status_pembayaran, ['lunas', 'selesai', 'pencairan_selesai']) ? 'Disetujui' : 'Dalam Proses',
                        'status_color' => in_array($byr->status_pembayaran, ['lunas', 'selesai', 'pencairan_selesai']) ? 'emerald' : 'blue',
                        'link' => route('pembayaran.index'),
                    ];
                }
            }
        }

        // =====================================================================
        // TAB 2: LAPORAN SESUAI PERAN (Figma 526:3083)
        // =====================================================================
        $units = Unit::all();
        $laporanCards = [];
        $laporanTable = [];
        $ringkasanTitle = 'Ringkasan Realisasi & Pengadaan';
        $tableTitle = 'Distribusi Anggaran per Jurusan dan Unit Kerja';
        $tableColumns = [];

        // 1. Role: USER (Usulan Pengadaan Pribadi Pemohon)
        if ($userRole === 'user') {
            $unitNama = $user->unit->nama_unit ?? $user->kode_unit ?? 'Unit Kerja';
            $ringkasanTitle = 'Ringkasan Pengadaan Unit Kerja ('.$user->nama.')';
            $tableTitle = 'Daftar Rekapitulasi Usulan Pengadaan Unit ('.$unitNama.')';

            $userPermohonan = PermohonanPengadaan::where('id_user', $user->id_user)
                ->when($tahun, fn ($q) => $q->whereYear('tanggal_pengajuan', $tahun))
                ->when($bulan, fn ($q) => $q->whereMonth('tanggal_pengajuan', $bulan))
                ->latest('tanggal_pengajuan')
                ->get();

            $totalUsulan = (float) $userPermohonan->sum('estimasi_total_pagu');
            $disetujuiCount = $userPermohonan->whereIn('status_permohonan', ['disetujui', 'selesai'])->count();
            $prosesCount = $userPermohonan->whereNotIn('status_permohonan', ['disetujui', 'selesai', 'ditolak', 'batal'])->count();

            $userPermohonanNomor = $userPermohonan->pluck('nomor_surat');
            $userPaketNomor = PaketPengadaan::whereIn('nomor_surat', $userPermohonanNomor)->pluck('nomor_paket');
            $totalKontrak = (float) KontrakSPK::whereIn('nomor_paket', $userPaketNomor)->sum('nilai_kontrak');
            $userPembayaranNomor = Pembayaran::whereIn('nomor_paket', $userPaketNomor)->pluck('nomor_pembayaran');
            $totalRealisasi = (float) TransaksiPencairan::whereIn('nomor_pembayaran', $userPembayaranNomor)->sum('nominal_transfer');

            $serapanPct = $totalUsulan > 0 ? round(($totalRealisasi / $totalUsulan) * 100, 1) : 0;

            $laporanCards = [
                ['label' => 'Total Estimasi Usulan Unit', 'val' => 'Rp '.number_format($totalUsulan, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Total pagu yang Anda ajukan'],
                ['label' => 'Total Realisasi Pencairan SP2D', 'val' => 'Rp '.number_format($totalRealisasi, 0, ',', '.'), 'icon' => 'check-circle', 'sub' => 'Dana belanja usulan telah cair'],
                ['label' => 'Total Nilai Kontrak / SPK', 'val' => 'Rp '.number_format($totalKontrak, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Perikatan resmi aktif'],
                ['label' => 'Usulan Telah Disetujui', 'val' => $disetujuiCount.' Berkas', 'icon' => 'check-badge', 'sub' => 'Disetujui Wadir 2 & PPK'],
                ['label' => 'Usulan Sedang Diproses', 'val' => $prosesCount.' Berkas', 'icon' => 'clock', 'sub' => 'Tahap verifikasi & pengadaan'],
                ['label' => 'Persentase Serapan Unit', 'val' => $serapanPct.'%', 'icon' => 'chart-pie', 'sub' => 'Rasio realisasi belanja usulan'],
            ];

            $tableColumns = ['No.', 'Nomor Surat', 'Nama Pengadaan', 'Tanggal Pengajuan', 'Estimasi Pagu', 'Status Pengadaan'];
            foreach ($userPermohonan as $idx => $item) {
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $item->nomor_surat,
                    'col2' => $item->judul_pengadaan,
                    'col3' => $item->tanggal_pengajuan ? Carbon::parse($item->tanggal_pengajuan)->translatedFormat('d M Y') : '-',
                    'col4' => 'Rp '.number_format($item->estimasi_total_pagu, 0, ',', '.'),
                    'col5' => ucfirst(str_replace('_', ' ', $item->status_permohonan)),
                    'link' => route('permohonan.index', ['selected' => $item->nomor_surat]),
                    'nomor_surat' => $item->nomor_surat,
                    'judul' => $item->judul_pengadaan,
                ];
            }
        }
        // 2. Role: PP (Penugasan Pejabat Pengadaan 50jt - 200jt)
        elseif ($userRole === 'pp') {
            $ringkasanTitle = 'Ringkasan Kinerja Pejabat Pengadaan (PP: '.$user->nama.')';
            $tableTitle = 'Rekapitulasi Paket Pengadaan Langsung (Penugasan PP)';

            $ppPaketList = PaketPengadaan::with(['permohonan.unit', 'vendor', 'kontrakSpk'])
                ->where(function ($q) use ($user) {
                    $q->where('jalur_routing', PaketPengadaan::ROUTING_PP)
                        ->orWhere('id_user_pp', $user->id_user);
                })
                ->when($tahun, fn ($q) => $q->whereYear('created_at', $tahun))
                ->when($bulan, fn ($q) => $q->whereMonth('created_at', $bulan))
                ->latest('created_at')
                ->get();

            $totalPaketPP = $ppPaketList->count();
            $ppPaketNomorList = $ppPaketList->pluck('nomor_paket');
            $reviuCount = $ppPaketList->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])->count();
            $menungguPpkCount = $ppPaketList->where('status_paket', 'menunggu_approval_ppk')->count();
            $siapBelanjaCount = $ppPaketList->whereIn('status_paket', ['disetujui_ppk_siap_belanja_pp', 'reviu_dokumen_disetujui', 'spk_terbit', 'selesai'])->count();
            $totalHps = (float) $ppPaketList->sum('nilai_hps');
            $totalKontrakPP = (float) KontrakSPK::whereIn('nomor_paket', $ppPaketNomorList)->sum('nilai_kontrak');

            $laporanCards = [
                ['label' => 'Total Paket Penugasan PP', 'val' => $totalPaketPP.' Paket', 'icon' => 'briefcase', 'sub' => 'Paket jalur 50jt - 200jt'],
                ['label' => 'Dalam Reviu & Negosiasi', 'val' => $reviuCount.' Paket', 'icon' => 'clock', 'sub' => 'Tahap verifikasi dokumen & harga'],
                ['label' => 'Menunggu Approval PPK', 'val' => $menungguPpkCount.' Paket', 'icon' => 'shield-check', 'sub' => 'Menunggu persetujuan PPK'],
                ['label' => 'Siap Belanja / SPK Terbit', 'val' => $siapBelanjaCount.' Paket', 'icon' => 'check-badge', 'sub' => 'Telah disetujui & eksekusi'],
                ['label' => 'Total Nilai HPS Pengadaan PP', 'val' => 'Rp '.number_format($totalHps, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Total perkiraan sendiri PP'],
                ['label' => 'Total Realisasi Kontrak / SPK', 'val' => 'Rp '.number_format($totalKontrakPP, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Nilai perikatan resmi PP'],
            ];

            $tableColumns = ['No.', 'Nomor Paket', 'Nama Paket Pengadaan', 'Metode Pengadaan', 'Nilai HPS', 'Status Paket'];
            foreach ($ppPaketList as $idx => $p) {
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $p->nomor_paket,
                    'col2' => $p->nama_paket,
                    'col3' => ucwords(str_replace('_', ' ', $p->metode_pengadaan ?? 'Pengadaan Langsung')),
                    'col4' => 'Rp '.number_format($p->nilai_hps, 0, ',', '.'),
                    'col5' => ucwords(str_replace('_', ' ', $p->status_paket)),
                    'link' => route('paket.index', ['selected' => $p->nomor_paket]),
                    'nomor_surat' => $p->nomor_paket,
                    'judul' => $p->nama_paket,
                ];
            }
        }
        // 3. Role: PPK (Pejabat Pembuat Komitmen)
        elseif ($userRole === 'ppk') {
            $ringkasanTitle = 'Ringkasan Komitmen & Paket Pengadaan (PPK: '.$user->nama.')';
            $tableTitle = 'Rekapitulasi Paket Komitmen Pengadaan (PPK: '.$user->nama.')';

            $ppkPaketList = PaketPengadaan::with(['permohonan.unit', 'vendor', 'kontrakSpk', 'pembayaran.transaksiPencairan'])
                ->where('id_user_ppk', $user->id_user)
                ->when($tahun, fn ($q) => $q->whereYear('created_at', $tahun))
                ->when($bulan, fn ($q) => $q->whereMonth('created_at', $bulan))
                ->latest('created_at')
                ->get();

            $totalPaketPPK = $ppkPaketList->count();
            $ppkPaketNomorList = $ppkPaketList->pluck('nomor_paket');
            $antreanAwal = PermohonanPengadaan::where('id_user_ppk_awal', $user->id_user)
                ->whereIn('status_permohonan', [PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, 'reviu_paralel'])
                ->count();
            $totalKontrakPPK = (float) KontrakSPK::whereIn('nomor_paket', $ppkPaketNomorList)->sum('nilai_kontrak');
            $tenderCount = $ppkPaketList->where('jalur_routing', 'ppk_diatas_200jt')->count();
            $memoPending = MemoBayar::where('role_approver', 'ppk')->whereIn('status_memo', ['diajukan', 'menunggu'])
                ->whereHas('pembayaran.paket', fn ($q) => $q->where('id_user_ppk', $user->id_user))
                ->count();
            $ppkPembayaranNomor = Pembayaran::whereIn('nomor_paket', $ppkPaketNomorList)->pluck('nomor_pembayaran');
            $totalCair = (float) TransaksiPencairan::whereIn('nomor_pembayaran', $ppkPembayaranNomor)->sum('nominal_transfer');

            $laporanCards = [
                ['label' => 'Total Paket Komitmen PPK', 'val' => $totalPaketPPK.' Paket', 'icon' => 'briefcase', 'sub' => 'Paket di bawah penugasan Anda'],
                ['label' => 'Antrean Persetujuan Awal', 'val' => $antreanAwal.' Berkas', 'icon' => 'clock', 'sub' => 'Permohonan masuk tahap PPK'],
                ['label' => 'Total Nilai Kontrak / SPK', 'val' => 'Rp '.number_format($totalKontrakPPK, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Komitmen kontrak sah aktif'],
                ['label' => 'Paket Tender / Seleksi (>200Jt)', 'val' => $tenderCount.' Paket', 'icon' => 'shield-check', 'sub' => 'Paket pengadaan skala besar'],
                ['label' => 'Memo Bayar Menunggu ACC', 'val' => $memoPending.' Berkas', 'icon' => 'document-text', 'sub' => 'Persetujuan pembayaran tagihan'],
                ['label' => 'Total Realisasi Pencairan SP2D', 'val' => 'Rp '.number_format($totalCair, 0, ',', '.'), 'icon' => 'check-circle', 'sub' => 'Realisasi pencairan belanja'],
            ];

            $tableColumns = ['No.', 'Nomor Paket', 'Nama Paket Pengadaan', 'Metode Pengadaan', 'Nilai Kontrak / HPS', 'Status Paket'];
            foreach ($ppkPaketList as $idx => $p) {
                $valNominal = $p->kontrakSpk?->nilai_kontrak ?? $p->nilai_hps;
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $p->nomor_paket,
                    'col2' => $p->nama_paket,
                    'col3' => ucwords(str_replace('_', ' ', $p->metode_pengadaan ?? 'Tender / Non-Tender')),
                    'col4' => 'Rp '.number_format($valNominal, 0, ',', '.'),
                    'col5' => ucwords(str_replace('_', ' ', $p->status_paket)),
                    'link' => route('paket.index', ['selected' => $p->nomor_paket]),
                    'nomor_surat' => $p->nomor_paket,
                    'judul' => $p->nama_paket,
                ];
            }
        }
        // 4. Role: PERLENGKAPAN (Penerimaan Barang & BAST)
        elseif ($userRole === 'perlengkapan') {
            $ringkasanTitle = 'Ringkasan Penerimaan Barang & Aset (Perlengkapan)';
            $tableTitle = 'Rekapitulasi Penerimaan Barang & Berita Acara Serah Terima (BAST)';

            $penerimaanList = PenerimaanBarang::with(['paket.vendor', 'paket.permohonan.unit', 'paket.kontrakSpk'])
                ->when($tahun, fn ($q) => $q->whereYear('tanggal_penerimaan', $tahun))
                ->when($bulan, fn ($q) => $q->whereMonth('tanggal_penerimaan', $bulan))
                ->latest('tanggal_penerimaan')
                ->get();

            $totalSpkMasuk = KontrakSPK::where('status_spk', 'aktif')->orWhereNotNull('nomor_spk')->count();
            $belumDiperiksa = KontrakSPK::whereDoesntHave('paket.penerimaanBarang')->count();
            $totalBast = $penerimaanList->count();
            $barangCount = $penerimaanList->where('kategori_belanja', 'belanja_barang')->count();
            $modalCount = $penerimaanList->where('kategori_belanja', 'belanja_modal')->count();
            $totalNilaiBast = (float) KontrakSPK::whereIn('nomor_paket', $penerimaanList->pluck('nomor_paket'))->sum('nilai_kontrak');

            $laporanCards = [
                ['label' => 'Total Kontrak / SPK Masuk', 'val' => $totalSpkMasuk.' Berkas', 'icon' => 'document-check', 'sub' => 'Perikatan aktif institusi'],
                ['label' => 'Menunggu Pemeriksaan Fisik', 'val' => $belumDiperiksa.' Paket', 'icon' => 'clock', 'sub' => 'Belum dilakukan serah terima'],
                ['label' => 'Total BAST Diterbitkan', 'val' => $totalBast.' BAST', 'icon' => 'check-badge', 'sub' => 'Serah terima fisik sah'],
                ['label' => 'Aset Belanja Barang', 'val' => $barangCount.' Berkas', 'icon' => 'cube', 'sub' => 'Barang habis pakai / operasional'],
                ['label' => 'Aset Belanja Modal', 'val' => $modalCount.' Berkas', 'icon' => 'building-library', 'sub' => 'Aset tetap & inventaris'],
                ['label' => 'Total Nilai Fisik Diterima', 'val' => 'Rp '.number_format($totalNilaiBast, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Akumulasi barang lolos verifikasi'],
            ];

            $tableColumns = ['No.', 'Nomor BAST', 'Nama Paket / Barang', 'Tanggal Penerimaan', 'Kategori Belanja', 'Status Verifikasi'];
            foreach ($penerimaanList as $idx => $item) {
                $bastNo = $item->nomor_bast ?: ('BAST-'.str_pad((string) $item->id_penerimaan, 4, '0', STR_PAD_LEFT));
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $bastNo,
                    'col2' => $item->paket?->nama_paket ?? 'Penerimaan Hasil Pengadaan',
                    'col3' => $item->tanggal_penerimaan ? Carbon::parse($item->tanggal_penerimaan)->translatedFormat('d M Y') : '-',
                    'col4' => ucwords(str_replace('_', ' ', $item->kategori_belanja ?? 'Belanja Barang')),
                    'col5' => ucwords(str_replace('_', ' ', $item->status_penerimaan ?? 'Diterima')),
                    'link' => route('penerimaan.index'),
                    'nomor_surat' => $bastNo,
                    'judul' => $item->paket?->nama_paket ?? 'Barang Pengadaan',
                ];
            }
        }
        // 5. Role: KEUANGAN (Pembayaran Tagihan & SP2D)
        elseif ($userRole === 'keuangan') {
            $ringkasanTitle = 'Ringkasan Pembayaran Tagihan & Realisasi SP2D (Keuangan)';
            $tableTitle = 'Rekapitulasi Verifikasi Pembayaran & Pencairan Dana (SP2D)';

            $pembayaranList = Pembayaran::with(['paket.vendor', 'transaksiPencairan', 'paket.permohonan.unit'])
                ->when($tahun, fn ($q) => $q->whereYear('tanggal_pengajuan', $tahun))
                ->when($bulan, fn ($q) => $q->whereMonth('tanggal_pengajuan', $bulan))
                ->latest('tanggal_pengajuan')
                ->get();

            $sppSiap = $pembayaranList->where('status_pembayaran', 'pengajuan')->count();
            $sp2dSiap = $pembayaranList->where('status_pembayaran', 'perintah_bayar')->count();
            $totalCairTx = TransaksiPencairan::count();
            $totalNominalCair = (float) TransaksiPencairan::sum('nominal_transfer');
            $totalTagihan = (float) $pembayaranList->sum('total_tagihan');
            $sisaTagihan = max(0, $totalTagihan - $totalNominalCair);

            $laporanCards = [
                ['label' => 'SPP Siap Verifikasi', 'val' => $sppSiap.' Berkas', 'icon' => 'clock', 'sub' => 'Tagihan rekanan masuk'],
                ['label' => 'SP2D Siap Dicairkan', 'val' => $sp2dSiap.' Berkas', 'icon' => 'document-check', 'sub' => 'Perintah bayar telah terbit'],
                ['label' => 'Total Transaksi Lunas / Cair', 'val' => $totalCairTx.' Transaksi', 'icon' => 'check-badge', 'sub' => 'SP2D berhasil ditransfer'],
                ['label' => 'Total Realisasi Pencairan SP2D', 'val' => 'Rp '.number_format($totalNominalCair, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Dana belanja keluar ke vendor'],
                ['label' => 'Total Nilai Tagihan Masuk', 'val' => 'Rp '.number_format($totalTagihan, 0, ',', '.'), 'icon' => 'document-text', 'sub' => 'Akumulasi seluruh pengajuan'],
                ['label' => 'Sisa Tagihan Dalam Proses', 'val' => 'Rp '.number_format($sisaTagihan, 0, ',', '.'), 'icon' => 'calculator', 'sub' => 'Antrean verifikasi & pencairan'],
            ];

            $tableColumns = ['No.', 'Nomor Pembayaran', 'Nama Paket / Rekanan', 'Tanggal Pengajuan', 'Nominal Pembayaran', 'Status Pembayaran'];
            foreach ($pembayaranList as $idx => $item) {
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $item->nomor_pembayaran,
                    'col2' => $item->paket?->nama_paket ?? ('Tagihan '.($item->paket?->vendor?->nama_vendor ?? 'Vendor')),
                    'col3' => $item->tanggal_pengajuan ? Carbon::parse($item->tanggal_pengajuan)->translatedFormat('d M Y') : '-',
                    'col4' => 'Rp '.number_format($item->total_tagihan ?? 0, 0, ',', '.'),
                    'col5' => ucwords(str_replace('_', ' ', $item->status_pembayaran)),
                    'link' => route('pembayaran.index'),
                    'nomor_surat' => $item->nomor_pembayaran,
                    'judul' => $item->paket?->nama_paket ?? 'Pembayaran Tagihan',
                ];
            }
        }
        // 6. Role: STAFF BIDANG 2 (Penerimaan & Telaah Berkas)
        elseif ($userRole === 'staff_bidang_2') {
            $ringkasanTitle = 'Ringkasan Penerimaan & Telaah Berkas (Staf Bidang II)';
            $tableTitle = 'Rekapitulasi Penerimaan & Telaah Berkas Pengadaan (Staf Bidang II)';

            $b2PermohonanList = PermohonanPengadaan::with('unit')
                ->when($tahun, fn ($q) => $q->whereYear('tanggal_pengajuan', $tahun))
                ->when($bulan, fn ($q) => $q->whereMonth('tanggal_pengajuan', $bulan))
                ->when($kodeUnit, fn ($q) => $q->where('kode_unit', $kodeUnit))
                ->latest('tanggal_pengajuan')
                ->get();

            $totalMasuk = $b2PermohonanList->count();
            $menungguTelaah = $b2PermohonanList->whereIn('status_permohonan', ['diajukan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2])->count();
            $reviuParalel = $b2PermohonanList->where('status_permohonan', 'reviu_paralel')->count();
            $disetujuiB2 = $b2PermohonanList->where('status_permohonan', 'disetujui')->count();
            $revisiTolak = $b2PermohonanList->whereIn('status_permohonan', ['ditolak', 'revisi'])->count();
            $totalPaguMasuk = (float) $b2PermohonanList->sum('estimasi_total_pagu');

            $laporanCards = [
                ['label' => 'Total Berkas Usulan Masuk', 'val' => $totalMasuk.' Berkas', 'icon' => 'document-text', 'sub' => 'Dari seluruh jurusan & unit'],
                ['label' => 'Menunggu Telaah Administrasi', 'val' => $menungguTelaah.' Berkas', 'icon' => 'clock', 'sub' => 'Tahap verifikasi berkas awal'],
                ['label' => 'Berkas Reviu Paralel', 'val' => $reviuParalel.' Berkas', 'icon' => 'arrow-path', 'sub' => 'Pemeriksaan MAK & PPK awal'],
                ['label' => 'Berkas Telah Disetujui', 'val' => $disetujuiB2.' Berkas', 'icon' => 'check-badge', 'sub' => 'Disetujui Wadir 2 & siap paket'],
                ['label' => 'Berkas Ditolak / Revisi', 'val' => $revisiTolak.' Berkas', 'icon' => 'x-circle', 'sub' => 'Perlu perbaikan oleh unit'],
                ['label' => 'Total Pagu Berkas Masuk', 'val' => 'Rp '.number_format($totalPaguMasuk, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Estimasi akumulasi usulan'],
            ];

            $tableColumns = ['No.', 'Nomor Surat', 'Jurusan / Unit Pengusul', 'Nama Pengadaan', 'Estimasi Pagu', 'Status Berkas'];
            foreach ($b2PermohonanList as $idx => $item) {
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $item->nomor_surat,
                    'col2' => $item->unit?->nama_unit ?? $item->kode_unit,
                    'col3' => $item->judul_pengadaan,
                    'col4' => 'Rp '.number_format($item->estimasi_total_pagu, 0, ',', '.'),
                    'col5' => ucwords(str_replace('_', ' ', $item->status_permohonan)),
                    'link' => route('permohonan.index', ['selected' => $item->nomor_surat]),
                    'nomor_surat' => $item->nomor_surat,
                    'judul' => $item->judul_pengadaan,
                ];
            }
        }
        // 7. Role: PERENCANAAN (Alokasi MAK DIPA)
        elseif ($userRole === 'perencanaan') {
            $ringkasanTitle = 'Ringkasan Alokasi Anggaran MAK DIPA (Perencanaan)';
            $tableTitle = 'Rekapitulasi Alokasi Mata Anggaran Kegiatan (MAK) DIPA';

            $makList = AnggaranMAK::query()
                ->when($tahun, fn ($q) => $q->where('tahun_anggaran', $tahun))
                ->latest('updated_at')
                ->get();

            $totalPaguDipa = (float) $makList->sum('pagu_anggaran');
            $totalSisaMak = (float) $makList->sum('sisa_pagu_mak');
            $paguTeralokasi = max(0, $totalPaguDipa - $totalSisaMak);
            $lockedCount = $makList->where('is_locked', true)->count();
            $activeCount = $makList->where('is_locked', false)->count();
            $menungguMak = PermohonanPengadaan::whereIn('status_permohonan', ['diajukan', 'reviu_paralel', 'pending_anggaran', PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN])
                ->whereNull('kode_mak')
                ->count();

            $laporanCards = [
                ['label' => 'Total Pagu Anggaran DIPA', 'val' => 'Rp '.number_format($totalPaguDipa, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Pagu DIPA tahun '.$tahun],
                ['label' => 'Pagu MAK Teralokasi', 'val' => 'Rp '.number_format($paguTeralokasi, 0, ',', '.'), 'icon' => 'check-circle', 'sub' => 'Alokasi komitmen pengadaan'],
                ['label' => 'Sisa Saldo Bebas MAK', 'val' => 'Rp '.number_format($totalSisaMak, 0, ',', '.'), 'icon' => 'calculator', 'sub' => 'Saldo anggaran belum terserap'],
                ['label' => 'Rekening MAK Terkunci (Lock)', 'val' => $lockedCount.' Rekening', 'icon' => 'lock-closed', 'sub' => 'Otoritas kunci perencanaan'],
                ['label' => 'Rekening MAK Aktif / Terbuka', 'val' => $activeCount.' Rekening', 'icon' => 'lock-open', 'sub' => 'Dapat digunakan untuk usulan'],
                ['label' => 'Usulan Menunggu Alokasi MAK', 'val' => $menungguMak.' Berkas', 'icon' => 'clock', 'sub' => 'Antrean verifikasi anggaran'],
            ];

            $tableColumns = ['No.', 'Kode MAK', 'Uraian Mata Anggaran', 'Tahun', 'Pagu Anggaran', 'Sisa Saldo MAK'];
            foreach ($makList as $idx => $item) {
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $item->kode_mak,
                    'col2' => $item->nama_mak ?? $item->uraian_mak ?? 'Mata Anggaran Kegiatan',
                    'col3' => (string) $item->tahun_anggaran,
                    'col4' => 'Rp '.number_format($item->pagu_anggaran, 0, ',', '.'),
                    'col5' => 'Rp '.number_format($item->sisa_pagu_mak, 0, ',', '.'),
                    'link' => route('verifikasi.perencanaan'),
                    'nomor_surat' => $item->kode_mak,
                    'judul' => $item->nama_mak ?? $item->uraian_mak ?? 'MAK DIPA',
                ];
            }
        }
        // 8. Role: PPBJ (Pengelolaan Paket & Jalur Eksekusi)
        elseif ($userRole === 'ppbj') {
            $ringkasanTitle = 'Ringkasan Pengelolaan Paket Pengadaan (PPBJ)';
            $tableTitle = 'Rekapitulasi Paket Pengadaan & Jalur Eksekusi (PPBJ)';

            $ppbjPaketList = PaketPengadaan::with(['vendor', 'kontrakSpk', 'permohonan.unit'])
                ->when($tahun, fn ($q) => $q->whereYear('created_at', $tahun))
                ->when($bulan, fn ($q) => $q->whereMonth('created_at', $bulan))
                ->latest('created_at')
                ->get();

            $totalPaketPPBJ = $ppbjPaketList->count();
            $langsungCount = $ppbjPaketList->where('jalur_routing', 'langsung_sd_50jt')->count();
            $ppCount = $ppbjPaketList->where('jalur_routing', 'pp_50jt_sd_200jt')->count();
            $ppkCount = $ppbjPaketList->where('jalur_routing', 'ppk_diatas_200jt')->count();
            $totalKontrakPPBJ = (float) KontrakSPK::whereIn('nomor_paket', $ppbjPaketList->pluck('nomor_paket'))->sum('nilai_kontrak');
            $siapPaketCount = PermohonanPengadaan::where('status_permohonan', 'disetujui')->whereDoesntHave('paketPengadaan')->count();

            $laporanCards = [
                ['label' => 'Total Paket Terdaftar PPBJ', 'val' => $totalPaketPPBJ.' Paket', 'icon' => 'briefcase', 'sub' => 'Pengadaan seluruh institusi'],
                ['label' => 'Jalur Belanja Langsung (<=50Jt)', 'val' => $langsungCount.' Paket', 'icon' => 'bolt', 'sub' => 'Kwitansi & non-kontrak'],
                ['label' => 'Jalur Pejabat Pengadaan (50-200Jt)', 'val' => $ppCount.' Paket', 'icon' => 'user-check', 'sub' => 'Penugasan Pejabat Pengadaan'],
                ['label' => 'Jalur Pokja / PPK (>200Jt)', 'val' => $ppkCount.' Paket', 'icon' => 'shield-check', 'sub' => 'Tender & perikatan PPK'],
                ['label' => 'Total Nilai Kontrak / SPK', 'val' => 'Rp '.number_format($totalKontrakPPBJ, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Perikatan resmi aktif'],
                ['label' => 'Usulan Siap Dibuat Paket', 'val' => $siapPaketCount.' Berkas', 'icon' => 'clock', 'sub' => 'Telah disetujui Wadir 2'],
            ];

            $tableColumns = ['No.', 'Nomor Paket', 'Nama Paket Pengadaan', 'Jalur Eksekusi', 'Nilai HPS', 'Status Paket'];
            foreach ($ppbjPaketList as $idx => $p) {
                $jalurLabel = match ($p->jalur_routing) {
                    'langsung_sd_50jt' => 'Belanja Langsung (<=50jt)',
                    'pp_50jt_sd_200jt' => 'Pejabat Pengadaan (50-200jt)',
                    'ppk_diatas_200jt' => 'PPK (>200jt)',
                    default => 'Umum'
                };
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $p->nomor_paket,
                    'col2' => $p->nama_paket,
                    'col3' => $jalurLabel,
                    'col4' => 'Rp '.number_format($p->nilai_hps, 0, ',', '.'),
                    'col5' => ucwords(str_replace('_', ' ', $p->status_paket)),
                    'link' => route('paket.index', ['selected' => $p->nomor_paket]),
                    'nomor_surat' => $p->nomor_paket,
                    'judul' => $p->nama_paket,
                ];
            }
        }
        // 9. Role: ADMIN (Manajemen Sistem & Pengguna)
        elseif ($userRole === 'admin') {
            $ringkasanTitle = 'Ringkasan Manajemen Pengguna & Sistem (Admin)';
            $tableTitle = 'Distribusi dan Status Pengguna Institusi PNJ';
            $allUsers = User::with('unit')->latest()->get();
            $totalUsers = $allUsers->count();
            $activeUsers = $allUsers->where('status_aktif', true)->count();
            $pendingUsers = $allUsers->where('status_registrasi', 'menunggu_persetujuan')->count();
            $rejectedUsers = $allUsers->where('status_registrasi', 'ditolak')->count();
            $totalRoles = $allUsers->pluck('role')->unique()->count();
            $pctAktif = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;

            $laporanCards = [
                ['label' => 'Total Pengguna Terdaftar', 'val' => $totalUsers.' Akun', 'icon' => 'users', 'sub' => 'Sivitas terdaftar di sistem'],
                ['label' => 'Pengguna Aktif Terverifikasi', 'val' => $activeUsers.' Akun', 'icon' => 'check-circle', 'sub' => 'Dapat mengakses sistem'],
                ['label' => 'Registrasi Menunggu Persetujuan', 'val' => $pendingUsers.' Pendaftar', 'icon' => 'clock', 'sub' => 'Memerlukan ACC admin'],
                ['label' => 'Registrasi Ditolak', 'val' => $rejectedUsers.' Akun', 'icon' => 'x-circle', 'sub' => 'Permohonan akun ditolak'],
                ['label' => 'Total Peran / Role Aktif', 'val' => $totalRoles.' Peran', 'icon' => 'shield-check', 'sub' => 'Matriks hak akses RBAC'],
                ['label' => 'Persentase Akun Aktif', 'val' => $pctAktif.'%', 'icon' => 'chart-pie', 'sub' => 'Rasio akun terverifikasi'],
            ];

            $tableColumns = ['No.', 'Nama Pengguna', 'NIP / Username', 'Unit Kerja / Jurusan', 'Role Akses', 'Status Akun'];
            foreach ($allUsers as $idx => $u) {
                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $u->nama,
                    'col2' => $u->nip ?? $u->username ?? '-',
                    'col3' => $u->unit->nama_unit ?? $u->kode_unit ?? 'Pusat',
                    'col4' => strtoupper($u->role),
                    'col5' => $u->status_aktif ? 'Aktif' : ($u->status_registrasi === 'menunggu_persetujuan' ? 'Menunggu ACC' : 'Nonaktif'),
                    'link' => route('admin.persetujuan-registrasi.index'),
                    'nomor_surat' => $u->nip ?? $u->username ?? '',
                    'judul' => $u->nama,
                ];
            }
        }
        // 10. Role: EKSEKUTIF INSTITUSI / WADIR 2 (Figma 526:3083) & Default Fallback
        else {
            $ringkasanTitle = 'Ringkasan Keuangan & Realisasi DIPA Institusi';
            $tableTitle = 'Distribusi Anggaran per Jurusan dan Unit Kerja (Eksekutif Institusi)';

            // Pagu MAK DIPA
            $makQuery = AnggaranMAK::query();
            if ($tahun) {
                $makQuery->where('tahun_anggaran', $tahun);
            }
            $totalPaguDipa = (float) $makQuery->sum('pagu_anggaran');

            // Usulan Permohonan
            $permohonanQuery = PermohonanPengadaan::query();
            if ($tahun) {
                $permohonanQuery->whereYear('tanggal_pengajuan', $tahun);
            }
            if ($bulan) {
                $permohonanQuery->whereMonth('tanggal_pengajuan', $bulan);
            }
            if ($kodeUnit) {
                $permohonanQuery->where('kode_unit', $kodeUnit);
            }
            $totalUsulan = (float) $permohonanQuery->sum('estimasi_total_pagu');

            // Kontrak SPK
            $spkQuery = KontrakSPK::query();
            if ($tahun) {
                $spkQuery->whereYear('tanggal_spk', $tahun);
            }
            if ($bulan) {
                $spkQuery->whereMonth('tanggal_spk', $bulan);
            }
            $totalKontrak = (float) $spkQuery->sum('nilai_kontrak');

            // Realisasi Pencairan SP2D
            $cairQuery = TransaksiPencairan::query();
            if ($tahun) {
                $cairQuery->whereYear('tanggal_transfer', $tahun);
            }
            if ($bulan) {
                $cairQuery->whereMonth('tanggal_transfer', $bulan);
            }
            $totalRealisasi = (float) $cairQuery->sum('nominal_transfer');

            $sisaAnggaran = max(0, $totalPaguDipa - $totalRealisasi);
            $persentaseSerapan = $totalPaguDipa > 0 ? round(($totalRealisasi / $totalPaguDipa) * 100, 1) : 0;

            $laporanCards = [
                ['label' => 'Total Pagu Anggaran DIPA', 'val' => 'Rp '.number_format($totalPaguDipa, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Pagu DIPA tahun '.$tahun],
                ['label' => 'Total Realisasi Pencairan SP2D', 'val' => 'Rp '.number_format($totalRealisasi, 0, ',', '.'), 'icon' => 'check-circle', 'sub' => 'Pencairan SP2D terbit'],
                ['label' => 'Total Estimasi Usulan Permohonan', 'val' => 'Rp '.number_format($totalUsulan, 0, ',', '.'), 'icon' => 'document-text', 'sub' => 'Akumulasi seluruh usulan'],
                ['label' => 'Sisa Anggaran Belum Terserap', 'val' => 'Rp '.number_format($sisaAnggaran, 0, ',', '.'), 'icon' => 'calculator', 'sub' => 'Sisa saldo alokasi DIPA'],
                ['label' => 'Total Nilai Kontrak / SPK', 'val' => 'Rp '.number_format($totalKontrak, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Kontrak & perikatan sah'],
                ['label' => 'Persentase Serapan Anggaran', 'val' => $persentaseSerapan.'%', 'icon' => 'chart-pie', 'sub' => 'Realisasi terhadap DIPA'],
            ];

            $tableColumns = ['No.', 'Kode Unit', 'Nama Jurusan / Unit Kerja', 'Jumlah Usulan', 'Total Nilai Usulan', 'Pangsa Usulan (%)'];
            foreach ($units as $idx => $u) {
                if ($kodeUnit && $u->kode_unit !== $kodeUnit) {
                    continue;
                }
                $pQ = PermohonanPengadaan::where('kode_unit', $u->kode_unit);
                if ($tahun) {
                    $pQ->whereYear('tanggal_pengajuan', $tahun);
                }
                if ($bulan) {
                    $pQ->whereMonth('tanggal_pengajuan', $bulan);
                }
                $countP = $pQ->count();
                $nilaiP = (float) $pQ->sum('estimasi_total_pagu');
                $sharePct = $totalUsulan > 0 ? round(($nilaiP / $totalUsulan) * 100, 1) : 0;

                $laporanTable[] = [
                    'no' => $idx + 1,
                    'col1' => $u->kode_unit,
                    'col2' => $u->nama_unit,
                    'col3' => $countP.' Berkas',
                    'col4' => 'Rp '.number_format($nilaiP, 0, ',', '.'),
                    'col5' => $sharePct.'%',
                    'link' => route('permohonan.index', ['unit' => $u->kode_unit]),
                    'nomor_surat' => $u->kode_unit,
                    'judul' => $u->nama_unit,
                ];
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'active_tab' => $activeTab,
                'riwayat' => $riwayatList,
                'laporan' => [
                    'cards' => $laporanCards,
                    'ringkasan_title' => $ringkasanTitle,
                    'table_title' => $tableTitle,
                    'columns' => $tableColumns,
                    'rows' => $laporanTable,
                ],
            ]);
        }

        return view('laporan.index', compact(
            'riwayatList',
            'laporanCards',
            'laporanTable',
            'ringkasanTitle',
            'tableTitle',
            'tableColumns',
            'tahun',
            'bulan',
            'kodeUnit',
            'units',
            'activeTab',
            'userRole'
        ));
    }

    /**
     * Resolusi label jenis aktivitas ramah pengguna dari string kode aksi audit log.
     */
    private function resolveJenisAktivitas(string $jenisAksi): string
    {
        $upper = strtoupper($jenisAksi);
        if (str_contains($upper, 'AJUKAN') || str_contains($upper, 'PERMOHONAN')) {
            return 'Pengajuan';
        }
        if (str_contains($upper, '_PP') || str_contains($upper, 'REVIEW_KELENGKAPAN_PP') || str_contains($upper, 'NEGOSIASI')) {
            return 'Pengadaan Langsung (PP)';
        }
        if (str_contains($upper, 'ACC') || str_contains($upper, 'SETUJU') || str_contains($upper, 'VERIFIKASI') || str_contains($upper, 'TELAAH')) {
            return 'Persetujuan';
        }
        if (str_contains($upper, 'TOLAK')) {
            return 'Penolakan';
        }
        if (str_contains($upper, 'REVISI')) {
            return 'Revisi';
        }
        if (str_contains($upper, 'MAK') || str_contains($upper, 'ANGGARAN')) {
            return 'Anggaran';
        }
        if (str_contains($upper, 'KONTRAK') || str_contains($upper, 'SPK') || str_contains($upper, 'BELANJA')) {
            return 'Kontrak';
        }
        if (str_contains($upper, 'BAST') || str_contains($upper, 'PENERIMAAN')) {
            return 'Penerimaan';
        }
        if (str_contains($upper, 'CAIR') || str_contains($upper, 'BAYAR') || str_contains($upper, 'SP2D')) {
            return 'Pembayaran';
        }

        return 'Sistem';
    }

    /**
     * Mengunduh Laporan Ringkasan Eksekutif dalam format PDF (< 3 Detik - NFR 12).
     */
    public function unduhLaporanRingkasanPdf(Request $request): Response
    {
        $user = Auth::user();
        $userRole = $user ? $user->role : 'user';

        if ($user && ! in_array($userRole, ['admin', 'ppbj', 'ppk', 'pp', 'wadir_2', 'perencanaan', 'staff_bidang_2', 'perlengkapan', 'keuangan', 'user'], true)) {
            if ($user->hasPermission('audit_log.view') || $user->hasPermission('paket.view_all')) {
                $userRole = 'ppbj';
            } elseif ($user->hasPermission('pembayaran.pencairan_sp2d')) {
                $userRole = 'keuangan';
            } elseif ($user->hasPermission('penerimaan.view')) {
                $userRole = 'perlengkapan';
            } elseif ($user->hasPermission('anggaran.mak_view')) {
                $userRole = 'perencanaan';
            } elseif ($user->hasPermission('verifikasi.wadir_2_view')) {
                $userRole = 'wadir_2';
            } elseif ($user->hasPermission('verifikasi.bidang_2_view')) {
                $userRole = 'staff_bidang_2';
            } elseif ($user->hasPermission('reviu.ppk')) {
                $userRole = 'ppk';
            } elseif ($user->hasPermission('reviu.pp')) {
                $userRole = 'pp';
            } else {
                $userRole = 'user';
            }
        }
        $tahun = $request->input('tahun', date('Y'));

        $filename = "Laporan_Ringkasan_PBJ_PNJ_{$tahun}.pdf";

        $title = 'LAPORAN RINGKASAN REKAPITULASI REALISASI PENGADAAN';
        $subtitle = "POLITEKNIK NEGERI JAKARTA - TAHUN ANGGARAN {$tahun}";
        $meta = 'Diunduh oleh: '.($user->nama ?? 'Pengguna').' ('.strtoupper($userRole).') pada '.now()->format('d/m/Y H:i:s');

        $pdfStream = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 180>>stream\nBT /F1 12 Tf 50 800 Td (".addslashes($title).') Tj /F1 10 Tf 50 780 Td ('.addslashes($subtitle).') Tj /F1 8 Tf 50 760 Td ('.addslashes($meta).") Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n430\n%%EOF";

        return response($pdfStream, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Mengunduh Laporan Rincian Detail dalam format PDF (< 3 Detik - NFR 12).
     */
    public function unduhLaporanDetailPdf(Request $request): Response
    {
        $user = Auth::user();
        $userRole = $user ? $user->role : 'user';

        if ($user && ! in_array($userRole, ['admin', 'ppbj', 'ppk', 'pp', 'wadir_2', 'perencanaan', 'staff_bidang_2', 'perlengkapan', 'keuangan', 'user'], true)) {
            if ($user->hasPermission('audit_log.view') || $user->hasPermission('paket.view_all')) {
                $userRole = 'ppbj';
            } elseif ($user->hasPermission('pembayaran.pencairan_sp2d')) {
                $userRole = 'keuangan';
            } elseif ($user->hasPermission('penerimaan.view')) {
                $userRole = 'perlengkapan';
            } elseif ($user->hasPermission('anggaran.mak_view')) {
                $userRole = 'perencanaan';
            } elseif ($user->hasPermission('verifikasi.wadir_2_view')) {
                $userRole = 'wadir_2';
            } elseif ($user->hasPermission('verifikasi.bidang_2_view')) {
                $userRole = 'staff_bidang_2';
            } elseif ($user->hasPermission('reviu.ppk')) {
                $userRole = 'ppk';
            } elseif ($user->hasPermission('reviu.pp')) {
                $userRole = 'pp';
            } else {
                $userRole = 'user';
            }
        }
        $tahun = $request->input('tahun', date('Y'));

        $filename = "Laporan_Detail_Pengadaan_PNJ_{$tahun}.pdf";

        $title = 'LAPORAN RINCIAN DETAIL PENGADAAN BARANG DAN JASA';
        $subtitle = "POLITEKNIK NEGERI JAKARTA - TAHUN ANGGARAN {$tahun}";
        $meta = 'Scope: '.strtoupper($userRole).' | Tanggal Cetak: '.now()->format('d/m/Y H:i:s');

        $pdfStream = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 180>>stream\nBT /F1 12 Tf 50 800 Td (".addslashes($title).') Tj /F1 10 Tf 50 780 Td ('.addslashes($subtitle).') Tj /F1 8 Tf 50 760 Td ('.addslashes($meta).") Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000111 00000 n\n0000000212 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n430\n%%EOF";

        return response($pdfStream, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Wrapper kompatibilitas laporan rekapitulasi terdahulu.
     */
    public function laporanRekapitulasiIndex(Request $request): View|JsonResponse
    {
        return $this->laporanIndex($request);
    }

    /**
     * Wrapper kompatibilitas unduh rekapitulasi terdahulu.
     */
    public function unduhRekapitulasiPdf(Request $request): Response
    {
        return $this->unduhLaporanRingkasanPdf($request);
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
            'environment' => config('app.env'),
            'database' => $dbStatus ? 'connected' : 'disconnected',
            'storage' => $storageWritable ? 'ready' : 'unreachable',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Menandai notifikasi telah dibaca atau ditutup oleh pengguna.
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
        $notif = $this->notificationService->kirimUjiCoba($user, $jenis);

        return back()->with(
            'success',
            "Notifikasi simulasi '{$notif->judul}' berhasil dibangkitkan dan dikirimkan ke surel {$user->email}."
        );
    }

    /**
     * Pengalihan rute monitoring lama ke rute laporan terpadu (Modul Riwayat & Laporan).
     */
    public function monitoringIndex(Request $request): RedirectResponse
    {
        return redirect()->route('laporan.index', $request->query());
    }

    // =========================================================================
    // HELPER: STREAMING UNDUH PDF RESMI (< 3 DETIK - NFR 12)
    // =========================================================================

    /**
     * Menyajikan streaming dokumen PDF resmi dengan respon cepat < 3 detik.
     */
    public function unduhDokumenPdf(Request $request, string $jenis, string $id): Response
    {
        return match ($jenis) {
            'permohonan' => app(PermohonanPengadaanController::class)->unduhPdf(
                PermohonanPengadaan::where('nomor_surat', $id)
                    ->when(is_numeric($id), fn ($q) => $q->orWhere('id', $id))
                    ->firstOrFail()
            ),
            'spk' => app(KontrakSPKController::class)->unduhPdf(KontrakSPK::findOrFail($id)),
            'bast' => app(PenerimaanBarangController::class)->unduhPdf(PenerimaanBarang::where('nomor_bast', $id)->firstOrFail()),
            'transfer', 'sp2d' => app(TransaksiPencairanController::class)->unduhPdf(TransaksiPencairan::where('nomor_transaksi_bank', $id)->firstOrFail()),
            'rekapitulasi' => $this->unduhRekapitulasiPdf($request),
            default => abort(404, 'Dokumen PDF tidak ditemukan.'),
        };
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
}
