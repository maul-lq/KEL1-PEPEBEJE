/**
 * Modul Pembantu Antarmuka Umum SIM-PBJ (ui_helpers.js)
 *
 * Menyediakan utilitas parsing data atribut HTML, pemformatan Rupiah,
 * delegasi aksi DOM global (auto-submit, konfirmasi, muat ulang, pilih izin massal),
 * serta komponen Alpine.js umum yang dipakai ulang lintas halaman.
 */

/**
 * Mengurai string JSON dari atribut data-* elemen secara aman.
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
 * Memformat angka menjadi teks mata uang Rupiah (tanpa desimal).
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
 * Memformat input angka menjadi string Rupiah bertitik saat pengguna mengetik.
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
 * Mengikat listener DOM global melalui atribut data-* agar view bebas handler inline.
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
 * Komponen dropdown sederhana yang dapat ditutup dari luar.
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
 * Komponen dialog modal dengan jebakan fokus keyboard (accessibility focus trap).
 */
export function modalDialog() {
    return {
        show: false,
        focusable: false,

        init() {
            this.show = this.$el.dataset.show === '1';
            this.focusable = this.$el.dataset.focusable === '1';

            this.$watch('show', (value) => {
                if (value) {
                    document.body.classList.add('overflow-y-hidden');
                    if (this.focusable) {
                        setTimeout(() => this.firstFocusable()?.focus(), 100);
                    }
                } else {
                    document.body.classList.remove('overflow-y-hidden');
                }
            });
        },

        onWindowOpen(targetId) {
            if (targetId === this.$el.id) {
                this.show = true;
            }
        },

        onWindowClose(targetId) {
            if (targetId === this.$el.id) {
                this.show = false;
            }
        },

        close() {
            this.show = false;
        },

        focusables() {
            const selector = "a, button, input:not([type='hidden']), textarea, select, details, [tabindex]:not([tabindex='-1'])";
            return [...this.$el.querySelectorAll(selector)].filter((el) => !el.hasAttribute('disabled'));
        },

        firstFocusable() {
            return this.focusables()[0];
        },

        lastFocusable() {
            return this.focusables().slice(-1)[0];
        },

        nextFocusable() {
            return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable();
        },

        prevFocusable() {
            return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable();
        },

        nextFocusableIndex() {
            const list = this.focusables();
            return (list.indexOf(document.activeElement) + 1) % (list.length + 1);
        },

        prevFocusableIndex() {
            const list = this.focusables();
            return (list.indexOf(document.activeElement) - 1 + list.length + 1) % (list.length + 1);
        },
    };
}

/**
 * Komponen modal pratinjau dokumen PDF global.
 */
export function docPreviewModal() {
    return {
        open: false,
        doc: { title: '', url: '', size: '' },

        openPreview(detail) {
            this.doc = detail || { title: '', url: '', size: '' };
            this.open = true;
        },

        close() {
            this.open = false;
        },
    };
}

/**
 * Komponen lightbox pratinjau foto dokumentasi barang.
 */
export function lightboxViewer() {
    return {
        lightboxImg: null,
        closeLightbox() {
            this.lightboxImg = null;
        },
    };
}
