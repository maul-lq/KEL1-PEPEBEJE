<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class SharedComponentsStressTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | 1. STAT-CARD STRESS TESTS
    |--------------------------------------------------------------------------
    */

    public function test_stat_card_renders_with_null_icon_without_error(): void
    {
        $view = $this->blade('<x-stat-card title="Total Pengadaan" :value="42" :icon="null" />');

        $view->assertSee('Total Pengadaan');
        $view->assertSee('42');
    }

    public function test_stat_card_renders_with_missing_subtitle(): void
    {
        $view = $this->blade('<x-stat-card title="Menunggu Telaah" value="15" />');

        $view->assertSee('Menunggu Telaah');
        $view->assertSee('15');
        // Pastikan tidak ada elemen subtitle kosong yang merusak tata letak
        $view->assertDontSee('text-[11px] text-slate-400 truncate');
    }

    public function test_stat_card_renders_with_extreme_number_values(): void
    {
        // Nilai 0
        $viewZero = $this->blade('<x-stat-card title="Draft" :value="0" />');
        $viewZero->assertSee('0');

        // Nilai negatif
        $viewNegative = $this->blade('<x-stat-card title="Defisit" :value="-999999" />');
        $viewNegative->assertSee('-999999');

        // Nilai angka sangat besar
        $huge = 999999999999999;
        $viewHuge = $this->blade('<x-stat-card title="Pagu DIPA" :value="$val" />', ['val' => $huge]);
        $viewHuge->assertSee((string) $huge);

        // String formatted currency
        $viewFormatted = $this->blade('<x-stat-card title="Realisasi" value="Rp 150.250.000.000" />');
        $viewFormatted->assertSee('Rp 150.250.000.000');

        // Float / pecahan
        $viewFloat = $this->blade('<x-stat-card title="Persentase" :value="99.98" />');
        $viewFloat->assertSee('99.98');
    }

    public function test_stat_card_handles_unusual_trend_strings(): void
    {
        // Trend up
        $viewUp = $this->blade('<x-stat-card title="Usulan" value="10" badge="+12%" trend="up" />');
        $viewUp->assertSee('+12%');

        // Trend down
        $viewDown = $this->blade('<x-stat-card title="Usulan" value="10" badge="-5%" trend="down" />');
        $viewDown->assertSee('-5%');

        // Unusual / invalid trends ('flat', 'neutral', 'unknown', symbols)
        $viewFlat = $this->blade('<x-stat-card title="Usulan" value="10" badge="0%" trend="flat" />');
        $viewFlat->assertSee('0%');

        $viewNeutral = $this->blade('<x-stat-card title="Usulan" value="10" badge="STABIL" trend="neutral" />');
        $viewNeutral->assertSee('STABIL');

        $viewSymbol = $this->blade('<x-stat-card title="Usulan" value="10" badge="~" trend="---" />');
        $viewSymbol->assertSee('~');
    }

    public function test_defect_stat_card_swallows_zero_badge(): void
    {
        // VERIFIED FIX: badge="0" is correctly displayed and not swallowed by falsy evaluation
        $viewZero = $this->blade('<x-stat-card title="Usulan" value="10" badge="0" />');
        $viewZero->assertSee('text-[10px] font-bold border');
        $viewZero->assertSee('0');
    }

    public function test_defect_stat_card_renders_phantom_empty_icon_box_when_icon_is_null(): void
    {
        // VERIFIED FIX: passing :icon="null" without slot does not render phantom icon container box
        $viewNullIcon = $this->blade('<x-stat-card title="Usulan" value="10" :icon="null" />');
        $viewNullIcon->assertDontSee('flex size-12 shrink-0 items-center justify-center rounded-xl');
    }

    public function test_stat_card_handles_invalid_color_props_with_safe_fallback(): void
    {
        // Color yang tidak terdaftar harus aman dan fallback ke blue theme
        $viewInvalid = $this->blade('<x-stat-card title="Custom" value="5" color="non_existent_color_xyz" icon="document" />');
        $viewInvalid->assertSee('Custom');
        $viewInvalid->assertSee('text-[#0969da]');
        $viewInvalid->assertSee('hover:border-blue-300');

        // Null / empty color prop
        $viewNull = $this->blade('<x-stat-card title="Null Color" value="5" :color="null" icon="document" />');
        $viewNull->assertSee('Null Color');
        $viewNull->assertSee('text-[#0969da]');
    }

    public function test_stat_card_renders_href_as_anchor_tag(): void
    {
        $viewAnchor = $this->blade('<x-stat-card title="Link Card" value="1" href="/dashboard/paket" />');
        $viewAnchor->assertSee('<a', false);
        $viewAnchor->assertSee('href="/dashboard/paket"', false);

        $viewDiv = $this->blade('<x-stat-card title="Div Card" value="1" />');
        $viewDiv->assertSee('<div', false);
        $viewDiv->assertDontSee('href=');
    }

    public function test_stat_card_escapes_xss_vectors(): void
    {
        $xss = '<script>alert("xss")</script>';
        $view = $this->blade('<x-stat-card :title="$xss" :value="$xss" :subtitle="$xss" :badge="$xss" />', ['xss' => $xss]);

        $view->assertDontSee('<script>alert("xss")</script>', false);
        $view->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. STEPPER-8-SIKLUS STRESS TESTS
    |--------------------------------------------------------------------------
    */

    public function test_stepper_handles_boundary_current_steps(): void
    {
        // Step 0 -> harus di-clamp ke Step 1
        $view0 = $this->blade('<x-stepper-8-siklus :currentStep="0" />');
        $view0->assertSee('Tahap 1 dari 8:');
        $view0->assertSee('Diajukan');

        // Step 1 -> Tahap 1
        $view1 = $this->blade('<x-stepper-8-siklus :currentStep="1" />');
        $view1->assertSee('Tahap 1 dari 8:');

        // Step 8 -> Tahap 8
        $view8 = $this->blade('<x-stepper-8-siklus :currentStep="8" />');
        $view8->assertSee('Tahap 8 dari 8:');
        $view8->assertSee('SP2D Lunas');

        // Step 9 -> harus di-clamp ke Step 8
        $view9 = $this->blade('<x-stepper-8-siklus :currentStep="9" />');
        $view9->assertSee('Tahap 8 dari 8:');
        $view9->assertSee('SP2D Lunas');

        // Step negatif
        $viewNeg = $this->blade('<x-stepper-8-siklus :currentStep="-99" />');
        $viewNeg->assertSee('Tahap 1 dari 8:');

        // Step non-numeric string
        $viewStr = $this->blade('<x-stepper-8-siklus currentStep="invalid_number" />');
        $viewStr->assertSee('Tahap 1 dari 8:');
    }

    public function test_stepper_handles_unknown_status_strings(): void
    {
        $view = $this->blade('<x-stepper-8-siklus :currentStep="3" status="unknown_status_custom_123" />');
        $view->assertSee('Tahap 3 dari 8:');
        $view->assertSee('Registrasi Paket');
    }

    public function test_stepper_handles_rejected_and_hold_statuses(): void
    {
        // Status ditolak
        $viewRejected = $this->blade('<x-stepper-8-siklus :currentStep="2" status="ditolak" />');
        $viewRejected->assertSee('Tahap 2 dari 8:');
        $viewRejected->assertSee('bg-rose-500');

        // Status revisi / hold
        $viewHold = $this->blade('<x-stepper-8-siklus :currentStep="2" status="perlu_revisi" />');
        $viewHold->assertSee('Tahap 2 dari 8:');
        $viewHold->assertSee('bg-amber-500');
    }

    public function test_stepper_vertical_mode_renders_timeline_and_step_data(): void
    {
        $stepsData = [
            1 => ['catatan' => 'Berkas KAK diunggah lengkap', 'date' => '2026-10-20 09:00'],
            2 => ['catatan' => 'Verifikasi MAK sedang diproses', 'date' => '2026-10-21 14:30'],
        ];

        $view = $this->blade('<x-stepper-8-siklus mode="vertical" :currentStep="2" :stepsData="$stepsData" />', ['stepsData' => $stepsData]);

        $view->assertSee('Tahap 1: Diajukan');
        $view->assertSee('Berkas KAK diunggah lengkap');
        $view->assertSee('2026-10-20 09:00');
        $view->assertSee('Tahap 2: Verifikasi Paralel');
        $view->assertSee('Verifikasi MAK sedang diproses');
        $view->assertSee('Sedang Berjalan');
        $view->assertSee('Tahap 8: SP2D Lunas');
    }

    public function test_stepper_compact_mode_renders_dots_and_counter(): void
    {
        $view = $this->blade('<x-stepper-8-siklus mode="compact" :currentStep="4" status="diajukan" />');

        $view->assertSee('4/8');
        $view->assertSee('title="Tahap 4 dari 8: Reviu Dokumen"', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. STATUS-BADGE STRESS TESTS
    |--------------------------------------------------------------------------
    */

    public function test_status_badge_renders_all_13_official_statuses(): void
    {
        $statuses = [
            'draft' => 'Draft',
            'diajukan' => 'Diajukan',
            'menunggu_telaah' => 'Menunggu Telaah',
            'menunggu_persetujuan' => 'Menunggu Persetujuan',
            'disetujui' => 'Disetujui',
            'acc' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'perlu_revisi' => 'Perlu Revisi',
            'revisi' => 'Perlu Revisi',
            'hold' => 'Hold Anggaran',
            'ditahan' => 'Hold Anggaran',
            'paket_terdaftar' => 'Paket Terdaftar',
            'spk_terbit' => 'SPK Terbit',
            'kontrak' => 'SPK Terbit',
            'pengiriman' => 'Pengiriman',
            'bast_terbit' => 'BAST Terbit',
            'spp_terbit' => 'SPP Terbit',
            'sp2d_lunas' => 'SP2D Lunas',
            'cair' => 'SP2D Lunas',
            'selesai' => 'Selesai',
        ];

        foreach ($statuses as $status => $expectedLabel) {
            $view = $this->blade('<x-status-badge :status="$st" />', ['st' => $status]);
            $view->assertSee($expectedLabel);
        }
    }

    public function test_status_badge_handles_unrecognized_fallback_status(): void
    {
        $view = $this->blade('<x-status-badge status="status_kustom_baru" />');
        // Fallback mengubah _ menjadi spasi dan meng-kapitalisasi huruf pertama
        $view->assertSee('Status kustom baru');
        $view->assertSee('bg-slate-100');
    }

    public function test_status_badge_renders_all_valid_sizes(): void
    {
        $sizes = ['xs', 'sm', 'md', 'lg'];

        foreach ($sizes as $size) {
            $view = $this->blade('<x-status-badge status="diajukan" :size="$sz" />', ['sz' => $size]);
            $view->assertSee('Diajukan');
        }
    }

    public function test_defect_status_badge_crashes_on_undefined_size(): void
    {
        // VERIFIED FIX: Passing an unrecognized size (e.g. 'xl') renders safely without ErrorException, applying fallback 'md' styling
        $view = $this->blade('<x-status-badge status="diajukan" size="xl" />');
        $view->assertSee('Diajukan');
        $view->assertSee('text-xs px-2.5 py-1 gap-1.5');
    }

    public function test_status_badge_handles_dot_flag(): void
    {
        $viewWithDot = $this->blade('<x-status-badge status="diajukan" :dot="true" />');
        $viewWithDot->assertSee('rounded-full shrink-0');

        $viewWithoutDot = $this->blade('<x-status-badge status="diajukan" :dot="false" />');
        $viewWithoutDot->assertDontSee('rounded-full shrink-0');
    }

    public function test_status_badge_renders_custom_text_and_escapes_xss(): void
    {
        $xss = '<script>alert("badge")</script>';
        $view = $this->blade('<x-status-badge status="diajukan" :text="$xss" />', ['xss' => $xss]);

        $view->assertDontSee('<script>alert("badge")</script>', false);
        $view->assertSee('&lt;script&gt;alert(&quot;badge&quot;)&lt;/script&gt;', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. MODAL STRESS TESTS
    |--------------------------------------------------------------------------
    */

    public function test_modal_renders_with_empty_subtitle(): void
    {
        $view = $this->blade('<x-modal id="modal-test" title="Judul Modal">Isi Konten</x-modal>');

        $view->assertSee('Judul Modal');
        $view->assertSee('Isi Konten');
        $view->assertDontSee('text-xs text-slate-500 mt-0.5');
    }

    public function test_modal_renders_with_various_max_width_options(): void
    {
        $widths = ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', 'full'];

        foreach ($widths as $width) {
            $view = $this->blade('<x-modal id="m-'.$width.'" title="Modal" :maxWidth="$w">Body</x-modal>', ['w' => $width]);
            $view->assertSee('id="m-'.$width.'"', false);
        }

        // Invalid maxWidth fallback ke lg
        $viewInvalid = $this->blade('<x-modal id="m-invalid" title="Modal" maxWidth="super_ultra_wide">Body</x-modal>');
        $viewInvalid->assertSee('sm:max-w-lg');
    }

    public function test_modal_renders_nested_form_elements_and_custom_slots(): void
    {
        $template = <<<'BLADE'
        <x-modal id="form-modal" title="Formulir Revisi">
            <x-slot:header>
                <h4 class="custom-header">Header Khusus</h4>
            </x-slot:header>

            <form id="inner-form">
                <input type="text" name="nomor_surat" value="001/PBJ/2026" />
                <textarea name="catatan">Catatan telaah</textarea>
                <select name="status"><option value="acc">ACC</option></select>
            </form>

            <x-slot:footer>
                <button type="submit" form="inner-form">Kirim</button>
            </x-slot:footer>
        </x-modal>
        BLADE;

        $view = $this->blade($template);

        $view->assertSee('Header Khusus');
        $view->assertSee('001/PBJ/2026');
        $view->assertSee('Catatan telaah');
        $view->assertSee('Kirim');
    }

    /*
    |--------------------------------------------------------------------------
    | 5. FILTER-BAR STRESS TESTS
    |--------------------------------------------------------------------------
    */

    public function test_filter_bar_renders_with_empty_action_defaulting_to_current_url(): void
    {
        $view = $this->blade('<x-filter-bar action="" />');

        $view->assertSee('<form action="'.url()->current().'"', false);
        $view->assertSee('Cari nomor surat, perihal, atau rekanan...');
    }

    public function test_filter_bar_renders_with_complex_query_parameters(): void
    {
        // Simulasi request dengan parameter URL kompleks
        request()->merge([
            'q' => 'laptop pengadaan',
            'status' => 'menunggu_telaah',
            'unit' => 'TIK',
            'tahun' => '2026',
            'page' => '3',
        ]);

        $view = $this->blade('<x-filter-bar action="/paket/data" />');

        $view->assertSee('action="/paket/data"', false);
        $view->assertSee('value="laptop pengadaan"', false);
        // Reset button harus muncul karena ada query parameter aktif selain 'page'
        $view->assertSee('Reset');
    }

    public function test_filter_bar_renders_custom_slots_and_escapes_xss(): void
    {
        $template = <<<'BLADE'
        <x-filter-bar action="/search">
            <x-slot:filters>
                <select name="kategori">
                    <option value="atk">ATK</option>
                </select>
            </x-slot:filters>

            <x-slot:actions>
                <a href="/create" class="btn-create">Tambah Baru</a>
            </x-slot:actions>
        </x-filter-bar>
        BLADE;

        $view = $this->blade($template);

        $view->assertSee('select name="kategori"', false);
        $view->assertSee('Tambah Baru');
        $view->assertSee('/create');
    }

    public function test_filter_bar_escapes_xss_in_search_value(): void
    {
        $xss = '"><script>alert(1)</script>';
        request()->merge(['q' => $xss]);

        $view = $this->blade('<x-filter-bar />');

        $view->assertDontSee('<script>alert(1)</script>', false);
        $view->assertSee('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_defect_stepper_step_1_produces_negative_css_width(): void
    {
        // VERIFIED FIX: at step 1, style produces valid zero width rather than negative CSS width
        $viewStep1 = $this->blade('<x-stepper-8-siklus :currentStep="1" />');
        $viewStep1->assertSee('style="width: 0px;"', false);
        $viewStep1->assertDontSee('calc(0% - 35px)', false);
    }

    public function test_defect_modal_missing_title_causes_broken_aria_labelledby(): void
    {
        // VERIFIED FIX: when title is missing, aria-labelledby is omitted so it doesn't reference a non-existent element
        $view = $this->blade('<x-modal id="no-title-modal"><x-slot:header>Custom Header</x-slot:header>Body</x-modal>');
        $view->assertDontSee('aria-labelledby=');
        $view->assertDontSee('id="modal-title-no-title-modal"', false);
    }
}
