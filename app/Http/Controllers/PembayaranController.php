<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\TransaksiPencairan;
use App\Models\User;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller Manajemen Pengajuan Tagihan & Pembayaran (Modul 7)
 *
 * Mengelola tahapan:
 * 1. Pengajuan dokumen pembayaran (SPK + Kwitansi + Invoice) oleh PPBJ setelah BAST terbit.
 * 2. Disposisi memo persetujuan pembayaran paralel oleh Wadir 2 dan PPK.
 * 3. Penerbitan Surat Perintah Pembayaran (SPP) dan kelengkapan legalitas vendor oleh PPBJ.
 * 4. Eksekusi pencairan dana SP2D dan unggah bukti transfer bank oleh Bagian Keuangan.
 */
class PembayaranController extends Controller
{
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan antrean dan riwayat alur pembayaran bertingkat.
     */
    public function pembayaranIndex(Request $request): View
    {
        $user = Auth::user();

        // 1. Paket dengan BAST selesai yang siap diajukan berkas pembayaran oleh PPBJ (BPMN N51 -> N52)
        $bastSiapBayar = PenerimaanBarang::with(['paket.kontrakSpk.vendor', 'paket.kontrak.vendor', 'paket.vendor', 'paket.permohonan.unit', 'fotoDokumentasi'])
            ->where('status_fisik', 'terima')
            ->whereDoesntHave('paket.pembayaran')
            ->latest('tanggal_penerimaan')
            ->get();

        // 2. Daftar pengajuan pembayaran SPP lengkap
        $pembayaranList = Pembayaran::with(['paket.kontrakSpk', 'paket.kontrak.vendor', 'paket.vendor', 'pengaju', 'memoBayar.user', 'transaksiPencairan'])
            ->latest('tanggal_pengajuan')
            ->paginate(15);

        // 3. Disposisi memo menunggu persetujuan (Wadir II & PPK Paralel)
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
                                    $sub->where('role_approver', 'wadir_2');
                                });
                            }
                            if ($canPpk) {
                                $query->orWhere(function ($sub) use ($user) {
                                    $sub->where('role_approver', 'ppk')
                                        ->where(function ($idQ) use ($user) {
                                            $idQ->where('id_user', $user->id_user)->orWhereNull('id_user');
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

        // 7. Berkas Pembayaran yang Ditolak (BPMN N60/N61 -> N52)
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

    public function index(Request $request): View
    {
        return $this->pembayaranIndex($request);
    }

    /**
     * Memproses pengajuan berkas pembayaran oleh PPBJ setelah BAST terbit.
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
            'nomor_kwitansi' => ['nullable', 'string', 'max:100'],
            'file_kwitansi' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'nomor_invoice' => ['nullable', 'string', 'max:100'],
            'file_invoice' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_dokumen_penunjang' => ['nullable', DokumenUploadRule::pdfOrImage()],
        ]);

        $paket = PaketPengadaan::findOrFail($validated['nomor_paket']);

        $penunjangPath = $request->hasFile('file_dokumen_penunjang')
            ? $request->file('file_dokumen_penunjang')->store('penunjang', 'public')
            : 'penunjang/resume_kontrak.pdf';

        $kwitansiPath = $request->hasFile('file_kwitansi')
            ? $request->file('file_kwitansi')->store('kwitansi', 'public')
            : ($paket->file_kwitansi_belanja ?? 'kwitansi/kwitansi_pembayaran.pdf');

        $invoicePath = $request->hasFile('file_invoice')
            ? $request->file('file_invoice')->store('invoice', 'public')
            : 'tagihan/invoice_dummy.pdf';

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
            'file_dokumen_penunjang' => $penunjangPath,
        ]);

        $paket->status_paket = 'pengajuan_pembayaran';
        $paket->save();

        // Bentuk memo persetujuan: PPK & Wadir II
        $defaultPpk = User::where('role', 'ppk')
            ->orWhereHas('roleEntity.permissions', fn ($q) => $q->where('nama_permission', 'pembayaran.memo_ppk'))
            ->first();
        $ppkUserId = $paket->id_user_ppk ?? $paket->permohonan?->id_user_ppk_awal ?? $defaultPpk?->id_user ?? $user->id_user;
        $memoLevel1 = MemoBayar::create([
            'id_user' => $ppkUserId,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'ppk',
            'status_memo' => 'menunggu',
        ]);

        $wadir = User::where('role', 'wadir_2')
            ->orWhereHas('roleEntity.permissions', fn ($q) => $q->where('nama_permission', 'pembayaran.memo_wadir2'))
            ->first();
        $memoLevel2 = MemoBayar::create([
            'id_user' => $wadir?->id_user,
            'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
            'role_approver' => 'wadir_2',
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

        $this->notificationService->notifyPengajuanBayar($pembayaran, $memoLevel1, $memoLevel2);

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Berkas dokumen pembayaran {$pembayaran->nomor_pembayaran} berhasil diajukan secara paralel ke Wakil Direktur II dan Pejabat Pembuat Komitmen (PPK) untuk penerbitan memo persetujuan."
        );
    }

    /**
     * Memproses perbaikan berkas dokumen pembayaran oleh PPBJ setelah ditolak Wadir II / PPK.
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

        if ($request->filled('nomor_surat_pesanan')) {
            $pembayaran->nomor_surat_pesanan = $validated['nomor_surat_pesanan'];
        }
        if ($request->filled('nomor_kwitansi')) {
            $pembayaran->nomor_kwitansi = $validated['nomor_kwitansi'];
        }
        if ($request->filled('nomor_invoice')) {
            $pembayaran->nomor_invoice = $validated['nomor_invoice'];
        }

        $pembayaran->status_pembayaran = 'reviu_memo';
        $pembayaran->catatan_reviu_akhir = 'Diperbaiki oleh PPBJ: '.($validated['catatan_perbaikan_ppbj'] ?? 'Dokumen pembayaran dan bukti perikatan telah disesuaikan.');
        $pembayaran->save();

        // Reset HANYA memo bayar yang sebelumnya berstatus 'ditolak'
        foreach ($pembayaran->memoBayar as $memo) {
            if ($memo->status_memo === 'ditolak') {
                $memo->status_memo = 'menunggu';
                $memo->tanggal_acc = null;
                $memo->catatan_memo = null;
                $memo->save();
            }
        }

        AuditLog::catat(
            $user->id_user,
            'PERBAIKAN_DOKUMEN_PEMBAYARAN',
            'pembayaran',
            $pembayaran->nomor_pembayaran,
            'ditolak',
            'reviu_memo',
            "PPBJ mengajukan ulang berkas pembayaran {$pembayaran->nomor_pembayaran} ke Wadir II / PPK terkait yang meminta perbaikan (BPMN N53 -> N56)"
        );

        $memoLevel1 = $pembayaran->memoBayar->where('role_approver', 'wadir_2')->first();
        $memoLevel2 = $pembayaran->memoBayar->where('role_approver', 'ppk')->first();
        $this->notificationService->notifyPengajuanBayar($pembayaran, $memoLevel1, $memoLevel2);

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Berkas dokumen pembayaran {$pembayaran->nomor_pembayaran} berhasil diperbaiki dan diteruskan kembali untuk penelaahan memo."
        );
    }

    public function update(Request $request, string $nomorPembayaran): RedirectResponse
    {
        return $this->pembayaranUpdate($request, $nomorPembayaran);
    }

    /**
     * Memproses persetujuan/penolakan memo pembayaran oleh Wadir 2 atau PPK.
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

        if ($memo->role_approver === 'wadir_2' && ! $user->hasRole('admin') && ! $user->hasPermission('pembayaran.memo_wadir2')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk menyetujui memo Wakil Direktur II.');
        }

        if ($memo->role_approver === 'ppk' && ! $user->hasRole('admin') && ! $user->hasPermission('pembayaran.memo_ppk')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk menyetujui memo Pejabat Pembuat Komitmen.');
        }

        if ($memo->role_approver === 'ppk' && $user->hasPermission('pembayaran.memo_ppk') && ! $user->hasRole('admin') && $memo->id_user && (int) $memo->id_user !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda hanya berhak menyetujui memo pada paket yang ditugaskan kepada Anda.');
        }

        if ($validated['status_memo'] === 'disetujui' && ! $request->hasFile('file_memo') && empty($memo->file_memo)) {
            return back()->withErrors(['file_memo' => 'Wajib mengunggah berkas naskah memo persetujuan pembayaran (PDF/Gambar).'])->withInput();
        }

        if ($validated['status_memo'] === 'ditolak' && empty($validated['catatan_memo'])) {
            return back()->withErrors(['catatan_memo' => 'Wajib mencantumkan alasan/catatan penolakan dokumen pembayaran.'])->withInput();
        }

        $memo->status_memo = $validated['status_memo'];
        $memo->catatan_memo = $validated['catatan_memo'];
        if ($request->filled('nomor_memo')) {
            $memo->nomor_memo = $validated['nomor_memo'];
        } elseif ($validated['status_memo'] === 'disetujui' && empty($memo->nomor_memo)) {
            $memo->nomor_memo = 'MEMO-BAYAR-'.strtoupper($memo->role_approver).'/'.date('Ymd').'/'.rand(100, 999);
        }
        if ($request->hasFile('file_memo')) {
            $memo->file_memo = $request->file('file_memo')->store('memo_bayar', 'public');
        }
        $memo->tanggal_acc = ($validated['status_memo'] === 'disetujui') ? now() : null;
        $memo->save();

        $pembayaran = $memo->pembayaran;
        if ($validated['status_memo'] === 'ditolak' && $pembayaran) {
            $pembayaran->status_pembayaran = 'ditolak';
            $pembayaran->catatan_reviu_akhir = ($memo->role_approver === 'wadir_2' ? 'Catatan Wadir II: ' : 'Catatan PPK: ').($validated['catatan_memo'] ?? 'Dokumen pembayaran ditolak pada tahap disposisi memo bayar.');
            $pembayaran->save();
        }

        AuditLog::catat(
            $user->id_user,
            'DISPOSISI_MEMO_PEMBAYARAN',
            'memo_bayar',
            (string) $memo->id,
            null,
            $memo->status_memo,
            "Persetujuan memo pembayaran ({$memo->role_approver}) ditetapkan menjadi {$memo->status_memo}".($memo->nomor_memo ? " [{$memo->nomor_memo}]" : '')
        );

        $this->notificationService->notifyApproveMemoBayar($memo);

        return redirect()->route('pembayaran.index')->with(
            'success',
            'Memo persetujuan pembayaran ('.($memo->role_approver === 'wadir_2' ? 'Wadir II' : 'PPK').') berhasil di-'.strtoupper($memo->status_memo).'.'
        );
    }

    /**
     * Memproses penerbitan Surat Perintah Pembayaran (SPP) oleh PPBJ setelah seluruh memo disetujui.
     */
    public function terbitkanSppWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_pembayaran' => ['required', 'exists:pembayaran,nomor_pembayaran'],
            'catatan_reviu_akhir' => ['nullable', 'string'],
            'file_spp' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_legalitas_vendor' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'npwp_vendor' => ['nullable', 'string', 'max:50'],
        ]);

        $pembayaran = Pembayaran::where('nomor_pembayaran', $validated['nomor_pembayaran'])->firstOrFail();

        $pendingCount = $pembayaran->memoBayar()->where('status_memo', '!=', 'disetujui')->count();
        if ($pendingCount > 0) {
            return back()->withErrors(['nomor_pembayaran' => 'Perintah pembayaran belum dapat diterbitkan karena memo bayar belum disetujui lengkap oleh PPK dan Wadir 2.']);
        }

        if ($request->hasFile('file_spp')) {
            $pembayaran->file_dokumen_penunjang = $request->file('file_spp')->store('spp', 'public');
        }

        $vendor = $pembayaran->paket?->vendor ?? $pembayaran->paket?->kontrakSpk?->vendor;
        if ($vendor) {
            if ($request->hasFile('file_legalitas_vendor')) {
                $vendor->file_dokumen_legalitas = $request->file('file_legalitas_vendor')->store('vendor_legalitas', 'public');
            }
            if ($request->filled('npwp_vendor')) {
                $vendor->npwp = $validated['npwp_vendor'];
            }
            $vendor->save();
        }

        $pembayaran->tanggal_perintah_bayar = now();
        $pembayaran->catatan_reviu_akhir = $validated['catatan_reviu_akhir'] ?? 'Dokumen pembayaran dan legalitas vendor lengkap dan sah';
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
            "PPBJ menerbitkan Surat Perintah Pembayaran (SPP) {$pembayaran->nomor_pembayaran} beserta Dokumen Pembayaran dan Legalitas Vendor ke Bagian Keuangan"
        );

        $this->notificationService->notifyTerbitkanSpp($pembayaran);

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Surat Perintah Pembayaran (SPP) {$pembayaran->nomor_pembayaran} beserta Dokumen Pembayaran, Legalitas Vendor, dan Memo Persetujuan berhasil diteruskan ke Bagian Keuangan (BPMN N71)."
        );
    }

    /**
     * Memproses eksekusi pencairan dana SP2D dan upload slip bukti transfer bank oleh Keuangan.
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
            'file_bukti_transfer' => ['required', DokumenUploadRule::pdfOrImage()],
            'keterangan_cair' => ['nullable', 'string'],
        ]);

        $pembayaran = Pembayaran::where('nomor_pembayaran', $validated['nomor_pembayaran'])->firstOrFail();

        $alreadyDisbursed = TransaksiPencairan::where('nomor_pembayaran', $pembayaran->nomor_pembayaran)->exists();
        if ($alreadyDisbursed || $pembayaran->status_pembayaran === 'lunas') {
            return back()->withErrors(['nomor_pembayaran' => 'Tagihan pembayaran ini sudah dicairkan sebelumnya dan berstatus Lunas.']);
        }

        $filePath = $request->file('file_bukti_transfer')->store('transfer', 'public');

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
            "Keuangan mencairkan dana SP2D {$transaksi->nomor_transaksi_bank} sebesar Rp ".number_format($transaksi->nominal_transfer).' dengan bukti transfer sah. Seluruh siklus pengadaan selesai.'
        );

        $this->notificationService->notifyPencairanSp2d($transaksi);

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Pencairan dana SP2D nomor {$transaksi->nomor_transaksi_bank} berhasil diselesaikan dengan bukti transfer sah. Seluruh siklus pengadaan kini berstatus SELESAI 100%!"
        );
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storePembayaran($request);
    }
}
