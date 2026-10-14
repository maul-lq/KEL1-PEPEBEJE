<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaketPengadaan extends Model
{
    use HasFactory;

    protected $table = 'paket_pengadaan';

    protected $primaryKey = 'nomor_paket';

    public $incrementing = false;

    protected $keyType = 'string';

    const ROUTING_LANGSUNG = 'langsung_sd_50jt';

    const ROUTING_PP = 'pp_50jt_sd_200jt';

    const ROUTING_PPK = 'ppk_diatas_200jt';

    protected $fillable = [
        'nomor_paket',
        'nama_paket',
        'jenis_pengadaan',
        'platform_pengadaan',
        'mekanisme_pengadaan',
        'jalur_routing',
        'nilai_hps',
        'alokasi_pagu_paket',
        'file_dokumen_pengadaan',
        'status_paket',
        'id_paket_lkpp',
        'nomor_surat',
        'tanggal_registrasi_paket',
        'nomor_memo_ppbj',
        'id_user_ppbj',
        'id_user_ppk',
        'tanggal_penugasan_ppk',
        'status_penugasan_ppk',
        'alasan_pengalihan_ppk',
        'id_user_pp',
        'tanggal_penugasan_pp',
        'nilai_hasil_negosiasi',
        'catatan_verifikasi_pp',
        'npwp_vendor',
        'tanggal_penunjukan_vendor',
    ];

    protected function casts(): array
    {
        return [
            'nilai_hps' => 'decimal:2',
            'alokasi_pagu_paket' => 'decimal:2',
            'nilai_hasil_negosiasi' => 'decimal:2',
            'tanggal_registrasi_paket' => 'datetime',
            'tanggal_penugasan_ppk' => 'datetime',
            'tanggal_penugasan_pp' => 'datetime',
            'tanggal_penunjukan_vendor' => 'datetime',
        ];
    }

    public static function tentukanRouting(float $nominal): string
    {
        if ($nominal <= 50000000) {
            return self::ROUTING_LANGSUNG;
        } elseif ($nominal <= 200000000) {
            return self::ROUTING_PP;
        } else {
            return self::ROUTING_PPK;
        }
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanPengadaan::class, 'nomor_surat', 'nomor_surat');
    }

    public function ppbjUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_ppbj', 'id_user');
    }

    public function ppkUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_ppk', 'id_user');
    }

    public function ppUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_pp', 'id_user');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'npwp_vendor', 'npwp');
    }

    public function reviuDokumen(): HasMany
    {
        return $this->hasMany(ReviuDokumen::class, 'nomor_paket', 'nomor_paket');
    }

    public function riwayatPpk(): HasMany
    {
        return $this->hasMany(RiwayatPenugasanPPK::class, 'nomor_paket', 'nomor_paket');
    }

    public function kontrakSpk(): HasOne
    {
        return $this->hasOne(KontrakSPK::class, 'nomor_paket', 'nomor_paket');
    }

    public function penerimaanBarang(): HasMany
    {
        return $this->hasMany(PenerimaanBarang::class, 'nomor_paket', 'nomor_paket');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'nomor_paket', 'nomor_paket');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'nomor_paket', 'nomor_paket');
    }

    /**
     * Scope query untuk memfilter paket sesuai wewenang peran dan isolasi berkas 4 akun PPK (NFR RBAC).
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->role === 'ppk') {
            // Isolasi berkas 4 akun PPK: Hanya melihat paket yang ditugaskan ke ID akun PPK bersangkutan
            return $query->where('id_user_ppk', $user->id_user);
        }

        if ($user->role === 'pp') {
            // PP hanya melihat paket yang dialokasikan ke jalur PP (50jt - 200jt) atau ditugaskan kepadanya
            return $query->where(function ($q) use ($user) {
                $q->where('jalur_routing', self::ROUTING_PP)
                    ->orWhere('id_user_pp', $user->id_user);
            });
        }

        if ($user->role === 'user') {
            // Pemohon hanya melihat paket dari permohonan yang diajukannya
            return $query->whereHas('permohonan', function ($q) use ($user) {
                $q->where('id_user', $user->id_user);
            });
        }

        // Role pengawas & manajemen (ppbj, perencanaan, wadir_2, perlengkapan, keuangan, staff_bidang_2) dapat melihat seluruh paket
        return $query;
    }
}
