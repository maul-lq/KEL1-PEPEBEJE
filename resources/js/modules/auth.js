/**
 * Modul Autentikasi (Masuk & Daftar) SIM-PBJ (auth.js)
 *
 * Mengatur visibilitas kata sandi serta fungsi pengisian otomatis akun uji coba.
 */

export function loginForm() {
    return {
        showPassword: false,
        remember: false,
    };
}

export function registerForm() {
    return {
        showPassword: false,
        showPasswordConfirm: false,
    };
}

export function initLoginAutofill() {
    const autofillButtons = document.querySelectorAll('.btn-autofill-login');
    if (!autofillButtons.length) {
        return;
    }

    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');

    autofillButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const username = this.getAttribute('data-username');
            const password = this.getAttribute('data-password');

            if (loginInput) {
                loginInput.value = username || '';
            }
            if (passwordInput) {
                passwordInput.value = password || '';
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
}
