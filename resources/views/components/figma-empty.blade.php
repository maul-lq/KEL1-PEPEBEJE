{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : Komponen Blade ini buat untuk menampilkan keadaan kosong (empty state) bergaya
              Figma dengan ikon centang hijau ketika tidak ada antrean kerja yang menumpuk.
--}}
{{--
   Komponen Empty State Antrean Kerja (`<x-figma-empty>`).

  Tujuan: memberi umpan balik positif kepada pengguna saat daftar antrean pada dasbor peran
          telah tuntas tertangani semua agar layar kosong tidak tampak seperti error pemuatan data.
  Parameter (@props):
    - `title` (string): Pesan utama keadaan kosong.
    - `subtitle` (string): Keterangan pelengkap di bawah judul.
  Nilai kembali: Kotak empty state HTML berborder putus-putus.
  Contoh pemanggilan: `<x-figma-empty title="Antrean Verifikasi Kosong" subtitle="Semua berkas sudah diproses." />`
--}}
@props([
    'title' => 'Tidak ada antrean yang memerlukan tindakan Anda',
    'subtitle' => 'Seluruh item kerja saat ini sudah tertangani dengan baik.',
])

<div class="rounded-[5px] border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center my-4">
    <div class="size-10 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <p class="text-sm font-semibold text-slate-700">{{ $title }}</p>
    @if($subtitle)
        <p class="text-xs text-slate-400 mt-0.5">{{ $subtitle }}</p>
    @endif
</div>
