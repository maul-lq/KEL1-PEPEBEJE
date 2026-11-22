{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : Komponen Blade ini rancang untuk membentuk layout dua kolom berimbang dasbor
              Figma: panel kiri untuk antrean yang memerlukan tindakan dan panel kanan untuk
              aktivitas terbaru atau ringkasan prioritas peran.
--}}
{{--
  Komentar Deskriptif: Komponen Layout Dua Kolom Workspace Dasbor (`<x-figma-workspace>`).

  Tujuan: menyeragamkan kerangka kolom kiri ("Perlu Tindakan Anda") dan kanan ("Aktivitas Terbaru")
          lintas seluruh dasbor peran agar konsistensi visual dan proporsi 12-kolom selalu terjaga.
  Parameter (@props & Slots):
    - `leftTitle`, `leftHref`, `leftLinkLabel`: Judul dan tautan footer header kolom kiri.
    - `rightTitle`, `rightHref`, `rightLinkLabel`: Judul dan tautan header kolom kanan.
    - Slot `$left`, `$right`, dan opsional `$rightHeaderExtra` untuk kontrol tab tambahan (seperti PPBJ).
  Nilai kembali: Grid responsif dua panel setinggi minimal 381px siap isi kartu aksi.
  Contoh pemanggilan:
    `<x-figma-workspace leftTitle="Antrean Telaah" :leftHref="route('verifikasi.bidang_2')">`
--}}
@props([
    'leftTitle' => 'Perlu Tindakan Anda',
    'leftHref' => null,
    'leftLinkLabel' => 'Lihat Semua',
    'rightTitle' => 'Aktivitas Terbaru',
    'rightHref' => null,
    'rightLinkLabel' => 'Lihat Semua',
])

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    {{-- Panel Kolom Kiri: Daftar item prioritas yang menunggu tindak lanjut pengguna --}}
    <div class="lg:col-span-6 bg-white border border-[#d2d2d2] rounded-[5px] p-6 shadow-xs flex flex-col justify-between min-h-[381px]">
        <div>
            <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5 min-w-0">
                    <img src="{{ asset('images/dashboard/icon-action-needed.svg') }}" alt="{{ $leftTitle }}" class="size-[25px] shrink-0">
                    <h3 class="text-[#4a4a4a] text-[18px] font-semibold truncate">{{ $leftTitle }}</h3>
                </div>
                @if($leftHref)
                    <a href="{{ $leftHref }}" class="text-[#3b82f6] text-[15px] md:text-[17px] font-semibold flex items-center gap-1.5 hover:underline group shrink-0">
                        {{ $leftLinkLabel }}
                        <svg class="size-3.5 -rotate-90 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 14.314 8.657" fill="none">
                            <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                        </svg>
                    </a>
                @endif
            </div>

            <div class="mt-4 space-y-3">
                {{ $left }}
            </div>
        </div>
    </div>

    {{-- Panel Kolom Kanan: Aktivitas terbaru atau ringkasan lintas status peran --}}
    <div class="lg:col-span-6 bg-white border border-[#d2d2d2] rounded-[5px] p-6 shadow-xs flex flex-col justify-between min-h-[381px]">
        <div>
            <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5 min-w-0">
                    <img src="{{ asset('images/dashboard/icon-recent-activity.svg') }}" alt="{{ $rightTitle }}" class="size-[22px] shrink-0">
                    <h3 class="text-[#4a4a4a] text-[18px] md:text-[20px] font-semibold truncate">{{ $rightTitle }}</h3>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    {{-- sediakan slot rightHeaderExtra agar dasbor PPBJ dapat menaruh switcher tab di sudut kanan header. --}}
                    @if(isset($rightHeaderExtra))
                        {{ $rightHeaderExtra }}
                    @endif
                    @if($rightHref)
                        <a href="{{ $rightHref }}" class="text-[#3b82f6] text-[15px] md:text-[17px] font-semibold flex items-center gap-1.5 hover:underline group">
                            {{ $rightLinkLabel }}
                            <svg class="size-3.5 -rotate-90 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 14.314 8.657" fill="none">
                                <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>

            <div class="mt-4">
                {{ $right }}
            </div>
        </div>
    </div>
</div>
