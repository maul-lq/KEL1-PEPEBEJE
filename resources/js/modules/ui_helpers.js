/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul pembantu antarmuka global ini susun untuk menyediakan parser dataset JSON
 *             yang aman, pemformat Rupiah ID, delegasi aksi DOM berbasis atribut `data-*`, serta
 *             komponen UI reusable Alpine (`dropdownMenu`, `modalDialog`, `docPreviewModal`, `lightboxViewer`).
 */

/**
 * Mengurai string JSON dari atribut `data-*` elemen DOM secara aman dengan nilai cadangan.
 *
 * Tujuan: menghindari kegagalan runtime seluruh halaman jika suatu elemen Blade belum mengisi
 * atribut JSON atau memiliki sintaks JSON tidak valid.
 * Parameter: `{HTMLElement} el`, `{string} key` (nama dataset camelCase), `{*} [fallback={}]`.
 * Nilai kembali: `{*}` Objek/array hasil parsing JSON atau `fallback` jika gagal/kosong.
 * Contoh pemanggilan: `const cfg = parseJsonDataset(this.$el, 'config', {});`
 */
export function parseJsonDataset(el, key, fallback = {}) {
    const raw = el?.dataset?.[key];
    if (!raw) {
        return fallback;
    }
    try {
        return JSON.parse(raw);
    } catch (err) {
        console.warn(`Gagal mengurai data-${key}:`, err);
        return fallback;
    }
}

/**
 * Memformat angka numerik menjadi string mata uang Rupiah Indonesia tanpa angka di belakang koma.
 *
 * Tujuan: memakai standar `Intl.NumberFormat('id-ID')` agar pemisah ribuan bertitik dan
 * simbol `Rp` tampil konsisten di seluruh kartu KPI, tabel, dan formulir paket.
 * Parameter: `{number|string} val` - Angka nominal yang akan diformat.
 * Nilai kembali: `{string}` String mata uang berformat IDR (misalnya `Rp 1.500.000`).
 * Contoh pemanggilan: `formatRupiahId(25000000)`
 */
export function formatRupiahId(val) {
    const n = parseFloat(val) || 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(n);
}

/**
 * Memformat nilai input pengguna saat mengetik menjadi string Rupiah bertitik lengkap opsional koma desimal.
 *
 * Tujuan: menyaring karakter non-numerik sambil menjaga pemisah koma desimal agar input nominal
 * panjang di form pengajuan mudah dibaca pengusul tanpa memecah proses ketikan.
 * Parameter: `{string|number} val` - Nilai string atau angka dari event input.
 * Nilai kembali: `{string}` String berawalan `Rp ` dengan titik ribuan atau string kosong.
 * Contoh pemanggilan: `raw = formatRupiahInput($event.target.value);`
 */
export function formatRupiahInput(val) {
    if (!val) {
        return '';
    }
    const numberString = val.toString().replace(/[^,\d]/g, '');
    const split = numberString.split(',');
    const sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    const ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        const separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] !== undefined ? `${rupiah},${split[1]}` : rupiah;
    return rupiah ? `Rp ${rupiah}` : '';
}

/**
 * Mengikat listener delegasi DOM global melalui atribut `data-*` agar view Blade bebas JS inline.
 *
 * Tujuan: menampung empat pola umum (`[data-auto-submit]`, `form[data-confirm]`,
 * `[data-reload-page]`, dan `[data-check-scope]`) dalam satu listener tingkat dokumen demi
 * menjaga kebersihan markup Blade dan efisiensi memori browser.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Tidak mengembalikan nilai (mendaftarkan global event listener).
 * Contoh pemanggilan: `initGlobalDomHandlers();`
 */
export function initGlobalDomHandlers() {
    document.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof Element && target.matches('[data-auto-submit]')) {
            target.form?.submit();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        // tahan pengiriman form destruktif hingga pengguna menyetujui dialog konfirmasi data-confirm.
        if (form instanceof HTMLFormElement && form.dataset.confirm) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        }
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const reloadBtn = target.closest('[data-reload-page]');
        if (reloadBtn) {
            window.location.reload();
            return;
        }

        const checkBtn = target.closest('[data-check-scope]');
        if (checkBtn) {
            const scopeSelector = checkBtn.dataset.checkScope;
            const inputSelector = checkBtn.dataset.checkSelector || 'input[type="checkbox"]';
            const checked = checkBtn.dataset.checkState === 'true';
            const container = checkBtn.closest(scopeSelector);
            if (container) {
                container.querySelectorAll(inputSelector).forEach((checkbox) => {
                    checkbox.checked = checked;
                });
            }
        }
    });
}

