<!DOCTYPE html>
<html lang="id" class="h-full bg-zinc-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Sistem PBJ Kampus</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col justify-center py-10 sm:px-6 lg:px-8 bg-zinc-100 text-zinc-900">
    <div class="sm:mx-auto sm:w-full sm:max-w-2xl text-center px-4">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-zinc-900 text-white font-black text-2xl mb-4 shadow-sm">
            PBJ
        </div>
        <h2 class="text-3xl font-extrabold text-zinc-900 tracking-tight">Sistem Pengadaan Barang & Jasa</h2>
        <p class="mt-2 text-base text-zinc-600 font-medium">Portal Terpadu Pengelolaan Pengadaan Kampus Politeknik</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-4xl px-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
            
            <!-- FORM LOGIN NORMAL -->
            <div class="md:col-span-6 bg-white py-8 px-6 shadow-sm rounded-xl border border-zinc-300 sm:px-8">
                <h3 class="text-xl font-bold text-zinc-900 border-b border-zinc-200 pb-3 mb-6">Masuk dengan Akun</h3>
                
                @if ($errors->any())
                    <div class="mb-4 p-3 rounded-lg bg-zinc-900 text-zinc-100 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form class="space-y-5" action="{{ route('login.post') }}" method="POST">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-bold text-zinc-800 mb-1">Alamat Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" 
                            class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 text-base"
                            placeholder="nama@kampus.ac.id">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-bold text-zinc-800 mb-1">Kata Sandi</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required 
                            class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 text-base"
                            placeholder="••••••••">
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox" class="h-5 w-5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900">
                            <label for="remember" class="ml-2 block text-sm text-zinc-700 font-medium">Ingat Saya</label>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-lg shadow-sm text-base font-bold text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none cursor-pointer">
                            Masuk ke Sistem
                        </button>
                    </div>
                </form>
            </div>

            <!-- DEMO ROLE SWITCHER (1-KLIK) -->
            <div class="md:col-span-6 bg-zinc-50 py-8 px-6 shadow-sm rounded-xl border border-zinc-300 sm:px-8">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-3 mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-zinc-900">Demo Cepat (7 Role)</h3>
                        <p class="text-xs text-zinc-500">Klik salah satu role untuk langsung masuk tanpa sandi</p>
                    </div>
                    <span class="bg-zinc-900 text-white text-xs font-bold px-2 py-1 rounded">Prototipe</span>
                </div>

                <div class="space-y-2.5">
                    <a href="{{ route('quick-login', 'user') }}" class="block p-3 rounded-lg border border-zinc-200 bg-white hover:border-zinc-900 hover:shadow-xs transition group">
                        <div class="font-bold text-sm text-zinc-900 group-hover:underline">1. User (Jurusan / Unit Pemohon)</div>
                        <div class="text-xs text-zinc-500">Dr. Ir. Budi Santoso (Kajur TIK) • Pembuat Surat Permohonan</div>
                    </a>

                    <a href="{{ route('quick-login', 'wadir2') }}" class="block p-3 rounded-lg border border-zinc-200 bg-white hover:border-zinc-900 hover:shadow-xs transition group">
                        <div class="font-bold text-sm text-zinc-900 group-hover:underline">2. Wadir 2 (Pimpinan / Kebijakan)</div>
                        <div class="text-xs text-zinc-500">Prof. Dr. Ahmad Dahlan • Persetujuan Permohonan & Memo Bayar</div>
                    </a>

                    <a href="{{ route('quick-login', 'perencanaan') }}" class="block p-3 rounded-lg border border-zinc-200 bg-white hover:border-zinc-900 hover:shadow-xs transition group">
                        <div class="font-bold text-sm text-zinc-900 group-hover:underline">3. Perencanaan (Anggaran & MAK)</div>
                        <div class="text-xs text-zinc-500">Dra. Siti Aminah • Penetapan Nomor MAK & Pagu Anggaran (SIGAP)</div>
                    </a>

                    <a href="{{ route('quick-login', 'ppbj') }}" class="block p-3 rounded-lg border border-zinc-200 bg-white hover:border-zinc-900 hover:shadow-xs transition group">
                        <div class="font-bold text-sm text-zinc-900 group-hover:underline">4. PPBJ (Pengelola Pengadaan)</div>
                        <div class="text-xs text-zinc-500">Rahmat Hidayat, S.T. • Registrasi, SPK, Vendor, & Terbit SPP</div>
                    </a>

                    <a href="{{ route('quick-login', 'ppk_pp') }}" class="block p-3 rounded-lg border border-zinc-200 bg-white hover:border-zinc-900 hover:shadow-xs transition group">
                        <div class="font-bold text-sm text-zinc-900 group-hover:underline">5. PPK / Pejabat Pengadaan</div>
                        <div class="text-xs text-zinc-500">Ir. Hendra Gunawan • Reviu Dokumen & Persetujuan SPK / Bayar</div>
                    </a>

                    <a href="{{ route('quick-login', 'perlengkapan') }}" class="block p-3 rounded-lg border border-zinc-200 bg-white hover:border-zinc-900 hover:shadow-xs transition group">
                        <div class="font-bold text-sm text-zinc-900 group-hover:underline">6. Perlengkapan (BMN & Penerimaan)</div>
                        <div class="text-xs text-zinc-500">Agus Setiawan, S.Sos. • Terima Barang, Foto & TTD Digital BAST</div>
                    </a>

                    <a href="{{ route('quick-login', 'keuangan') }}" class="block p-3 rounded-lg border border-zinc-200 bg-white hover:border-zinc-900 hover:shadow-xs transition group">
                        <div class="font-bold text-sm text-zinc-900 group-hover:underline">7. Keuangan (Pembayaran & Pencairan)</div>
                        <div class="text-xs text-zinc-500">Maya Indrawati, S.E. • Pencatatan Transaksi & Bukti Transfer Selesai</div>
                    </a>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
