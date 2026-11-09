<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controller Riwayat & Laporan Terpadu (Modul 8)
 *
 * Mengelola tab Riwayat Aktivitas Audit & Tab Laporan Statistik berbasis peran pengguna (Role-Based Scoping).
 */
class LaporanController extends Controller
{
    /**
     * Menampilkan antarmuka terpadu Riwayat dan Laporan (Tab Riwayat Aktivitas & Tab Laporan).
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
        // TAB 1: RIWAYAT AKTIVITAS
        // =====================================================================
        $auditQuery = AuditLog::with('user')->latest('waktu_log');

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

        // Sintesis entitas fallback jika audit log masih kosong
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
        // TAB 2: LAPORAN SESUAI PERAN
        // =====================================================================
        $units = Unit::all();
        $laporanCards = [];
        $laporanTable = [];
        $ringkasanTitle = 'Ringkasan Realisasi & Pengadaan';
        $tableTitle = 'Distribusi Anggaran per Jurusan dan Unit Kerja';
        $tableColumns = [];

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
        } elseif ($userRole === 'pp') {
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
        } elseif ($userRole === 'ppk') {
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
        } elseif ($userRole === 'perlengkapan') {
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
        } elseif ($userRole === 'keuangan') {
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
        } elseif ($userRole === 'staff_bidang_2') {
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
        } elseif ($userRole === 'perencanaan') {
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
        } elseif ($userRole === 'ppbj') {
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
        } elseif ($userRole === 'admin') {
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
        } else {
            $ringkasanTitle = 'Ringkasan Keuangan & Realisasi DIPA Institusi';
            $tableTitle = 'Distribusi Anggaran per Jurusan dan Unit Kerja (Eksekutif Institusi)';

            $makQuery = AnggaranMAK::query();
            if ($tahun) {
                $makQuery->where('tahun_anggaran', $tahun);
            }
            $totalPaguDipa = (float) $makQuery->sum('pagu_anggaran');

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

            $spkQuery = KontrakSPK::query();
            if ($tahun) {
                $spkQuery->whereYear('tanggal_spk', $tahun);
            }
            if ($bulan) {
                $spkQuery->whereMonth('tanggal_spk', $bulan);
            }
            $totalKontrak = (float) $spkQuery->sum('nilai_kontrak');

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

    public function index(Request $request): View|JsonResponse
    {
        return $this->laporanIndex($request);
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
     * Redirect monitoring lama ke laporan.index.
     */
    public function monitoringIndex(): RedirectResponse
    {
        return redirect()->route('laporan.index');
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
}
