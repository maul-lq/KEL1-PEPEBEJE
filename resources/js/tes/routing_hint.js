/**
 * Modul Indikator Visual Auto-Routing HPS Paket Pengadaan (routing_hint.js)
 *
 * Memberikan kalkulasi dan petunjuk jalur routing pengadaan secara realtime:
 * - HPS <= 50 Juta: Pengadaan Langsung (PPBJ langsung buat Surat Kontrak/SPK)
 * - 50 Juta < HPS < 200 Juta: Pejabat Pengadaan / PP (Verifikasi & Negosiasi)
 * - HPS >= 200 Juta: Pejabat Pembuat Komitmen / PPK (Wajib Pilih Salah Satu Akun PPK)
 */
export function initRoutingHint() {
    const hpsInput = document.getElementById('nilai_hps');
    const ppkSelect = document.getElementById('id_user_ppk');

    if (hpsInput) {
        // Buat elemen penampil hint semantik jika belum ada di DOM
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
                if (ppkSelect) {
                    ppkSelect.required = false;
                }
            } else if (val <= 50000000) {
                hintEl.innerHTML = '<strong>[JALUR ROUTING]:</strong> Pengadaan Langsung (&le; Rp 50.000.000) &bull; PPBJ langsung buat SPK.';
                if (ppkSelect) {
                    ppkSelect.required = false;
                }
            } else if (val <= 200000000) {
                hintEl.innerHTML = '<strong>[JALUR ROUTING]:</strong> Pejabat Pengadaan / PP (Rp 50.000.001 s.d Rp 200.000.000).';
                if (ppkSelect) {
                    ppkSelect.required = false;
                }
            } else {
                hintEl.innerHTML = '<strong>[JALUR ROUTING]:</strong> Pejabat Pembuat Komitmen / PPK (&gt; Rp 200.000.000) &bull; <u>Wajib Pilih Salah Satu Akun PPK</u>.';
                if (ppkSelect) {
                    ppkSelect.required = true;
                }
            }
        };

        hpsInput.addEventListener('input', updateRoutingHint);
        updateRoutingHint();
    }
}
