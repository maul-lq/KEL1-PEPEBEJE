<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $table = 'units';

    protected $primaryKey = 'kode_unit';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode_unit',
        'nama_unit',
        'jenis_unit',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'kode_unit', 'kode_unit');
    }

    public function permohonanPengadaan(): HasMany
    {
        return $this->hasMany(PermohonanPengadaan::class, 'kode_unit', 'kode_unit');
    }
}
