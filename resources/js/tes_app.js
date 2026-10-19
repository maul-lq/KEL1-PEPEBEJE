/**
 * Skrip Utama Aplikasi Web SIM-PBJ Politeknik Negeri Jakarta (app.js)
 *
 * Murni Vanilla JavaScript (Bebas dari CSS framework / library eksternal).
 * Menyediakan interaktivitas semantik:
 * 1. Autofill kredensial login cepat untuk pengujian manual oleh Overseer.
 * 2. Kalkulasi visual jalur auto-routing ambang batas HPS saat registrasi paket.
 */

document.addEventListener('DOMContentLoaded', function () {
    // =========================================================================
    // 1. Panel Bantuan Pengujian Manual: Autofill Akun Demo
    // =========================================================================
    const autofillButtons = document.querySelectorAll('.btn-autofill-login');
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

    // =========================================================================
    // 2. Indikator Interaktif Auto-Routing HPS Paket Pengadaan (NFR 4)
    // =========================================================================
    const hpsInput = document.getElementById('nilai_hps');
    const ppkSelect = document.getElementById('id_user_ppk');

    if (hpsInput) {
        // Buat elemen penampil hint semantik jika belum ada
        let hintEl = document.getElementById('hps-routing-hint');
        if (!hintEl) {
            hintEl = document.createElement('p');
            hintEl.id = 'hps-routing-hint';
            hpsInput.parentNode.appendChild(hintEl);
        }

        const updateRoutingHint = function () {
            const val = parseFloat(hpsInput.value) || 0;
            if (val <= 0) {
                hintEl.innerHTML = '<small><em>Masukkan nilai HPS dalam rupiah.</em></small>';
                if (ppkSelect) ppkSelect.required = false;
            } else if (val <= 50000000) {
                hintEl.innerHTML = '<strong>[JALUR ROUTING]:</strong> Pengadaan Langsung (&le; Rp 50.000.000).';
                if (ppkSelect) ppkSelect.required = false;
            } else if (val <= 200000000) {
                hintEl.innerHTML = '<strong>[JALUR ROUTING]:</strong> Pejabat Pengadaan / PP (Rp 50.000.001 s.d Rp 200.000.000).';
                if (ppkSelect) ppkSelect.required = false;
            } else {
                hintEl.innerHTML = '<strong>[JALUR ROUTING]:</strong> Pejabat Pembuat Komitmen / PPK (&gt; Rp 200.000.000) &bull; <u>Wajib Pilih Salah Satu Akun PPK</u>.';
                if (ppkSelect) ppkSelect.required = true;
            }
        };

        hpsInput.addEventListener('input', updateRoutingHint);
        updateRoutingHint();
    }
});
