<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Akun - Sistem PBJ</title>
    
    <!-- Google Fonts: Inder -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inder&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .font-inder {
            font-family: 'Inder', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen w-full bg-[#1E3156] font-inder overflow-x-hidden relative flex flex-col justify-between selection:bg-[#6b4fde] selection:text-white py-10 px-4 sm:px-6 lg:px-8">

    <!-- Background Layer Subtle Shapes -->
    <div class="fixed inset-0 w-full h-full pointer-events-none overflow-hidden z-0">
        <div class="absolute -right-32 -top-32 w-96 h-96 rounded-full bg-[#6b4fde]/10 blur-3xl"></div>
        <div class="absolute -left-32 -bottom-32 w-96 h-96 rounded-full bg-[#b3e0ff]/10 blur-3xl"></div>
    </div>

    <!-- Main Container -->
    <main class="relative z-10 w-full max-w-4xl mx-auto my-auto flex flex-col items-center">
        
        <!-- Header Title -->
        <div class="mb-6 text-center select-none">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-normal leading-tight text-[#b3e0ff] tracking-tight drop-shadow-[0_4px_6px_rgba(0,0,0,0.35)]">
                Daftar Akun Baru
            </h1>
            <p class="text-slate-300 text-sm sm:text-base mt-2 max-w-lg mx-auto">
                Sistem Informasi Manajemen Pengadaan Barang dan Jasa (SIM-PBJ) Politeknik Negeri Jakarta
            </p>
        </div>

        <!-- Registration Card -->
        <div class="w-full bg-[#b0cbe6] rounded-[32px] sm:rounded-[40px] px-6 py-8 sm:px-10 sm:py-10 shadow-[0_16px_32px_rgba(0,0,0,0.3)] backdrop-blur-sm">
            
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-[#6b4fde]/20">
                <div>
                    <h2 class="text-[#6b4fde] text-2xl font-bold">
                        Formulir Registrasi Pengguna
                    </h2>
                    <p class="text-xs text-[#1E3156]/80 mt-1">
                        Lengkapi profil pegawai Anda untuk mengakses pengajuan & verifikasi PBJ
                    </p>
                </div>
                <a 
                    href="{{ route('login') }}" 
                    class="text-xs sm:text-sm font-semibold text-[#6b4fde] hover:text-[#583fc4] bg-white/70 hover:bg-white px-3 py-1.5 rounded-full transition shadow-sm"
                >
                    &larr; Sudah ada akun? Login
                </a>
            </div>

            <!-- Error Notifications -->
            @if ($errors->any())
                <div class="w-full mb-6 px-4 py-3 bg-rose-50 border border-rose-300 rounded-2xl text-rose-800 text-sm shadow-sm">
                    <div class="flex items-center gap-2 font-semibold mb-1">
                        <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>Pendaftaran Belum Dapat Diproses:</span>
                    </div>
                    <ul class="list-disc list-inside text-xs space-y-0.5 pl-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Registration Form -->
            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                    
                    <!-- NIP -->
                    <div>
                        <label for="nip" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            NIP Pegawai <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="text" 
                                id="nip" 
                                name="nip" 
                                value="{{ old('nip') }}" 
                                required 
                                placeholder="Contoh: 198501012010121001"
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                    <!-- Username -->
                    <div>
                        <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Username Unik <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                value="{{ old('username') }}" 
                                required 
                                placeholder="Contoh: maulana_tik"
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                    <!-- Nama Lengkap -->
                    <div class="md:col-span-2">
                        <label for="nama" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Nama Lengkap & Gelar <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="text" 
                                id="nama" 
                                name="nama" 
                                value="{{ old('nama') }}" 
                                required 
                                placeholder="Contoh: Dr. Maulana Ibrahim, S.T., M.Kom."
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                    <!-- Email Kampus -->
                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Alamat Email Kampus / Resmi <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="{{ old('email') }}" 
                                required 
                                placeholder="nama@pnj.ac.id"
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                    <!-- Unit Kerja (Database Dropdown) -->
                    <div>
                        <label for="kode_unit" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Unit Kerja / Jurusan (Tersinkron DB) <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-3 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <select 
                                id="kode_unit" 
                                name="kode_unit" 
                                required
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] text-sm cursor-pointer"
                            >
                                <option value="" disabled {{ old('kode_unit') ? '' : 'selected' }}>-- Pilih Unit / Jurusan --</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->kode_unit }}" {{ old('kode_unit') === $unit->kode_unit ? 'selected' : '' }}>
                                        [{{ $unit->kode_unit }}] {{ $unit->nama_unit }} ({{ ucfirst($unit->tipe_unit) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Jabatan -->
                    <div>
                        <label for="jabatan" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Jabatan Kedinasan <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="text" 
                                id="jabatan" 
                                name="jabatan" 
                                value="{{ old('jabatan') }}" 
                                required 
                                placeholder="Contoh: Kepala Laboratorium Jaringan"
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                    <!-- Role / Hak Akses -->
                    <div>
                        <label for="role" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Peran Akun Sistem PBJ <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-3 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <select 
                                id="role" 
                                name="role" 
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] text-sm cursor-pointer"
                            >
                                <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>Pemohon / Pengaju Pengadaan (User)</option>
                                <option value="staff_bidang_2" {{ old('role') === 'staff_bidang_2' ? 'selected' : '' }}>Staff Bidang 2</option>
                                <option value="wadir_2" {{ old('role') === 'wadir_2' ? 'selected' : '' }}>Wakil Direktur 2 (Wadir 2)</option>
                                <option value="perencanaan" {{ old('role') === 'perencanaan' ? 'selected' : '' }}>Tim Perencanaan (Alokasi MAK)</option>
                                <option value="ppbj" {{ old('role') === 'ppbj' ? 'selected' : '' }}>Koordinator PPBJ (Review & Routing)</option>
                                <option value="pp" {{ old('role') === 'pp' ? 'selected' : '' }}>Pejabat Pengadaan (PP 50jt - 200jt)</option>
                                <option value="ppk" {{ old('role') === 'ppk' ? 'selected' : '' }}>PPK (Pejabat Pembuat Komitmen > 200jt)</option>
                                <option value="perlengkapan" {{ old('role') === 'perlengkapan' ? 'selected' : '' }}>Staff Perlengkapan (Gudang/BAST)</option>
                                <option value="keuangan" {{ old('role') === 'keuangan' ? 'selected' : '' }}>Bagian Keuangan (Pencairan & SP2D)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Nomor HP / WhatsApp -->
                    <div>
                        <label for="no_hp" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Nomor HP / WhatsApp <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="text" 
                                id="no_hp" 
                                name="no_hp" 
                                value="{{ old('no_hp') }}" 
                                required 
                                placeholder="Contoh: 081234567890"
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                    <!-- Nomor Telepon Kantor (Opsional) -->
                    <div>
                        <label for="no_telp_kantor" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Nomor Telepon Kantor (Opsional)
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="text" 
                                id="no_telp_kantor" 
                                name="no_telp_kantor" 
                                value="{{ old('no_telp_kantor') }}" 
                                placeholder="Contoh: 021-7270036 ext 102"
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Password Akun <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required 
                                placeholder="Minimal 6 karakter"
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                    <!-- Konfirmasi Password -->
                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-[#1E3156] mb-1.5">
                            Konfirmasi Password <span class="text-rose-600">*</span>
                        </label>
                        <div class="bg-white rounded-2xl h-[52px] px-4 flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50">
                            <input 
                                type="password" 
                                id="password_confirmation" 
                                name="password_confirmation" 
                                required 
                                placeholder="Ulangi password di atas"
                                class="w-full bg-transparent border-none outline-none text-[#1E3156] placeholder-[#6b4fde]/60 text-sm"
                            >
                        </div>
                    </div>

                </div>

                <!-- Submit Button & Action -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-[#6b4fde]/20">
                    <p class="text-xs text-[#1E3156]/80 text-center sm:text-left">
                        Dengan mendaftar, data akun Anda akan diverifikasi dan disesuaikan dengan wewenang unit kerja.
                    </p>
                    <button 
                        type="submit"
                        class="bg-[#6b4fde] hover:bg-[#583fc4] active:scale-95 transition-all text-white font-semibold text-base rounded-2xl h-[50px] px-8 flex items-center justify-center shadow-lg hover:shadow-indigo-500/30 shrink-0 w-full sm:w-auto cursor-pointer"
                    >
                        Daftarkan Akun
                    </button>
                </div>

            </form>

        </div>

    </main>

    <!-- Footer Copyright -->
    <footer class="relative z-10 text-center text-xs text-slate-400 mt-8">
        &copy; {{ date('Y') }} Tim Pengembang SIM-PBJ Politeknik Negeri Jakarta. Seluruh Hak Cipta Dilindungi.
    </footer>

</body>
</html>
