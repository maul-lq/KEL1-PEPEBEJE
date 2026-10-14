/**
 * Script Pengujian Login Backend SIM-PBJ (AGENTS-BACKEND.md)
 * Menyediakan fungsi pembantu isi otomatis kredensial demo dan toggle visibilitas kata sandi.
 */

window.fillCredentials = function (login, password) {
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');
    if (loginInput && passwordInput) {
        loginInput.value = login;
        passwordInput.value = password;
        loginInput.focus();
    }
};

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleBtn.textContent = isPassword ? 'Sembunyikan' : 'Tampilkan';
        });
    }
});
