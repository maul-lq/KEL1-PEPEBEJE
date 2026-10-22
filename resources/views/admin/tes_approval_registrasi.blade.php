@extends('layouts.tes_app')

@section('title', 'Persetujuan Registrasi Pengguna Baru - SIM-PBJ')

@section('content')
<section>
    <h2>Verifikasi & Persetujuan Registrasi Akun Pengguna Baru</h2>
    <p>Halaman ini dikhususkan bagi Administrator untuk memverifikasi pendaftaran akun baru, memilihkan serta menetapkan hak peran (role) sistem, atau menolak pendaftaran akun yang tidak memenuhi syarat.</p>
</section>

<section>
    <h3>Antrean Pendaftar Akun Menunggu Persetujuan</h3>

    @if($pendingUsers->isEmpty())
        <p><em>Saat ini tidak ada pendaftaran akun baru yang menunggu persetujuan Administrator.</em></p>
    @else
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>NIP</th>
                    <th>Nama Lengkap</th>
                    <th>Email & Username</th>
                    <th>Unit / Jurusan</th>
                    <th>Jabatan</th>
                    <th>No. Kontak</th>
                    <th>Waktu Daftar</th>
                    <th>Persetujuan (ACC) & Tetapkan Role</th>
                    <th>Penolakan Pendaftaran</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingUsers as $index => $user)
                    <tr>
                        <td>{{ $pendingUsers->firstItem() + $index }}</td>
                        <td><strong>{{ $user->nip }}</strong></td>
                        <td>{{ $user->nama }}</td>
                        <td>
                            Email: {{ $user->email }}<br>
                            Username: {{ $user->username }}
                        </td>
                        <td>{{ $user->unit->nama_unit ?? $user->kode_unit }}</td>
                        <td>{{ $user->jabatan }}</td>
                        <td>
                            HP: {{ $user->no_hp }}<br>
                            Telp: {{ $user->no_telp_kantor ?? '-' }}
                        </td>
                        <td>{{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '-' }}</td>
                        <td>
                            {{-- Form Persetujuan (ACC) & Penugasan Role --}}
                            <form action="{{ route('admin.persetujuan-registrasi.acc', $user->id_user) }}" method="POST">
                                @csrf
                                <fieldset>
                                    <legend>Pilihkan Role Pengguna</legend>
                                    <select name="id_role" required>
                                        <option value="">-- Pilih Role --</option>
                                        @foreach($availableRoles as $role)
                                            <option value="{{ $role->id }}">
                                                {{ $role->label }} ({{ $role->nama_role }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <br><br>
                                    <button type="submit"><strong>ACC & Tetapkan Role</strong></button>
                                </fieldset>
                            </form>
                        </td>
                        <td>
                            {{-- Form Penolakan Akun dengan Alasan --}}
                            <form action="{{ route('admin.persetujuan-registrasi.tolak', $user->id_user) }}" method="POST">
                                @csrf
                                <fieldset>
                                    <legend>Alasan Penolakan</legend>
                                    <textarea name="alasan_penolakan" rows="2" cols="25" required placeholder="Tuliskan alasan penolakan..."></textarea>
                                    <br><br>
                                    <button type="submit">Tolak Akun</button>
                                </fieldset>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Paginasi Antrean Menunggu --}}
        <div>
            {{ $pendingUsers->links() }}
        </div>
    @endif
</section>

<hr>

<section>
    <h3>Riwayat Keputusan Registrasi (20 Terakhir)</h3>

    @if($historyUsers->isEmpty())
        <p><em>Belum ada riwayat persetujuan atau penolakan registrasi.</em></p>
    @else
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>NIP</th>
                    <th>Nama Pengguna</th>
                    <th>Unit Asal</th>
                    <th>Status Keputusan</th>
                    <th>Role Ditetapkan</th>
                    <th>Alasan (Jika Ditolak)</th>
                    <th>Diverifikasi Oleh</th>
                    <th>Waktu Keputusan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($historyUsers as $hist)
                    <tr>
                        <td>{{ $hist->nip }}</td>
                        <td>{{ $hist->nama }}</td>
                        <td>{{ $hist->unit->nama_unit ?? $hist->kode_unit }}</td>
                        <td>
                            @if($hist->status_registrasi === \App\Models\User::STATUS_REGISTRASI_DISETUJUI)
                                <strong>DISETUJUI (ACC)</strong>
                            @elseif($hist->status_registrasi === \App\Models\User::STATUS_REGISTRASI_DITOLAK)
                                <strong>DITOLAK</strong>
                            @else
                                {{ strtoupper($hist->status_registrasi) }}
                            @endif
                        </td>
                        <td>
                            @if($hist->status_registrasi === \App\Models\User::STATUS_REGISTRASI_DISETUJUI)
                                {{ $hist->roleEntity->label ?? strtoupper(str_replace('_', ' ', $hist->role)) }}
                            @else
                                <em>-</em>
                            @endif
                        </td>
                        <td>
                            {{ $hist->alasan_penolakan ?? '-' }}
                        </td>
                        <td>
                            {{ $hist->adminApproval->nama ?? 'Administrator' }}
                        </td>
                        <td>
                            {{ $hist->tanggal_approval ? $hist->tanggal_approval->format('d/m/Y H:i') : '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
@endsection
