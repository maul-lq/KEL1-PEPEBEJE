@extends('layouts.app')

@section('title', 'Profil Saya - SIM-PBJ')

@section('content')
<div x-data="{ editModalOpen: false, passwordModalOpen: false }" class="mx-auto max-w-[1750px]">

    {{-- HEADER HALAMAN --}}
    <div>
        <h1 class="text-[40px] font-semibold leading-tight text-black">Profil Saya</h1>
        <p class="mt-2 text-base text-[#4A4A4A]">Lihat dan kelola informasi akun Anda.</p>
    </div>

    {{-- GRID KONTEN UTAMA --}}
    <div class="mt-10 grid items-start gap-[42px] xl:grid-cols-[1082fr_631fr]">

        {{-- KARTU KIRI: KARTU PROFIL & INFORMASI UMUM --}}
        <div class="rounded-[20px] border border-[rgba(120,120,120,0.5)] bg-white px-6 py-8 sm:px-[38px] sm:pb-10 sm:pt-[35px] shadow-sm">
            
            {{-- HEADER AVATAR & NAMA --}}
            <div class="flex flex-wrap items-center gap-8">
                <div class="relative size-[103px] sm:size-[125px] shrink-0">
                    @if($user->foto_profil)
                        <img src="{{ asset('storage/' . $user->foto_profil) }}" alt="{{ $user->nama }}" class="size-full rounded-full object-cover border border-slate-200" />
                    @else
                        <div class="flex size-full items-center justify-center rounded-full bg-[#0088E8] text-4xl font-bold text-white shadow-inner">
                            {{ strtoupper(substr($user->nama ?? 'U', 0, 1)) }}
                        </div>
                    @endif
                </div>
                <div>
                    <h2 class="text-[32px] sm:text-[40px] font-semibold leading-tight text-black">{{ $user->nama }}</h2>
                    <p class="mt-1 text-lg font-medium text-black">{{ $user->username ?? strtolower(str_replace(' ', '', $user->nama)) }}</p>
                    <span class="mt-1 inline-block rounded-[10px] bg-[#B0CBE6] px-[13px] py-[3px] text-[15px] font-medium text-[#0969DA] uppercase">
                        {{ str_replace('_', ' ', $user->role) }}
                    </span>
                </div>
            </div>

            <hr class="my-[33px] border-[rgba(170,170,170,0.5)]" />

            {{-- INFORMASI UMUM --}}
            <div class="flex items-center gap-[18px] text-2xl font-medium text-[#3F3F3F]">
                <svg class="size-7" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
                Informasi Umum
            </div>

            <div class="mt-[30px] grid gap-x-[95px] gap-y-[25px] md:grid-cols-2">
                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">Nama Lengkap</span>
                    <input readOnly value="{{ $user->nama }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">Username</span>
                    <input readOnly value="{{ $user->username ?? '-' }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">NIP</span>
                    <input readOnly value="{{ $user->nip ?? '-' }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">Nomor HP / WhatsApp</span>
                    <input readOnly value="{{ $user->no_hp ?? '-' }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">Email</span>
                    <input readOnly value="{{ $user->email }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">Nomor Telepon Kantor</span>
                    <input readOnly value="{{ $user->no_telepon_kantor ?? '-' }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">Unit Kerja / Jurusan</span>
                    <input readOnly value="{{ $user->unit->nama_unit ?? $user->kode_unit ?? '-' }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">Gelar</span>
                    <input readOnly value="{{ $user->gelar ?? '-' }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-[15px] font-medium text-black">Jabatan</span>
                    <input readOnly value="{{ $user->jabatan ?? '-' }}" class="h-[34px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[14px] text-[13px] text-[#4A4A4A] outline-none" />
                </label>

                {{-- TOMBOL EDIT PROFIL DENGAN IKON FIGMA --}}
                <div class="flex items-end justify-end md:col-start-2">
                    <button @click="editModalOpen = true" type="button" class="flex h-11 items-center gap-2.5 rounded-[6px] border border-[#B0CBE6] bg-[#E8F5FD] px-[18px] text-[15px] font-semibold text-[#3B82F6] hover:bg-[#dbeffd] transition-all shadow-sm">
                        <svg class="size-5 shrink-0 text-[#3B82F6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                        </svg>
                        Edit Profil
                    </button>
                </div>
            </div>

            <hr class="my-[28px] border-[rgba(170,170,170,0.5)]" />

            {{-- FOTO PROFIL UPLOAD --}}
            <div class="flex items-center gap-[19px] text-base font-medium text-black">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Foto Profil
            </div>
            
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="mt-4 flex items-start gap-8" x-data="{ photoName: '' }">
                @csrf
                @method('PUT')
                <input type="hidden" name="nama" value="{{ $user->nama }}">
                <input type="hidden" name="email" value="{{ $user->email }}">

                <div class="size-[58px] shrink-0 rounded-full overflow-hidden bg-slate-100 border border-slate-200">
                    @if($user->foto_profil)
                        <img src="{{ asset('storage/' . $user->foto_profil) }}" alt="" class="size-full object-cover" />
                    @else
                        <div class="flex size-full items-center justify-center bg-[#0088E8] text-xl font-bold text-white">
                            {{ strtoupper(substr($user->nama ?? 'U', 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div>
                    <p class="text-xs font-medium text-[#AAA]">JPG, PNG (Maks. 2MB)</p>
                    <div class="mt-[10px] flex items-center gap-4">
                        <label class="flex h-[30px] cursor-pointer items-center gap-2 rounded-[5px] border border-[#B0CBE6] bg-white px-3 text-xs font-bold text-[#3B82F6] hover:bg-[#E8F5FD] transition-colors">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span x-text="photoName ? photoName : 'Ubah Foto'"></span>
                            <input type="file" name="foto_profil" accept="image/*" @change="photoName = $event.target.files[0]?.name; $nextTick(() => $el.form.submit())" class="sr-only" />
                        </label>
                        
                        @if($user->foto_profil)
                            <button type="submit" name="hapus_foto" value="1" class="flex size-[30px] items-center justify-center rounded-[5px] border border-[#B0CBE6] bg-white text-[#F00] hover:bg-red-50 transition-colors" title="Hapus foto">
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- KARTU KANAN: REMINDER GMAIL, UBAH PASSWORD & HAPUS AKUN --}}
        <div class="flex flex-col gap-[39px]">

            {{-- REMINDER GMAIL --}}
            <div class="rounded-[20px] border border-[rgba(120,120,120,0.5)] bg-white px-[34px] pb-[26px] pt-[28px] shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <svg class="size-9 shrink-0 text-black" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <h3 class="text-2xl font-semibold text-black">Reminder Gmail</h3>
                    </div>
                    
                    {{-- TOGGLE REMINDER --}}
<div x-data="{ enabled: true }">
    <button
        type="button"
        role="switch"
        :aria-checked="enabled.toString()"
        @click="enabled = !enabled"
        :class="enabled ? 'bg-[#0088E8]' : 'bg-gray-300'"
        class="relative inline-block h-6 w-12 shrink-0 cursor-pointer overflow-hidden rounded-full transition-colors duration-200 ease-in-out focus:outline-none"
    >
        <span
            class="absolute top-0.5 left-0.5 block h-5 w-5 rounded-full bg-white shadow-md transition-transform duration-200 ease-in-out"
            :style="enabled ? 'transform: translateX(24px)' : 'transform: translateX(0)'"
        ></span>
    </button>
</div>
                </div>
                <p class="mt-6 text-[17px] text-[#4A4A4A]">Aktifkan reminder melalui Gmail untuk mendapatkan notifikasi jadwal &amp; status pengadaan.</p>
                <div class="mt-5 flex items-center gap-4 rounded-[10px] border border-[#B0CBE6] bg-[#E8F5FD] px-[26px] py-[18px]">
                    <svg class="size-8 shrink-0 text-black" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <div class="min-w-0">
                        <a href="mailto:{{ $user->email }}" class="block truncate text-base text-black font-medium">{{ $user->email }}</a>
                        <div class="flex items-center gap-2 text-sm text-[#087F79] font-bold">
                            <span class="size-2 rounded-full bg-[#087F79]"></span> Terhubung
                        </div>
                    </div>
                </div>
            </div>

            {{-- UBAH PASSWORD --}}
            <div class="rounded-[20px] border border-[rgba(120,120,120,0.5)] bg-white px-[34px] py-[30px] shadow-sm flex gap-[30px]">
                <svg class="size-8 text-black mt-1 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                <div>
                    <h3 class="text-2xl font-semibold text-black">Ubah Password</h3>
                    <p class="mt-2 text-[17px] text-[#4A4A4A]">Pastikan password Anda aman dan sulit ditebak.</p>
                    <button @click="passwordModalOpen = true" type="button" class="mt-5 flex h-10 w-[245px] items-center justify-between rounded-[5px] border border-[#B0CBE6] bg-white px-4 text-base font-semibold text-[#3B82F6] hover:bg-[#E8F5FD] transition-colors">
                        Ubah Password
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- HAPUS AKUN --}}
            <div class="rounded-[20px] border border-[rgba(120,120,120,0.5)] bg-[#FFF2F2] px-[34px] py-[30px] shadow-sm flex flex-wrap items-center gap-x-[30px] gap-y-4">
                <svg class="size-8 text-[#F00] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <div class="flex min-w-0 flex-1 flex-wrap items-end justify-between gap-4">
                    <div>
                        <h3 class="text-2xl font-semibold text-[#F00]">Hapus Akun</h3>
                        <p class="mt-2 text-[17px] text-[#E97474]">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <button type="button" onclick="confirm('Apakah Anda yakin ingin menghapus akun? Permintaan ini akan diteruskan ke Administrator.')" class="flex h-[47px] w-[167px] items-center justify-center gap-3 rounded-[10px] border border-[rgba(120,120,120,0.5)] bg-[#FFE4E4] text-base font-semibold text-[#F00] hover:brightness-95 transition-all">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Hapus Akun
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- MODAL EDIT PROFIL --}}
    <div x-show="editModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
        <form action="{{ route('profile.update') }}" method="POST" @click.away="editModalOpen = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
            @csrf
            @method('PUT')
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="text-xl font-bold text-gray-800">Edit Profil</h3>
                <button type="button" @click="editModalOpen = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="mt-4 space-y-4 text-left">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                    <input type="text" name="nama" value="{{ $user->nama }}" required class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm outline-none focus:ring-2 focus:ring-[#0088E8]" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="{{ $user->email }}" required class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm outline-none focus:ring-2 focus:ring-[#0088E8]" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">NIP</label>
                    <input type="text" name="nip" value="{{ $user->nip }}" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm outline-none focus:ring-2 focus:ring-[#0088E8]" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nomor HP / WhatsApp</label>
                    <input type="text" name="no_hp" value="{{ $user->no_hp }}" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm outline-none focus:ring-2 focus:ring-[#0088E8]" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Jabatan</label>
                    <input type="text" name="jabatan" value="{{ $user->jabatan }}" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm outline-none focus:ring-2 focus:ring-[#0088E8]" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                <button type="button" @click="editModalOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-md bg-[#0088E8] px-4 py-2 text-sm font-medium text-white hover:brightness-95">Simpan Perubahan</button>
            </div>
        </form>
    </div>

</div>
@endsection