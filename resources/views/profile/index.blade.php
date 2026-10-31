@extends('layouts.app')

@section('title', 'Profil Saya - SIM-PBJ')

@section('content')

{{-- Sembunyikan ikon mata / hapus bawaan browser (Edge, IE, Safari) agar tidak ada 2 ikon mata.
     CATATAN: sebelumnya memakai @push('styles'), tetapi layouts/app.blade.php tidak punya @stack('styles'),
     sehingga CSS-nya tidak pernah dimuat. Sekarang ditaruh langsung di sini. --}}
<style>
    input::-ms-reveal,
    input::-ms-clear {
        display: none !important;
    }
    input[type="password"]::-webkit-credentials-auto-fill-button,
    input[type="password"]::-webkit-contacts-auto-fill-button {
        visibility: hidden;
        display: none !important;
        pointer-events: none;
    }
</style>

<div x-data="{ 
    editModalOpen: false, 
    passwordModalOpen: {{ session('password_modal_error') ? 'true' : 'false' }}, 
    deleteAccountModalOpen: {{ session('delete_modal_error') ? 'true' : 'false' }} 
}" class="mx-auto max-w-[1750px]">

    {{-- HEADER HALAMAN --}}
    <div>
        <h1 class="text-[40px] font-semibold leading-tight text-black">Profil Saya</h1>
        <p class="mt-2 text-base text-[#4A4A4A]">Lihat dan kelola informasi akun Anda.</p>
    </div>

    {{-- NOTIFIKASI FLASH ALERT --}}
    @if(session('success'))
        <div class="mt-6 flex items-center gap-3 rounded-[10px] border border-emerald-300 bg-emerald-50 px-5 py-3 text-emerald-800">
            <svg class="size-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mt-6 flex items-center gap-3 rounded-[10px] border border-red-300 bg-red-50 px-5 py-3 text-red-800">
            <svg class="size-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif
    @if(session('status'))
        <div class="mt-6 flex items-center gap-3 rounded-[10px] border border-blue-300 bg-blue-50 px-5 py-3 text-blue-800">
            <svg class="size-5 shrink-0 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-sm font-medium">{{ session('status') }}</span>
        </div>
    @endif

    {{-- GRID KONTEN UTAMA --}}
    <div class="mt-10 grid items-start gap-[42px] xl:grid-cols-[1082fr_631fr]">

        {{-- KARTU KIRI: KARTU PROFIL & INFORMASI UMUM --}}
        <div class="rounded-[20px] border border-[rgba(120,120,120,0.5)] bg-white px-6 py-8 sm:px-[38px] sm:pb-10 sm:pt-[35px] shadow-sm">
            
            {{-- HEADER AVATAR & NAMA --}}
            <div class="flex flex-wrap items-center gap-8">
                <div class="relative size-[103px] sm:size-[125px] shrink-0">
                    @if($user->foto_profil)
                        <img src="{{ asset('storage/' . $user->foto_profil) }}?v={{ time() }}" alt="{{ $user->nama }}" class="size-full rounded-full object-cover border border-slate-200" />
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
                        <img src="{{ asset('storage/' . $user->foto_profil) }}?v={{ time() }}" alt="" class="size-full object-cover" />
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

            {{-- KONEKSI AKUN GOOGLE (GOOGLE OAUTH) --}}
            <div class="rounded-[20px] border border-[rgba(120,120,120,0.5)] bg-white px-[34px] py-[30px] shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <svg class="size-8 shrink-0" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <h3 class="text-2xl font-semibold text-black">Akun Google</h3>
                    </div>
                    @if($user->isGoogleLinked())
                        <div class="inline-flex items-center gap-2 rounded-full bg-[#E6F4EA] px-3 py-1 text-sm font-bold text-[#137333]">
                            <span class="size-2 rounded-full bg-[#137333]"></span>
                            Terhubung
                        </div>
                    @else
                        <div class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-500">
                            <span class="size-2 rounded-full bg-gray-400"></span>
                            Belum Terhubung
                        </div>
                    @endif
                </div>

                @if($user->isGoogleLinked())
                    <p class="mt-4 text-[17px] text-[#4A4A4A]">
                        Akun Google Anda telah terhubung. Anda dapat menggunakan akun Google ini untuk masuk ke SIM-PBJ dalam satu klik.
                    </p>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4 rounded-[10px] border border-[#B0CBE6] bg-[#E8F5FD] px-[26px] py-[18px]">
                        <div class="flex items-center gap-4 min-w-0">
                            @if($user->google_avatar)
                                <img src="{{ $user->google_avatar }}" alt="Google Avatar" class="size-10 shrink-0 rounded-full object-cover border border-gray-200">
                            @else
                                <svg class="size-9 shrink-0 text-[#0088E8]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                            @endif
                            <div class="min-w-0">
                                <div class="truncate text-base font-medium text-black">{{ $user->google_email ?? $user->email }}</div>
                                <div class="text-xs text-gray-500">
                                    Ditautkan pada {{ $user->google_linked_at ? $user->google_linked_at->translatedFormat('d M Y, H:i') : 'Sebelumnya' }}
                                </div>
                            </div>
                        </div>

                        <form action="{{ route('profile.google.unlink') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memutuskan tautan akun Google ini? Anda nantinya hanya dapat masuk menggunakan password akun lokal.');" class="shrink-0">
                            @csrf
                            <button type="submit" class="flex h-10 items-center justify-center rounded-[5px] border border-red-200 bg-white px-4 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors">
                                Putuskan Tautan
                            </button>
                        </form>
                    </div>
                @else
                    <p class="mt-4 text-[17px] text-[#4A4A4A]">
                        Tautkan akun Google Anda ke profil SIM-PBJ untuk mengaktifkan fitur masuk cepat (1-Click Google Login) secara aman tanpa perlu memasukkan kata sandi.
                    </p>

                    <a href="{{ route('profile.google.link') }}" class="mt-5 inline-flex h-11 w-full sm:w-auto items-center justify-center gap-3 rounded-[8px] border border-[#D2D2D2] bg-white px-6 text-base font-semibold text-black shadow-sm hover:bg-gray-50 transition-colors">
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        Hubungkan Akun Google
                    </a>
                @endif
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
                    <button @click="deleteAccountModalOpen = true" type="button" class="flex h-[47px] w-[167px] items-center justify-center gap-3 rounded-[10px] border border-[rgba(120,120,120,0.5)] bg-[#FFE4E4] text-base font-semibold text-[#F00] hover:brightness-95 transition-all">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Hapus Akun
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- MODAL EDIT PROFIL (form otomatis kembali ke data asli saat modal ditutup) --}}
    <div x-show="editModalOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
        <form action="{{ route('profile.update') }}" method="POST"
              x-init="$watch('editModalOpen', open => { if (!open) $el.reset(); })"
              @click.away="editModalOpen = false"
              class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
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

    {{-- OVERLAY 1: MODAL UBAH PASSWORD (FIGMA FRAME 68) --}}
    <div x-show="passwordModalOpen" 
         x-cloak 
         x-transition.opacity 
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        
        {{-- x-init + $watch: setiap modal ditutup (tombol X, Batal, atau klik di luar),
             semua isian dikosongkan dan ikon mata kembali ke mode tersembunyi --}}
        <div @click.away="passwordModalOpen = false" 
             x-data="{ showOld: false, showNew: false, showConfirm: false }"
             x-init="$watch('passwordModalOpen', open => {
                 if (!open) {
                     $refs.passwordForm.reset();
                     showOld = false; showNew = false; showConfirm = false;
                 }
             })"
             class="relative w-full max-w-[596px] rounded-[10px] bg-white p-8 sm:p-10 shadow-2xl">
            
            <!-- Tombol Close (X) -->
            <button type="button" @click="passwordModalOpen = false" class="absolute top-6 right-6 text-slate-400 hover:text-black transition-colors">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <!-- Title & Subtitle -->
            <div class="flex items-center gap-3 mb-2">
                <svg class="size-7 text-black shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                <h2 class="text-2xl font-semibold text-black">Ubah Password</h2>
            </div>
            <p class="text-[17px] text-[#494949] mb-8">Masukkan password baru Anda untuk mengganti kata sandi akun.</p>

            <form x-ref="passwordForm" action="{{ route('profile.password.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Field 1: Password Lama -->
                <div>
                    <label class="block text-lg font-medium text-black mb-2">
                        Password Lama <span class="text-[#ff0000]">*</span>
                    </label>
                    <div class="relative h-[60px] w-full rounded-[5px] border border-[#d1d1d1] bg-[#f5f5ff]">
                        <input :type="showOld ? 'text' : 'password'" 
                               name="old_password" 
                               required 
                               placeholder="Masukkan password lama"
                               class="size-full bg-transparent pl-7 pr-12 text-lg text-black placeholder:text-[#aaaaaa] outline-none">
                        <button type="button" @click="showOld = !showOld" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700">
                            <svg x-show="!showOld" class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showOld" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a8.962 8.962 0 013.98-.863c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m-4.692-4.692a3 3 0 00-4.243-4.243"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Field 2: Password Baru -->
                <div>
                    <label class="block text-lg font-medium text-black mb-2">
                        Password Baru <span class="text-[#ff0000]">*</span>
                    </label>
                    <div class="relative h-[60px] w-full rounded-[5px] border border-[#d1d1d1] bg-[#f5f5ff]">
                        <input :type="showNew ? 'text' : 'password'" 
                               name="new_password" 
                               required 
                               minlength="8" 
                               placeholder="Minimal 8 karakter"
                               class="size-full bg-transparent pl-7 pr-12 text-lg text-black placeholder:text-[#aaaaaa] outline-none">
                        <button type="button" @click="showNew = !showNew" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700">
                            <svg x-show="!showNew" class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showNew" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a8.962 8.962 0 013.98-.863c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m-4.692-4.692a3 3 0 00-4.243-4.243"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Field 3: Konfirmasi Password Baru -->
                <div>
                    <label class="block text-lg font-medium text-black mb-2">
                        Konfirmasi Password Baru <span class="text-[#ff0000]">*</span>
                    </label>
                    <div class="relative h-[60px] w-full rounded-[5px] border border-[#d1d1d1] bg-[#f5f5ff]">
                        <input :type="showConfirm ? 'text' : 'password'" 
                               name="confirm_password" 
                               required 
                               placeholder="Ulangi password baru"
                               class="size-full bg-transparent pl-7 pr-12 text-lg text-black placeholder:text-[#aaaaaa] outline-none">
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700">
                            <svg x-show="!showConfirm" class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirm" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a8.962 8.962 0 013.98-.863c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m-4.692-4.692a3 3 0 00-4.243-4.243"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="flex items-center justify-end gap-4 pt-4">
                    <button type="button" @click="passwordModalOpen = false" class="h-12 w-[185px] rounded-[10px] border border-[#3b82f6d9] bg-white text-lg font-medium text-blue-500 hover:bg-blue-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="h-12 w-[248px] rounded-[10px] border border-[#3b82f6d9] bg-blue-500 text-lg font-medium text-white hover:bg-blue-600 transition-colors shadow-sm">
                        Simpan Perubahan
                    </button>
                </div>
            </form>

        </div>
    </div>

    {{-- OVERLAY 2: MODAL HAPUS AKUN (FIGMA FRAME 69) --}}
    <div x-show="deleteAccountModalOpen" 
         x-cloak 
         x-transition.opacity 
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        
        <div @click.away="deleteAccountModalOpen = false" 
             x-data="{ showPass: false }"
             x-init="$watch('deleteAccountModalOpen', open => {
                 if (!open) {
                     $refs.deleteForm.reset();
                     showPass = false;
                 }
             })"
             class="relative w-full max-w-[596px] rounded-[10px] border border-[#e87474] bg-[#fff1f1] p-8 sm:p-10 shadow-2xl">
            
            <!-- Tombol Close (X) -->
            <button type="button" @click="deleteAccountModalOpen = false" class="absolute top-6 right-6 text-[#e87474] hover:text-[#ff0000] transition-colors">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <!-- Title & Subtitle Red -->
            <div class="flex items-center gap-3 mb-2">
                <svg class="size-7 text-[#ff0000] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <h2 class="text-2xl font-semibold text-[#ff0000]">Hapus Akun</h2>
            </div>
            <p class="text-[17px] text-[#e87474] mb-8">Tindakan ini tidak dapat dibatalkan.</p>

            <form x-ref="deleteForm" action="{{ route('profile.destroy') }}" method="POST" class="space-y-6">
                @csrf
                @method('DELETE')

                <!-- Field Konfirmasi Password -->
                <div>
                    <label class="block text-lg font-medium text-black mb-2">
                        Konfirmasi Password <span class="text-[#ff0000]">*</span>
                    </label>
                    <div class="relative h-[60px] w-full rounded-[5px] border border-[#d1d1d1] bg-[#ffe5e5]">
                        <input :type="showPass ? 'text' : 'password'" 
                               name="password_konfirmasi" 
                               required 
                               placeholder="Masukkan password"
                               class="size-full bg-transparent pl-7 pr-12 text-lg text-black placeholder:text-[#aaaaaa] outline-none">
                        <button type="button" @click="showPass = !showPass" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700">
                            <svg x-show="!showPass" class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPass" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a8.962 8.962 0 013.98-.863c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m-4.692-4.692a3 3 0 00-4.243-4.243"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Tombol Aksi Red -->
                <div class="flex items-center justify-end gap-4 pt-4">
                    <button type="button" @click="deleteAccountModalOpen = false" class="h-12 w-[185px] rounded-[10px] border border-[#e87474] bg-white text-lg font-medium text-[#ff0000] hover:bg-red-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="h-12 w-[248px] rounded-[10px] border border-[#ff0000] bg-[#ff0000] text-lg font-medium text-white hover:bg-red-700 transition-colors shadow-sm">
                        Hapus Akun
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection