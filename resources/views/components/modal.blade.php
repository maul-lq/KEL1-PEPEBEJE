@props([
    'id',
    'title' => null,
    'subtitle' => null,
    'maxWidth' => 'lg',
    'show' => false,
    'focusable' => true,
])

@php
    $maxWidthClasses = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
        '5xl' => 'sm:max-w-5xl',
        'full' => 'sm:max-w-[95vw]',
    ];

    $widthClass = $maxWidthClasses[$maxWidth] ?? $maxWidthClasses['lg'];
@endphp

<div
    x-data="{
        show: @js($show),
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';
            return [...$el.querySelectorAll(selector)].filter(el => ! el.hasAttribute('disabled'));
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return (this.focusables().indexOf(document.activeElement) - 1 + this.focusables().length + 1) % (this.focusables().length + 1) },
    }"
    x-init="
        $watch('show', value => {
            if (value) {
                document.body.classList.add('overflow-y-hidden');
                {{ $focusable ? 'setTimeout(() => firstFocusable()?.focus(), 100)' : '' }}
            } else {
                document.body.classList.remove('overflow-y-hidden');
            }
        });
    "
    x-on:open-modal.window="$event.detail === '{{ $id }}' ? show = true : null"
    x-on:close-modal.window="$event.detail === '{{ $id }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey ? prevFocusable().focus() : nextFocusable().focus()"
    x-show="show"
    id="{{ $id }}"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    @if($title) aria-labelledby="modal-title-{{ $id }}" @endif
>
    <!-- 1. Backdrop Blur Overlay -->
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
        @click="show = false"
        aria-hidden="true"
    ></div>

    <!-- 2. Modal Dialog Card -->
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        @click.stop
        class="relative w-full {{ $widthClass }} my-8 overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-200/80 transition-all flex flex-col max-h-[90vh]"
    >
        <!-- Modal Header -->
        @if(isset($header))
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                {{ $header }}
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600 rounded-lg p-1 transition-colors">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @elseif($title)
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                <div>
                    <h3 id="modal-title-{{ $id }}" class="text-lg font-bold text-[#213153] leading-snug">
                        {{ $title }}
                    </h3>
                    @if($subtitle)
                        <p class="text-xs text-slate-500 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600 rounded-lg p-1 transition-colors" title="Tutup">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <!-- Modal Body (Scrollable if content overflows) -->
        <div class="p-6 overflow-y-auto grow">
            {{ $slot }}
        </div>

        <!-- Modal Footer -->
        @if(isset($footer))
            <div class="px-6 py-3.5 bg-slate-50/80 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0 rounded-b-2xl">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