/**
 * Menyediakan komponen menu dropdown sederhana yang mendukung toggle dan penutupan eksternal.
 *
 * Tujuan: pakai ulang komponen kecil ini pada filter dropdown dan menu aksi ringkas di UI.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `open`, `toggle()`, dan `close()` untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="dropdownMenu()">`
 */
export function dropdownMenu() {
    return {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    };
}

/**
 * Menyediakan komponen dialog modal dengan penguncian scroll body dan jebakan fokus keyboard (a11y).
 *
 * Tujuan: mengelola perpindahan fokus Tab/Shift+Tab tetap di dalam dialog saat `focusable`
 * aktif serta memicu modal berdasarkan ID via CustomEvent window `open-modal`/`close-modal`.
 * Parameter: Tidak menerima argumen (status awal & fokus dibaca dari `data-show` dan `data-focusable`).
 * Nilai kembali: Objek state dan helper navigasi fokus keyboard modal untuk Alpine.js.
 * Contoh pemanggilan: `<div id="modal-detail" x-data="modalDialog()" data-show="0" data-focusable="1">`
 */
export function modalDialog() {
    return {
        show: false,
        focusable: false,

        /**
         * Menginisialisasi status buka modal dan mengawasi perubahan `show` untuk kunci scroll dan fokus.
         *
         * Tujuan: tambahkan kelas `overflow-y-hidden` pada `document.body` saat modal terbuka
         * agar halaman latar belakang tidak ikut tergulir di desktop maupun perangkat sentuh.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis oleh Alpine saat komponen modal di-mount.
         */
        init() {
            this.show = this.$el.dataset.show === '1';
            this.focusable = this.$el.dataset.focusable === '1';

            this.$watch('show', (value) => {
                if (value) {
                    document.body.classList.add('overflow-y-hidden');
                    if (this.focusable) {
                        // beri tunda 100ms supaya transisi CSS visibilitas elemen rampung sebelum focus().
                        setTimeout(() => this.firstFocusable()?.focus(), 100);
                    }
                } else {
                    document.body.classList.remove('overflow-y-hidden');
                }
            });
        },

        /**
         * Membuka modal ini apabila event global menyebutkan ID elemen yang cocok.
         *
         * Tujuan: mendukung pemanggilan modal deklaratif dari komponen Blade terpisah.
         * Parameter: `{string} targetId` - ID DOM modal tujuan.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@open-modal.window="onWindowOpen($event.detail)"`
         */
        onWindowOpen(targetId) {
            if (targetId === this.$el.id) {
                this.show = true;
            }
        },

        /**
         * Menutup modal ini bila event penutupan menargetkan ID elemen modal saat ini.
         *
         * Tujuan: pastikan penutupan suatu modal via event tidak menutup modal lain secara keliru.
         * Parameter: `{string} targetId` - ID DOM modal yang ditutup.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@close-modal.window="onWindowClose($event.detail)"`
         */
        onWindowClose(targetId) {
            if (targetId === this.$el.id) {
                this.show = false;
            }
        },

        /**
         * Menutup dialog modal secara langsung.
         *
         * Tujuan: sediakan aksi ringkas untuk tombol tutup atau klik backdrop.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="close()"`
         */
        close() {
            this.show = false;
        },

        /**
         * Mengumpulkan seluruh elemen interaktif di dalam modal yang tidak berstatus `disabled`.
         *
         * Tujuan: menyaring `input[type='hidden']` dan `tabindex="-1"` supaya siklus Tab
         * hanya mampir pada tombol atau field yang dapat dijangkau keyboard pengguna.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{Array<HTMLElement>}` Daftar elemen fokus yang valid.
         * Contoh pemanggilan: `const items = this.focusables();`
         */
        focusables() {
            const selector = "a, button, input:not([type='hidden']), textarea, select, details, [tabindex]:not([tabindex='-1'])";
            return [...this.$el.querySelectorAll(selector)].filter((el) => !el.hasAttribute('disabled'));
        },

        /**
         * Mengembalikan elemen fokus pertama di dalam modal.
         *
         * Tujuan: gunakan sebagai target awal ketika modal dibuka atau ketika siklus Tab wrap-around.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{HTMLElement|undefined}` Elemen fokus pertama atau undefined bila kosong.
         * Contoh pemanggilan: `this.firstFocusable()?.focus();`
         */
        firstFocusable() {
            return this.focusables()[0];
        },

        /**
         * Mengembalikan elemen fokus terakhir di dalam modal.
         *
         * Tujuan: pakai saat pengguna menekan Shift+Tab pada elemen pertama supaya fokus berputar.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{HTMLElement|undefined}` Elemen fokus terakhir.
         * Contoh pemanggilan: `this.lastFocusable()?.focus();`
         */
        lastFocusable() {
            return this.focusables().slice(-1)[0];
        },

        /**
         * Mengambil elemen fokus berikutnya sesuai urutan DOM di dalam modal.
         *
         * Tujuan: menjaga fokus keyboard tidak lolos ke elemen latar belakang saat modal aktif.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{HTMLElement|undefined}` Elemen fokus selanjutnya atau elemen pertama.
         * Contoh pemanggilan: `this.nextFocusable()?.focus();`
         */
        nextFocusable() {
            return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable();
        },

        /**
         * Mengambil elemen fokus sebelumnya untuk mendukung navigasi balik `Shift+Tab`.
         *
         * Tujuan: memastikan urutan tab mundur ikut terkurung di dalam kontainer modal.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{HTMLElement|undefined}` Elemen fokus sebelumnya atau elemen terakhir.
         * Contoh pemanggilan: `this.prevFocusable()?.focus();`
         */
        prevFocusable() {
            return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable();
        },

        /**
         * Menghitung indeks elemen fokus berikutnya dengan modulo panjang daftar fokus.
         *
         * Tujuan: pakai modulo `(list.length + 1)` mengikuti konvensi focus-trap Alpine Breeze.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{number}` Indeks posisi fokus berikutnya.
         * Contoh pemanggilan: `const idx = this.nextFocusableIndex();`
         */
        nextFocusableIndex() {
            const list = this.focusables();
            return (list.indexOf(document.activeElement) + 1) % (list.length + 1);
        },

        /**
         * Menghitung indeks elemen fokus sebelumnya secara melingkar dengan offset non-negatif.
         *
         * Tujuan: tambahkan `list.length + 1` sebelum modulo agar indeks tetap positif pada kasus indeks 0.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{number}` Indeks posisi fokus sebelumnya.
         * Contoh pemanggilan: `const idx = this.prevFocusableIndex();`
         */
        prevFocusableIndex() {
            const list = this.focusables();
            return (list.indexOf(document.activeElement) - 1 + list.length + 1) % (list.length + 1);
        },
    };
}

