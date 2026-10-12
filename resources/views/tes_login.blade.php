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
        <!-- Left Side: Capybara Background Image -->
        <div class="absolute inset-y-0 left-0 w-full lg:w-[62%] h-full">
            <img 
                src="{{ asset('images/login-capybara.png') }}" 
                alt="Background PBJ" 
                class="w-full h-full object-cover object-left-bottom opacity-90 lg:opacity-100"
            >
            <!-- Gradient Overlay for Soft Blend -->
            <div 
                class="absolute inset-0 bg-gradient-to-r from-white/20 via-transparent to-transparent lg:block hidden"
                style="background: linear-gradient(215deg, rgba(255, 255, 255, 0.4) 0%, rgba(155, 192, 218, 0) 80%);"
            ></div>
            <!-- Mobile dark tint overlay to ensure readability -->
            <div class="absolute inset-0 bg-slate-900/40 lg:hidden"></div>
        </div>

        <!-- Right Side: Curved Ellipse Background (From Figma SVG) -->
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
        <h1 class="sr-only">Halaman Tes Login Sistem PBJ</h1>
        <div class="w-full max-w-[480px] xl:max-w-[540px] flex flex-col items-center">
            
            <!-- Large "Login" Title (Styled as in Figma) -->
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

                    <!-- NIP / Email Input -->
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
                            placeholder="NIP / Email"
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
                            Belom punya akun?
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <!-- Floating / Collapsible Quick Demo Accounts Helper (Aman untuk Developer/Pengujian) -->
    <aside class="relative z-20 px-4 pb-4">
        <details class="group max-w-4xl mx-auto bg-slate-900/80 backdrop-blur-md border border-slate-700/60 rounded-2xl text-slate-200 overflow-hidden shadow-2xl transition-all">
            <summary class="cursor-pointer px-5 py-3 font-medium text-sm flex items-center justify-between hover:bg-slate-800/60 transition-colors select-none">
                <span class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <strong>Akun Demo Testing</strong>
                    <span class="text-xs text-slate-400 font-normal hidden sm:inline">(Password semua akun: <code class="text-amber-300 bg-slate-800 px-1.5 py-0.5 rounded">tes_password123</code> — Klik tombol role untuk isi otomatis)</span>
                </span>
                <span class="text-xs text-indigo-300 group-open:rotate-180 transition-transform duration-200">
                    ▼ Buka / Tutup
                </span>
            </summary>
            
            <div class="p-4 border-t border-slate-700/60 text-xs overflow-x-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                    @php
                        $demoAccounts = [
                            ['role' => 'user_pengaju', 'email' => 'tes_pengaju@kampus.ac.id', 'nama' => 'tes_Budi Pengaju Unit'],
                            ['role' => 'wadir_2', 'email' => 'tes_wadir2@kampus.ac.id', 'nama' => 'tes_Wadir 2 Bidang Keuangan'],
                            ['role' => 'perencanaan', 'email' => 'tes_perencanaan@kampus.ac.id', 'nama' => 'tes_Staf Perencanaan MAK'],
                            ['role' => 'ppbj', 'email' => 'tes_ppbj@kampus.ac.id', 'nama' => 'tes_Pengelola PPBJ'],
                            ['role' => 'pp', 'email' => 'tes_pp@kampus.ac.id', 'nama' => 'tes_Pejabat Pengadaan (PP)'],
                            ['role' => 'ppk', 'email' => 'tes_ppk@kampus.ac.id', 'nama' => 'tes_Pejabat Pembuat Komitmen (PPK)'],
                            ['role' => 'perlengkapan', 'email' => 'tes_perlengkapan@kampus.ac.id', 'nama' => 'tes_Staf Perlengkapan'],
                            ['role' => 'keuangan', 'email' => 'tes_keuangan@kampus.ac.id', 'nama' => 'tes_Staf Keuangan'],
                            ['role' => 'staff_bidang_2 (Admin)', 'email' => 'tes_admin@kampus.ac.id', 'nama' => 'tes_Super Admin PBJ'],
                        ];
                    @endphp

                    @foreach ($demoAccounts as $acc)
                        <button 
                            type="button" 
                            onclick="fillLogin('{{ $acc['email'] }}', 'tes_password123')"
                            class="text-left p-2.5 bg-slate-800/90 hover:bg-indigo-900/50 hover:border-indigo-500 border border-slate-700 rounded-xl transition-all flex flex-col justify-between"
                        >
                            <div class="font-semibold text-indigo-300 flex items-center justify-between">
                                <span>{{ $acc['role'] }}</span>
                                <span class="text-[10px] bg-indigo-500/20 text-indigo-300 px-1.5 py-0.5 rounded font-mono">Pilih</span>
                            </div>
                            <div class="text-slate-300 truncate mt-0.5">{{ $acc['nama'] }}</div>
                            <div class="text-slate-400 text-[11px] font-mono truncate mt-0.5">{{ $acc['email'] }}</div>
                        </button>
                    @endforeach
                </div>
            </div>
        </details>
    </aside>

    <script>
        // Password Visibility Toggle
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                
                eyeIcon.innerHTML = isPassword 
                    ? `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />`
                    : `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />`;
            });
        }

        // Quick Auto-Fill for Testing
        function fillLogin(loginVal, passVal) {
            const loginInput = document.getElementById('login');
            const passInput = document.getElementById('password');
            if (loginInput && passInput) {
                loginInput.value = loginVal;
                passInput.value = passVal;
                loginInput.focus();
            }
        }
    </script>
</body>
</html>
