<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun Baru - SIM-PBJ</title>

    <!-- Dynamic Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- CDN Tailwind & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-white">

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-white p-4 font-sans sm:p-8" x-data="{ showPassword: false, showPasswordConfirm: false }">
        <!-- Background Image -->
        <img
            src="https://api.builder.io/api/v1/image/assets/TEMP/a6a346ae9082d34ec87b3f43d83776d06c5ee139?width=3840"
            alt=""
            class="absolute inset-0 h-full w-full object-cover blur-[1px]"
        />
        <div class="absolute inset-0 bg-white/[0.68]"></div>
        <div class="pointer-events-none absolute -left-40 -top-40 h-[420px] w-[420px] rounded-full bg-[#3B82F6]/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-48 -right-40 h-[480px] w-[480px] rounded-full bg-[#3B82F6]/30 blur-3xl"></div>

        <!-- Main Card Container -->
        <div class="relative z-10 flex w-full max-w-[1356px] flex-col overflow-hidden rounded-[10px] shadow-[0_0_25px_rgba(0,0,0,0.25)] lg:flex-row">
            
            <!-- Sidebar / Left Panel -->
            <aside class="flex flex-col items-start justify-between gap-10 bg-[radial-gradient(203%_267%_at_187%_-91%,rgba(35,76,144,0.85)_0%,rgba(59,130,246,0.85)_100%)] p-8 text-white lg:w-[485px] lg:shrink-0 lg:px-[52px] lg:pb-[16px] lg:pt-[95px]">
                <div class="flex w-full flex-col">
                    <div class="flex items-center gap-3">
                        <img
                            src="{{ asset('favicon.ico') }}"
                            alt="Logo SIM-PBJ"
                            class="size-[77px] shrink-0 object-contain"
                        />
                        <span class="text-[28px] font-medium">SIM-PBJ</span>
                    </div>
                    <h2 class="mt-[43px] max-w-[382px] text-[30px] font-medium leading-snug">
                        Aplikasi administratif Politeknik Negeri Jakarta
                    </h2>
                    <p class="mt-6 max-w-[382px] text-lg font-medium leading-[30px]">
                        Pemantauan proses peminjaman barang yang berlangsung di sistem PNJ
                    </p>
                </div>
                <p class="w-full text-center text-xs font-medium leading-[30px] text-white/50 lg:mt-[200px]">
                    &copy; 2024 SIM-PBJ. All rights reserved.
                </p>
            </aside>

            <!-- Main Form Content -->
            <main class="flex-1 bg-white/[0.92] p-6 sm:p-10 lg:px-[67px] lg:py-[42px]">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                    <h1 class="text-4xl font-semibold text-black">Buat Akun Baru</h1>
                    <p class="text-lg font-medium text-[#AAA] sm:pt-1 sm:text-right">
                        Sudah punya akun? <a href="{{ url('/login') }}" class="text-[#3B82F6] hover:underline">Masuk</a>
                    </p>
                </div>
                <p class="mt-2 text-lg font-medium text-[#AAA]">
                    Lengkapi data diri dan informasi kedinasan Anda
                </p>

                <form
                    action="{{ route('register') }}"
                    method="POST"
                    class="mt-[40px] grid grid-cols-1 gap-x-6 gap-y-[18px] sm:grid-cols-2"
                >
                    @csrf

                    <!-- NIP Pegawai -->
                    <label class="flex flex-col gap-[13px]">
                        <span class="text-lg font-medium text-black">
                            NIP Pegawai <span class="text-red-600">*</span>
                        </span>
                        <input
                            type="text"
                            name="nip"
                            value="{{ old('nip') }}"
                            placeholder="Masukkan NIP"
                            required
                            class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[22px] text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                        />
                    </label>

                    <!-- Username -->
                    <label class="flex flex-col gap-[13px]">
                        <span class="text-lg font-medium text-black">
                            Username <span class="text-red-600">*</span>
                        </span>
                        <input
                            type="text"
                            name="username"
                            value="{{ old('username') }}"
                            placeholder="Buat username unik"
                            required
                            class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[22px] text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                        />
                    </label>

                    <!-- Nama Lengkap & Gelar -->
                    <label class="flex flex-col gap-[13px] sm:col-span-2">
                        <span class="text-lg font-medium text-black">
                            Nama Lengkap & Gelar <span class="text-red-600">*</span>
                        </span>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="Contoh: Dr. Budi Santoso, M.T."
                            required
                            class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[22px] text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                        />
                    </label>

                    <!-- Email Kampus / Resmi -->
                    <label class="flex flex-col gap-[13px] sm:col-span-2">
                        <span class="text-lg font-medium text-black">
                            Email Kampus / Resmi
                        </span>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="nama@instansi.ac.id"
                            class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[22px] text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                        />
                    </label>

                    <!-- Unit Kerja / Jurusan -->
                    <label class="flex flex-col gap-[13px]">
                        <span class="text-lg font-medium text-black">
                            Unit Kerja / Jurusan <span class="text-red-600">*</span>
                        </span>
                        <input
                            type="text"
                            name="unit_kerja"
                            value="{{ old('unit_kerja') }}"
                            placeholder="Pilih unit kerja"
                            required
                            class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[22px] text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                        />
                    </label>

                    <!-- Jabatan Kedinasan -->
                    <label class="flex flex-col gap-[13px]">
                        <span class="text-lg font-medium text-black">
                            Jabatan Kedinasan <span class="text-red-600">*</span>
                        </span>
                        <input
                            type="text"
                            name="jabatan"
                            value="{{ old('jabatan') }}"
                            placeholder="Pilih jabatan"
                            required
                            class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[22px] text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                        />
                    </label>

                    <!-- Nomor HP / WhatsApp -->
                    <label class="flex flex-col gap-[13px]">
                        <span class="text-lg font-medium text-black">
                            Nomor HP / WhatsApp <span class="text-red-600">*</span>
                        </span>
                        <input
                            type="tel"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="08xxxxxxxxxxx"
                            required
                            class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[22px] text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                        />
                    </label>

                    <!-- Telepon Kantor -->
                    <label class="flex flex-col gap-[13px]">
                        <span class="text-lg font-medium text-black">
                            Telepon Kantor <span class="text-[15px] text-gray-500">(Opsional)</span>
                        </span>
                        <input
                            type="tel"
                            name="office_phone"
                            value="{{ old('office_phone') }}"
                            placeholder="021-xxxxxxxxx"
                            class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] px-[22px] text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                        />
                    </label>

                    <!-- Password -->
                    <label class="flex flex-col gap-[13px]">
                        <span class="text-lg font-medium text-black">
                            Password <span class="text-red-600">*</span>
                        </span>
                        <div class="relative flex items-center">
                            <input
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Minimal 8 karakter"
                                required
                                class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] pl-[22px] pr-14 text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6] [&::-ms-reveal]:hidden"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                class="absolute right-[22px] flex items-center justify-center text-[#787878] hover:text-black transition-colors"
                            >
                                <template x-if="showPassword">
                                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .698 10.747 10.747 0 0 1-5.003 5.487"/>
                                        <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
                                        <path d="M17.479 17.499A10.75 10.75 0 0 1 12 19c-4.664 0-8.8-2.903-10.938-6.65a1 1 0 0 1 0-.7 10.733 10.733 0 0 1 3.528-4.238"/>
                                        <line x1="2" x2="22" y1="2" y2="22"/>
                                    </svg>
                                </template>
                                <template x-if="!showPassword">
                                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </template>
                            </button>
                        </div>
                    </label>

                    <!-- Konfirmasi Password -->
                    <label class="flex flex-col gap-[13px]">
                        <span class="text-lg font-medium text-black">
                            Konfirmasi Password <span class="text-red-600">*</span>
                        </span>
                        <div class="relative flex items-center">
                            <input
                                name="password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                placeholder="Ulangi password"
                                required
                                class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] pl-[22px] pr-14 text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6] [&::-ms-reveal]:hidden"
                            />
                            <button
                                type="button"
                                @click="showPasswordConfirm = !showPasswordConfirm"
                                :aria-label="showPasswordConfirm ? 'Sembunyikan password' : 'Tampilkan password'"
                                class="absolute right-[22px] flex items-center justify-center text-[#787878] hover:text-black transition-colors"
                            >
                                <template x-if="showPasswordConfirm">
                                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .698 10.747 10.747 0 0 1-5.003 5.487"/>
                                        <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
                                        <path d="M17.479 17.499A10.75 10.75 0 0 1 12 19c-4.664 0-8.8-2.903-10.938-6.65a1 1 0 0 1 0-.7 10.733 10.733 0 0 1 3.528-4.238"/>
                                        <line x1="2" x2="22" y1="2" y2="22"/>
                                    </svg>
                                </template>
                                <template x-if="!showPasswordConfirm">
                                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </template>
                            </button>
                        </div>
                    </label>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="mt-[22px] h-[60px] rounded-[5px] bg-[#3B82F6] text-lg font-semibold text-white hover:bg-[#2f6fd8] transition-colors sm:col-span-2"
                    >
                        Daftar Akun &rarr;
                    </button>
                </form>
            </main>
        </div>
    </div>

</body>
</html>