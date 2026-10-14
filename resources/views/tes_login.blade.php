<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem PBJ</title>
    
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
<body class="min-h-screen w-full bg-[#1E3156] font-inder overflow-x-hidden relative flex flex-col justify-between selection:bg-[#6b4fde] selection:text-white">

    <!-- Background Layer (Desktop & Tablet) -->
    <div class="fixed inset-0 w-full h-full pointer-events-none overflow-hidden z-0">
        <!-- Left Side: Background Image -->
        <div class="absolute inset-y-0 left-0 w-full lg:w-[62%] h-full">
            <img 
                src="{{ asset('images/login-capybara.png') }}" 
                alt="Background PBJ" 
                class="w-full h-full object-cover object-left-bottom opacity-90 lg:opacity-100"
            >
            <div 
                class="absolute inset-0 bg-gradient-to-r from-white/20 via-transparent to-transparent lg:block hidden"
                style="background: linear-gradient(215deg, rgba(255, 255, 255, 0.4) 0%, rgba(155, 192, 218, 0) 80%);"
            ></div>
            <div class="absolute inset-0 bg-slate-900/40 lg:hidden"></div>
        </div>

        <!-- Right Side: Curved Shape -->
        <div class="hidden lg:block absolute -right-[15%] -top-[25%] -bottom-[25%] w-[68%] h-[150%]">
            <img 
                src="{{ asset('images/ellipse-bg.svg') }}" 
                alt="Curve Shape" 
                class="w-full h-full object-fill"
            >
        </div>
    </div>

    <!-- Main Content Container -->
    <main class="relative z-10 w-full min-h-screen flex items-center justify-center lg:justify-end px-4 sm:px-8 lg:px-16 xl:px-28 py-10">
        <h1 class="sr-only">Halaman Login Sistem PBJ</h1>
        <div class="w-full max-w-[480px] xl:max-w-[540px] flex flex-col items-center">
            
            <!-- Large "Login" Title -->
            <div class="mb-6 lg:mb-8 text-center select-none">
                <h1 class="text-6xl sm:text-7xl lg:text-[100px] xl:text-[116px] font-normal leading-none text-[#b3e0ff] tracking-tight drop-shadow-[0_4px_6px_rgba(0,0,0,0.35)]">
                    Login
                </h1>
            </div>

            <!-- Login Card -->
            <div class="w-full bg-[#b0cbe6] rounded-[36px] sm:rounded-[40px] px-6 py-8 sm:px-10 sm:py-10 shadow-[0_16px_32px_rgba(0,0,0,0.3)] backdrop-blur-sm flex flex-col items-center">
                
                <h2 class="text-[#6b4fde] text-2xl sm:text-[30px] font-normal mb-6 sm:mb-8 text-center">
                    Log In To Continue
                </h2>

                <!-- Flash Success Notification -->
                @if (session('success'))
                    <div class="w-full mb-6 px-4 py-3 bg-emerald-50 border border-emerald-300 rounded-2xl text-emerald-800 text-sm flex items-center gap-2 shadow-sm">
                        <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <!-- Validation Errors Notification -->
                @if ($errors->any())
                    <div class="w-full mb-6 px-4 py-3 bg-rose-50 border border-rose-300 rounded-2xl text-rose-800 text-sm shadow-sm">
                        <div class="flex items-center gap-2 font-semibold mb-1">
                            <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span>Gagal Masuk:</span>
                        </div>
                        <ul class="list-disc list-inside text-xs space-y-0.5 pl-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Login Form -->
                <form method="POST" action="{{ route('login') }}" class="w-full flex flex-col items-center gap-5 sm:gap-6">
                    @csrf

                    <!-- Username / NIP / Email Input -->
                    <div class="w-full bg-white rounded-full h-[58px] sm:h-[64px] px-5 sm:px-6 flex items-center gap-3.5 shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50 transition-all">
                        <img 
                            src="{{ asset('images/icon-person.svg') }}" 
                            alt="Person Icon" 
                            class="w-6 h-6 shrink-0 select-none pointer-events-none"
                        >
                        <input 
                            type="text" 
                            id="login" 
                            name="login" 
                            value="{{ old('login') }}" 
                            required 
                            autofocus 
                            placeholder="Username / NIP / Email"
                            class="w-full bg-transparent border-none outline-none font-inder text-[#6b4fde] placeholder-[#6b4fde]/70 text-lg sm:text-[22px] leading-normal"
                        >
                    </div>

                    <!-- Password Input -->
                    <div class="w-full bg-white rounded-full h-[58px] sm:h-[64px] px-5 sm:px-6 flex items-center gap-3.5 shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)] focus-within:ring-2 focus-within:ring-[#6b4fde]/50 transition-all">
                        <img 
                            src="{{ asset('images/icon-lock.svg') }}" 
                            alt="Lock Icon" 
                            class="w-6 h-6 shrink-0 select-none pointer-events-none"
                        >
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Password"
                            class="w-full bg-transparent border-none outline-none font-inder text-[#6b4fde] placeholder-[#6b4fde]/70 text-lg sm:text-[22px] leading-normal"
                        >
                        <!-- Show/Hide Password Toggle -->
                        <button 
                            type="button" 
                            id="togglePassword" 
                            tabindex="-1"
                            class="text-[#6b4fde]/60 hover:text-[#6b4fde] p-1 transition-colors focus:outline-none"
                            title="Tampilkan / Sembunyikan Password"
                        >
                            <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-2 flex justify-center w-full">
                        <button 
                            type="submit"
                            class="bg-[#6b4fde] hover:bg-[#583fc4] active:scale-95 transition-all duration-150 text-white font-inder text-lg sm:text-[20px] rounded-[20px] h-[43px] w-[135px] flex items-center justify-center shadow-md cursor-pointer"
                        >
                            Login
                        </button>
                    </div>

                    <!-- Belom punya akun? Link -->
                    <div class="mt-2 text-center">
                        <a 
                            href="{{ route('register') }}" 
                            class="text-[#6b4fde] font-inder text-[15px] underline hover:text-[#523aa7] transition-colors"
                        >
                            Belom punya akun? Daftar disini
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <!-- Floating Quick Demo Accounts Helper -->
    <aside class="relative z-20 px-4 pb-4">
        <details class="group max-w-5xl mx-auto bg-slate-900/85 backdrop-blur-md border border-slate-700/60 rounded-2xl text-slate-200 overflow-hidden shadow-2xl transition-all">
            <summary class="cursor-pointer px-5 py-3 font-medium text-sm flex items-center justify-between hover:bg-slate-800/60 transition-colors select-none">
                <span class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <strong>Akun Demo Terdaftar di Database (9 Roles PNJ)</strong>
                    <span class="text-xs text-slate-400 font-normal hidden sm:inline">(Password semua akun: <code class="text-amber-300 bg-slate-800 px-1.5 py-0.5 rounded">password123</code> — Klik tombol untuk isi otomatis)</span>
                </span>
                <span class="text-xs text-indigo-300 group-open:rotate-180 transition-transform duration-200">
                    ▼ Buka / Tutup Daftar Akun
                </span>
            </summary>
            
            <div class="p-4 border-t border-slate-700/60 text-xs overflow-x-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5">
                    @php
                        $demoAccounts = [
                            ['role' => 'user', 'username' => 'user_pengaju', 'nama' => 'Dr. Maulana Pengaju (TIK)'],
                            ['role' => 'staff_bidang_2', 'username' => 'staff_b2', 'nama' => 'Ahmad Staff Bidang 2'],
                            ['role' => 'wadir_2', 'username' => 'wadir2', 'nama' => 'Prof. Wadir Dua (Wadir 2)'],
                            ['role' => 'perencanaan', 'username' => 'perencanaan', 'nama' => 'Budi Perencanaan (MAK)'],
                            ['role' => 'ppbj', 'username' => 'ppbj', 'nama' => 'Citra Koordinator PPBJ'],
                            ['role' => 'pp', 'username' => 'pp_user', 'nama' => 'Dedi Pejabat Pengadaan (PP)'],
                            ['role' => 'ppk', 'username' => 'ppk_1', 'nama' => 'Eko PPK I (Sarana)'],
                            ['role' => 'ppk', 'username' => 'ppk_2', 'nama' => 'Fajar PPK II (Lab)'],
                            ['role' => 'ppk', 'username' => 'ppk_3', 'nama' => 'Gita PPK III (Konstruksi)'],
                            ['role' => 'ppk', 'username' => 'ppk_4', 'nama' => 'Hendra PPK IV (Jasa)'],
                            ['role' => 'perlengkapan', 'username' => 'perlengkapan', 'nama' => 'Indra Staff Perlengkapan'],
                            ['role' => 'keuangan', 'username' => 'keuangan', 'nama' => 'Joko Bendahara Keuangan'],
                        ];
                    @endphp

                    @foreach ($demoAccounts as $acc)
                        <button 
                            type="button" 
                            onclick="fillCredentials('{{ $acc['username'] }}', 'password123')"
                            class="flex flex-col text-left p-2.5 rounded-xl bg-slate-800/80 hover:bg-indigo-900/50 border border-slate-700 hover:border-indigo-500/50 transition-all group"
                        >
                            <div class="flex items-center justify-between w-full mb-1">
                                <span class="font-semibold text-slate-100 group-hover:text-indigo-200">
                                    {{ $acc['nama'] }}
                                </span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider bg-slate-700 group-hover:bg-indigo-600 text-slate-300 group-hover:text-white">
                                    {{ $acc['role'] }}
                                </span>
                            </div>
                            <span class="text-slate-400 group-hover:text-slate-300 font-mono text-[11px]">
                                user: {{ $acc['username'] }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </details>
    </aside>

    <script>
        function fillCredentials(login, password) {
            const loginInput = document.getElementById('login');
            const passwordInput = document.getElementById('password');
            if (loginInput && passwordInput) {
                loginInput.value = login;
                passwordInput.value = password;
                loginInput.focus();
            }
        }

        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (toggleBtn && passwordInput && eyeIcon) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                
                if (isPassword) {
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    `;
                    toggleBtn.setAttribute('title', 'Sembunyikan Password');
                } else {
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    `;
                    toggleBtn.setAttribute('title', 'Tampilkan Password');
                }
            });
        }
    </script>
</body>
</html>
