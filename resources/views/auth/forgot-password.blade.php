{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View Blade ini susun untuk halaman permintaan tautan pengaturan ulang kata sandi SIM-PBJ bagi pengguna yang lupa sandi sebelum masuk ke sistem.
--}}
{{--
   Halaman Lupa Kata Sandi (`auth.forgot-password`).

  Tujuan: menyediakan formulir input alamat email terdaftar agar sistem mengirimkan tautan
          pemulihan kata sandi dengan pesan respons netral tanpa membocorkan status akun.
  Parameter (Flash Session Opsional): `error`, `status`, dan error validasi `email` beserta `old('email')`.
  Nilai kembali: Dokumen HTML lengkap halaman lupa kata sandi.
  Contoh pemanggilan: `return view('auth.forgot-password');`
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi - SIM-PBJ Politeknik Negeri Jakarta</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white">

    <div class="relative flex min-h-screen w-full flex-col items-center justify-center overflow-x-hidden p-4 sm:p-8">
        <img
            src="https://api.builder.io/api/v1/image/assets/TEMP/a6a346ae9082d34ec87b3f43d83776d06c5ee139?width=3840"
            alt=""
            class="fixed inset-0 h-full w-full object-cover blur-[1px]"
        />
        <div class="fixed inset-0 bg-white/90 opacity-75"></div>

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
            <div class="flex w-full flex-col overflow-hidden rounded-[10px] shadow-[0_0_25px_rgba(0,0,0,0.25)] lg:min-h-[680px] lg:flex-row">
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
                            Sistem Pengadaan Barang dan Jasa PNJ
                        </p>
                    </div>
                    <p class="text-center text-xs font-medium leading-[30px] text-white/50 lg:mt-[120px]">
                        &copy; {{ date('Y') }} SIM-PBJ Politeknik Negeri Jakarta
                    </p>
                </aside>

                <main class="flex flex-1 flex-col justify-center bg-white/90 px-6 py-10 sm:px-12 lg:px-[67px]">
                    <form
                        action="{{ route('password.email') }}"
                        method="POST"
                        class="mx-auto flex w-full max-w-[486px] flex-col"
                    >
                        @csrf

                        <h1 class="mb-2 text-3xl font-semibold text-black sm:text-4xl">
                            Lupa Kata Sandi
                        </h1>
                        <p class="text-base text-[#5A5A5A]">
                            Masukkan alamat email akun Anda untuk menerima tautan pengaturan ulang kata sandi.
                        </p>

                        @if(session('error'))
                            <div class="mt-4 flex items-start gap-3 rounded-[8px] border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                                <svg class="mt-0.5 size-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>{{ session('error') }}</span>
                            </div>
                        @endif
                        @if(session('warning'))
                            <div class="mt-4 flex items-start gap-3 rounded-[8px] border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                                <svg class="mt-0.5 size-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86l-7.5 13A1 1 0 003.65 18.5h16.7a1 1 0 00.86-1.5l-7.5-13a1 1 0 00-1.72 0z"/></svg>
                                <span>{{ session('warning') }}</span>
                            </div>
                        @endif
                        @if(session('status'))
                            <div class="mt-4 flex items-start gap-3 rounded-[8px] border border-blue-300 bg-blue-50 p-4 text-sm text-blue-800">
                                <svg class="mt-0.5 size-5 shrink-0 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>{{ session('status') }}</span>
                            </div>
                        @endif
                        @if($errors->any())
                            <div class="mt-4 flex items-start gap-3 rounded-[8px] border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                                <svg class="mt-0.5 size-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>{{ $errors->first() }}</span>
                            </div>
                        @endif

                        <label
                            for="email"
                            class="mt-8 text-lg font-medium text-black"
                        >
                            Email Terdaftar
                        </label>
                        <div class="relative mt-3 flex items-center">
                            <div class="pointer-events-none absolute left-5 flex items-center justify-center text-[#AAA]">
                                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="20" height="16" x="2" y="4" rx="2"/>
                                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                                </svg>
                            </div>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                placeholder="Masukkan alamat email"
                                required
                                autofocus
                                class="h-[60px] w-full rounded-[5px] border {{ $errors->has('email') ? 'border-red-400' : 'border-[#D2D2D2]' }} bg-[#F5F6FF] pl-14 pr-4 text-lg text-black outline-none placeholder:text-[#AAA] focus:border-[#3B82F6]"
                            />
                        </div>

                        <button
                            type="submit"
                            class="mt-7 h-[60px] w-full rounded-[5px] bg-[#3B82F6] text-lg text-white transition-colors hover:bg-[#2563EB]"
                        >
                            Kirim Tautan Reset
                        </button>

                        <p class="mt-8 text-center text-base font-medium text-[#787878]">
                            Sudah ingat kata sandi?
                            <a href="{{ route('login') }}" class="text-[#3B82F6] hover:underline">
                                Kembali ke halaman masuk
                            </a>
                        </p>
                    </form>
                </main>
            </div>
        </div>
    </div>
</body>
</html>