/**
 * Menyediakan modal pratinjau dokumen PDF global yang merespons event pratinjau.
 *
 * Tujuan: memusatkan pembukaan dokumen KAK, SPK, atau lampiran dalam iframe modal tunggal
 * supaya setiap tabel daftar cukup mengirim detail dokumen (`title`, `url`, `size`).
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `open`, `doc`, `openPreview()`, dan `close()` untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="docPreviewModal()">`
 */
export function docPreviewModal() {
    return {
        open: false,
        doc: { title: '', url: '', size: '' },

        /**
         * Membuka modal pratinjau dengan mengisi metadata judul, tautan URL, dan ukuran dokumen.
         *
         * Tujuan: fallback ke objek metadata kosong jika pemanggil tidak mengirim detail lengkap.
         * Parameter: `{Object} detail` - Berisi `{title, url, size}` berkas yang dipratinjau.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `openPreview({ title: 'KAK.pdf', url: '/storage/...', size: '120 KB' })`
         */
        openPreview(detail) {
            this.doc = detail || { title: '', url: '', size: '' };
            this.open = true;
        },

        /**
         * Menutup modal pratinjau dokumen.
         *
         * Tujuan: menyembunyikan modal tanpa menghapus elemen agar pemuatan berikutnya instan.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="close()"`
         */
        close() {
            this.open = false;
        },
    };
}

/**
 * Menyediakan komponen lightbox layar penuh untuk memperbesar foto dokumentasi inspeksi barang.
 *
 * Tujuan: membiarkan petugas Perlengkapan & BMN maupun Keuangan memeriksa detail foto barang
 * tanpa harus mengunduh gambar ke penyimpanan lokal.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `lightboxImg` dan `closeLightbox()` untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="lightboxViewer()">`
 */
export function lightboxViewer() {
    return {
        lightboxImg: null,
        /**
         * Menutup lightbox pratinjau foto dengan mengosongkan URL gambar aktif.
         *
         * Tujuan: mengosongkan `lightboxImg` agar overlay langsung tersembunyi melalui `x-show`.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="closeLightbox()"`
         */
        closeLightbox() {
            this.lightboxImg = null;
        },
    };
}
