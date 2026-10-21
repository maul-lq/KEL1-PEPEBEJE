/**
 * Modul Autofill Kredensial Akun Dinas Demo (autofill_login.js)
 *
 * Membantu pengujian operasional manual cepat oleh Overseer dengan mengisi
 * field login dan password secara otomatis berdasarkan atribut data semantik
 * [data-username] dan [data-password] pada tabel direktori akun dinas.
 */
export function initAutofillLogin() {
    const autofillButtons = document.querySelectorAll('[data-username][data-password], .btn-autofill-login');
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');

    if (autofillButtons.length > 0 && loginInput && passwordInput) {
        autofillButtons.forEach(button => {
            button.addEventListener('click', function () {
                const username = this.getAttribute('data-username');
                const password = this.getAttribute('data-password');

                if (username) {
                    loginInput.value = username;
                }
                if (password) {
                    passwordInput.value = password;
                }

                // Fokuskan kursor ke tombol submit
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.focus();
                }
            });
        });
    }
}
