<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $table = 'vendors';

    protected $primaryKey = 'npwp';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'npwp',
        'nib',
        'nama_perusahaan',
        'alamat',
        'is_active',
        'nama_pic',
        'telepon_pic',
        'email_pic',
        'nama_bank',
        'nomor_rekening',
        'atas_nama',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function filesLegalitas(): HasMany
    {
        return $this->hasMany(VendorFileLegalitas::class, 'npwp', 'npwp');
    }

    public function paketPengadaan(): HasMany
    {
        return $this->hasMany(PaketPengadaan::class, 'npwp_vendor', 'npwp');
    }
}
