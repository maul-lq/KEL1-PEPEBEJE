<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'nama_perusahaan',
        'npwp',
        'nib',
        'alamat',
        'nama_kontak',
        'telepon',
        'email',
        'nama_bank',
        'nomor_rekening',
        'nama_rekening',
        'file_legalitas',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function pengadaans()
    {
        return $this->hasMany(Pengadaan::class);
    }
}
