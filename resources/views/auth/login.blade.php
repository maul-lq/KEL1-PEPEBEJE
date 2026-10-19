<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - SIM-PBJ Politeknik Negeri Jakarta</title>

    <!-- Dynamic Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- CDN Tailwind & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-white">

    <div class="relative flex min-h-screen w-full flex-col items-center justify-center overflow-x-hidden p-4 sm:p-8" x-data="{ showPassword: false, remember: false }">
        <!-- Background Image -->
        <img
            src="https://api.builder.io/api/v1/image/assets/TEMP/a6a346ae9082d34ec87b3f43d83776d06c5ee139?width=3840"
            alt=""
            class="fixed inset-0 h-full w-full object-cover blur-[1px]"
        />
        <div class="fixed inset-0 bg-white/90 opacity-75"></div>

        <!-- Decorative Top-Left SVG -->
        <svg
            class="pointer-events-none fixed left-0 top-0 hidden w-[36vw] max-w-[704px] sm:block"
            viewBox="0 0 704 576"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M354.276 0.00585938C547.633 1.07776 704 138.618 704 308.127C648.559 487.781 555.263 423.602 438.986 538.99C322.71 654.378 0 478.301 0 308.127C1.94517e-06 305.617 0.0353453 303.115 0.103516 300.619C0.0364537 298.912 0 297.206 0 295.5C0 134.187 147.692 3.11615 330.998 0.540039C337.946 0.182128 344.949 0 352 0L354.276 0.00585938ZM422.817 10.2949C566.838 43.7066 673 158.735 673 295.5C620 467.5 530.812 406.055 419.656 516.527C319.74 615.829 60.3744 489.663 8.94141 344.804C16.6409 373.921 32.9701 402.741 55.3184 429.663C87.0103 467.842 130.554 501.888 177.774 527.116C225.007 552.35 275.69 568.641 321.593 571.536C367.51 574.432 408.186 563.921 436.169 536.151C494.959 477.81 548.404 464.6 592.619 444.031C614.729 433.746 634.68 421.611 652.539 401.136C670.348 380.717 686.287 351.756 699.997 307.524C699.672 161.409 581.156 38.8803 422.817 10.2949Z"
                fill="#3B82F6"
                fill-opacity="0.85"
            />
        </svg>

        <!-- Decorative Bottom-Right SVG -->
        <svg
            class="pointer-events-none fixed bottom-0 right-0 hidden w-[40vw] max-w-[900px] translate-x-[15%] translate-y-[40%] sm:block"
            viewBox="0 0 900 855"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M594.553 676.135C427.678 773.811 223.033 735.232 136.595 589.417C92.6768 406.605 205.658 414.238 246.842 255.687C288.025 97.1357 655.413 84.0424 742.189 230.429C743.469 232.588 744.714 234.759 745.928 236.94C746.856 238.374 747.758 239.824 748.628 241.291C830.886 380.055 770.675 568.117 614.305 663.806C608.51 667.657 602.58 671.384 596.514 674.98L594.553 676.135ZM530.346 702.235C389.419 746.934 239.441 702.119 169.701 584.471C127.585 409.488 235.638 416.865 274.924 265.153C310.237 128.783 597.684 105.055 715.795 203.439C694.324 182.317 665.581 165.853 632.629 154.09C585.898 137.409 531.08 130.325 477.596 132.702C424.098 135.081 372.192 146.912 331.23 167.828C290.254 188.752 260.624 218.535 250.713 256.692C229.89 336.858 190.652 375.474 163.106 415.714C149.331 435.836 138.357 456.448 133.435 483.168C128.528 509.814 129.585 542.854 140.346 587.894C215.134 713.42 379.564 758.387 530.346 702.235Z"
                fill="#3B82F6"
                fill-opacity="0.85"
            />
        </svg>

        <div class="relative z-10 my-auto flex w-full max-w-[1104px] flex-col gap-8">
            <!-- Main Card Container -->
            <div class="flex w-full flex-col overflow-hidden rounded-[10px] shadow-[0_0_25px_rgba(0,0,0,0.25)] lg:min-h-[792px] lg:flex-row">
                <!-- Sidebar / Left Panel -->
                <aside class="relative flex flex-col justify-between gap-10 bg-[radial-gradient(203%_267%_at_187%_-91%,rgba(35,76,144,0.85)_0%,rgba(59,130,246,0.85)_100%)] px-8 py-10 text-white lg:w-[44%] lg:shrink-0 lg:px-[52px] lg:py-[95px] lg:pb-[20px]">
                    <div class="flex flex-col gap-[18px]">
                        <div class="flex items-center gap-4">
                            <img
                                src="{{ asset('favicon.ico') }}" 
                                alt="Logo SIM-PBJ"
                                class="size-[77px] shrink-0 object-contain"
                            />
                            <span class="text-[28px] font-medium">SIM-PBJ</span>
                        </div>
                        
                        <h2 class="mt-8 max-w-[382px] text-2xl font-medium sm:text-[30px]">
                            Aplikasi administratif Politeknik Negeri Jakarta
                        </h2>
                        <p class="mt-2 max-w-[382px] text-lg font-medium leading-[30px]">
                            Pemantauan proses peminjaman barang yang berlangsung di sistem PNJ
                        </p>
                    </div>
                    <p class="text-center text-xs font-medium leading-[30px] text-white/50 lg:mt-[200px]">
                        &copy; 2024 SIM-PBJ. All rights reserved.
                    </p>
                </aside>

                <!-- Form Content / Right Panel -->
                <main class="flex flex-1 flex-col justify-center bg-white/90 px-6 py-10 sm:px-12 lg:px-[67px]">
                    <form
                        action="{{ route('login') }}"
                        method="POST"
                        class="mx-auto flex w-full max-w-[486px] flex-col"
                    >
                        @csrf

                        <h1 class="text-3xl font-semibold text-black sm:text-4xl">
                            Selamat Datang!
                        </h1>
                        <p class="mt-[6px] text-lg font-medium text-[#AAA]">
                            Silahkan masuk ke akun Anda untuk melanjutkan ke platform.
                        </p>

                        <!-- Input Login (Mendukung Username / NIP / Email) -->
                        <label
                            for="login"
                            class="mt-8 text-lg font-medium text-black"
                        >
                            Email atau Nomor Induk
                        </label>
                        <div class="relative flex items-center mt-3">
                            <div class="pointer-events-none absolute left-5 flex items-center justify-center text-[#AAA]">
                                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="20" height="16" x="2" y="4" rx="2"/>
                                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                                </svg>
                            </div>
                            <input
                                id="login"
                                name="login"
                                type="text"
                                value="{{ old('login', old('identifier')) }}"
                                placeholder="Masukkan email atau nomor induk"
                                required
                                autofocus
                                class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] pl-14 pr-4 text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                            />
                        </div>

                        <!-- Input Password -->
                        <label
                            for="password"
                            class="mt-6 text-lg font-medium text-black"
                        >
                            Password
                        </label>
                        <div class="relative flex items-center mt-3">
                            <div class="pointer-events-none absolute left-5 flex items-center justify-center text-[#AAA]">
                                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>

                            <input
                                id="password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Masukkan password"
                                required
                                class="h-[60px] w-full rounded-[5px] border border-[#D2D2D2] bg-[#F5F6FF] pl-14 pr-14 text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6] [&::-ms-reveal]:hidden"
                            />

                            <!-- Toggle Password Button -->
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                class="absolute right-5 flex items-center justify-center text-[#787878] hover:text-black transition-colors"
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

                        <!-- Remember Me & Forgot Password -->
                        <div class="mt-6 flex items-center justify-between gap-4 text-lg font-medium">
                            <label class="flex cursor-pointer items-center gap-4 text-[#AAA]">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    x-model="remember"
                                    class="size-[18px] rounded-[3px] border-2 border-black accent-[#3B82F6]"
                                />
                                Ingat saya
                            </label>
                            <a href="#" class="text-[#3B82F6] hover:underline">
                                Lupa password?
                            </a>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="mt-[27px] h-[60px] w-full rounded-[5px] bg-[#3B82F6] text-lg text-white transition-colors hover:bg-[#2563EB]"
                        >
                            Masuk &rarr;
                        </button>

                        <!-- Divider -->
                        <div class="mt-[34px] flex items-center gap-4 text-lg font-medium text-[#AAA]">
                            <div class="h-px flex-1 bg-[#787878]/25"></div>
                            atau
                            <div class="h-px flex-1 bg-[#787878]/25"></div>
                        </div>

                        <!-- Google Login Button -->
                        <a
                            href="{{ url('auth/google') }}"
                            class="mt-[21px] flex h-[60px] w-full items-center justify-center gap-4 rounded-[5px] border border-[#D2D2D2] bg-white text-lg font-medium text-black transition-colors hover:bg-gray-50"
                        >
                            <img
                                src="https://api.builder.io/api/v1/image/assets/TEMP/1d34ad722c88563f67ab01fc9efbaba5ed84a8fa?width=50"
                                alt="Google"
                                class="size-[25px]"
                            />
                            Masuk dengan Google
                        </a>

                        <!-- Register Link -->
                        <p class="mt-[42px] text-center text-lg font-medium text-[#AAA]">
                            Belum punya akun?
                            <a href="{{ route('register') }}" class="text-[#3B82F6] hover:underline">
                                Daftar di sini
                            </a>
                        </p>
                    </form>
                </main>
            </div>

            <!-- Panel Akses Cepat untuk Pengujian Manual Overseer -->
            <section class="rounded-[10px] border border-slate-200 bg-white/95 p-6 shadow-md backdrop-blur-sm">
                <h3 class="text-xl font-bold text-slate-800">
                    Panel Bantuan Pengujian Manual Overseer (Daftar Akun 9 Peran Resmi PNJ)
                </h3>
                <p class="mt-1 text-sm text-slate-600 mb-4">
                    <em>Klik tombol <strong>"Gunakan Akun Ini"</strong> pada salah satu baris untuk mengisi kolom formulir login secara otomatis.</em>
                </p>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse border border-slate-200">
                        <thead class="bg-slate-100 text-slate-700">
                            <tr>
                                <th class="border border-slate-200 p-2 text-center">No</th>
                                <th class="border border-slate-200 p-2">Peran (Role)</th>
                                <th class="border border-slate-200 p-2">Nama Pejabat / Pengguna</th>
                                <th class="border border-slate-200 p-2">Unit Kerja</th>
                                <th class="border border-slate-200 p-2">Username</th>
                                <th class="border border-slate-200 p-2">Password Default</th>
                                <th class="border border-slate-200 p-2 text-center">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">1</td>
                                <td class="border border-slate-200 p-2 font-semibold">Pemohon (User)</td>
                                <td class="border border-slate-200 p-2">Dr. Ir. Maulana Pengaju, M.T.</td>
                                <td class="border border-slate-200 p-2">Jurusan TIK</td>
                                <td class="border border-slate-200 p-2"><code>user_pengaju</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="user_pengaju" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">2</td>
                                <td class="border border-slate-200 p-2 font-semibold">Staf Bidang 2</td>
                                <td class="border border-slate-200 p-2">Ahmad Staff Bidang 2, S.E.</td>
                                <td class="border border-slate-200 p-2">Bidang II</td>
                                <td class="border border-slate-200 p-2"><code>staff_b2</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="staff_b2" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">3</td>
                                <td class="border border-slate-200 p-2 font-semibold">Wadir 2 (Hak Mutlak ACC)</td>
                                <td class="border border-slate-200 p-2">Prof. Dr. Ir. Wadir Dua, M.Sc.</td>
                                <td class="border border-slate-200 p-2">Bidang II (Umum & Keuangan)</td>
                                <td class="border border-slate-200 p-2"><code>wadir2</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="wadir2" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">4</td>
                                <td class="border border-slate-200 p-2 font-semibold">Perencanaan</td>
                                <td class="border border-slate-200 p-2">Budi Perencanaan, S.Kom.</td>
                                <td class="border border-slate-200 p-2">Subbagian Perencanaan & MAK</td>
                                <td class="border border-slate-200 p-2"><code>perencanaan</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="perencanaan" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">5</td>
                                <td class="border border-slate-200 p-2 font-semibold">PPBJ (Pengelola Paket)</td>
                                <td class="border border-slate-200 p-2">Citra Koordinator PPBJ, M.M.</td>
                                <td class="border border-slate-200 p-2">Unit Kerja PBJ</td>
                                <td class="border border-slate-200 p-2"><code>ppbj</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="ppbj" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">6</td>
                                <td class="border border-slate-200 p-2 font-semibold">Pejabat Pengadaan (PP)</td>
                                <td class="border border-slate-200 p-2">Dedi Pejabat Pengadaan, S.T.</td>
                                <td class="border border-slate-200 p-2">Pejabat Pengadaan (&le; 200 Juta)</td>
                                <td class="border border-slate-200 p-2"><code>pp_user</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="pp_user" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">7</td>
                                <td class="border border-slate-200 p-2 font-semibold">PPK 1 (Sarana - Terisolasi)</td>
                                <td class="border border-slate-200 p-2">Eko PPK Bidang Sarana, M.T.</td>
                                <td class="border border-slate-200 p-2">PPK I (> 200 Juta)</td>
                                <td class="border border-slate-200 p-2"><code>ppk_1</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="ppk_1" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">8</td>
                                <td class="border border-slate-200 p-2 font-semibold">PPK 2 (Laboratorium)</td>
                                <td class="border border-slate-200 p-2">Fajar PPK Bidang Laboratorium, M.Kom.</td>
                                <td class="border border-slate-200 p-2">PPK II (> 200 Juta)</td>
                                <td class="border border-slate-200 p-2"><code>ppk_2</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="ppk_2" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">9</td>
                                <td class="border border-slate-200 p-2 font-semibold">PPK 3 (Konstruksi)</td>
                                <td class="border border-slate-200 p-2">Gita PPK Bidang Konstruksi, S.T.</td>
                                <td class="border border-slate-200 p-2">PPK III (> 200 Juta)</td>
                                <td class="border border-slate-200 p-2"><code>ppk_3</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="ppk_3" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">10</td>
                                <td class="border border-slate-200 p-2 font-semibold">PPK 4 (Jasa)</td>
                                <td class="border border-slate-200 p-2">Hendra PPK Bidang Jasa Lainnya, S.E.</td>
                                <td class="border border-slate-200 p-2">PPK IV (> 200 Juta)</td>
                                <td class="border border-slate-200 p-2"><code>ppk_4</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="ppk_4" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">11</td>
                                <td class="border border-slate-200 p-2 font-semibold">Perlengkapan (BAST)</td>
                                <td class="border border-slate-200 p-2">Indra Staff Perlengkapan, A.Md.</td>
                                <td class="border border-slate-200 p-2">Unit Perlengkapan & BMN</td>
                                <td class="border border-slate-200 p-2"><code>perlengkapan</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="perlengkapan" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-slate-200 p-2 text-center">12</td>
                                <td class="border border-slate-200 p-2 font-semibold">Keuangan (SP2D / Bayar)</td>
                                <td class="border border-slate-200 p-2">Joko Bendahara Keuangan, S.Ak.</td>
                                <td class="border border-slate-200 p-2">Unit Keuangan & Pencairan</td>
                                <td class="border border-slate-200 p-2"><code>keuangan</code></td>
                                <td class="border border-slate-200 p-2"><code>password123</code></td>
                                <td class="border border-slate-200 p-2 text-center">
                                    <button type="button" class="btn-autofill-login rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors" data-username="keuangan" data-password="password123">Gunakan Akun Ini</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <!-- Script Autofill -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const autofillButtons = document.querySelectorAll('.btn-autofill-login');
            const loginInput = document.getElementById('login');
            const passwordInput = document.getElementById('password');

            autofillButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const username = this.getAttribute('data-username');
                    const password = this.getAttribute('data-password');

                    if (loginInput) loginInput.value = username;
                    if (passwordInput) passwordInput.value = password;

                    // Smooth scroll kembali ke form login atas
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            });
        });
    </script>
</body>
</html>