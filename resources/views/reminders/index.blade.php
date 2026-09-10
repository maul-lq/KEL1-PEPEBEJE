@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Daftar Pengingat & Alarm Jadwal (FR 12.0)</h1>
            <p class="text-sm text-zinc-600 mt-1">Notifikasi otomatis jadwal pengiriman barang, H-2 jatuh tempo, dan tembusan SPK</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-zinc-300 shadow-xs overflow-hidden">
        <div class="divide-y divide-zinc-200">
            @forelse ($reminders as $r)
                <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 {{ $r->is_dismissed ? 'opacity-60 bg-zinc-50' : 'bg-white' }}">
                    <div class="space-y-1">
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-zinc-900 text-white uppercase">{{ str_replace('_', ' ', $r->tipe) }}</span>
                            <span class="text-xs text-zinc-500">Untuk Role: <strong>{{ strtoupper($r->target_role ?? 'SEMUA') }}</strong></span>
                            @if($r->tanggal_ingat)
                                <span class="text-xs text-zinc-500">• Tanggal: {{ \Carbon\Carbon::parse($r->tanggal_ingat)->format('d M Y') }}</span>
                            @endif
                        </div>
                        <h4 class="text-base font-bold text-zinc-900">{{ $r->judul }}</h4>
                        <p class="text-xs text-zinc-600">{{ $r->pesan }}</p>
                    </div>

                    <div class="shrink-0 flex items-center space-x-2">
                        @if($r->pengadaan_id)
                            <a href="{{ route('pengadaan.show', $r->pengadaan_id) }}" class="px-3.5 py-2 bg-zinc-900 text-white text-xs font-bold rounded-lg">
                                Buka Berkas
                            </a>
                        @endif
                        @if(!$r->is_dismissed)
                            <form method="POST" action="{{ route('reminders.dismiss', $r) }}">
                                @csrf
                                <button type="submit" class="px-3.5 py-2 bg-zinc-200 hover:bg-zinc-300 text-zinc-800 text-xs font-bold rounded-lg cursor-pointer">
                                    Tandai Selesai
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-10 text-center text-zinc-500 text-sm">Tidak ada notifikasi atau alarm aktif.</div>
            @endforelse
        </div>
        <div class="p-4 border-t border-zinc-200">
            {{ $reminders->links() }}
        </div>
    </div>
</div>
@endsection
