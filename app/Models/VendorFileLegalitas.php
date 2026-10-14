<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorFileLegalitas extends Model
{
    use HasFactory;

    protected $table = 'vendor_file_legalitas';

    protected $fillable = [
        'npwp',
        'nama_dokumen',
        'file_legalitas',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'npwp', 'npwp');
    }
}
