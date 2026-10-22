@extends('layouts.tes_app')

@section('title', 'Manajemen Role & Hak Akses Fitur - SIM-PBJ')

@section('content')
<section>
    <h2>Manajemen Role & Matriks Hak Akses Fitur (Permissions)</h2>
    <p>Halaman ini dikhususkan bagi Administrator untuk mengelola master peran pengguna, menambah peran kustom baru (misal: <em>auditor</em>, <em>inspektorat</em>), serta mengatur hak akses fitur secara dinamis.</p>
    <p><strong>Catatan Proteksi:</strong> Sembilan role sistem institusi (seperti <em>admin</em>, <em>user</em>, <em>ppbj</em>, <em>ppk</em>, <em>pp</em>, dll.) diproteksi sebagai <code>Role Sistem</code> sehingga tidak dapat dihapus melalui antarmuka web demi menjamin integritas alur PBJ.</p>
</section>

<section>
    <fieldset>
        <legend><strong>Tambah Peran (Role) Baru</strong></legend>
        <form action="{{ route('admin.roles.store') }}" method="POST">
            @csrf
            <p>
                <label for="nama_role"><strong>Nama Kode Role (Slug huruf kecil & garis bawah):</strong></label><br>
                <input type="text" id="nama_role" name="nama_role" value="{{ old('nama_role') }}" required placeholder="Contoh: auditor_internal">
            </p>

            <p>
                <label for="label"><strong>Nama Tampilan Role (Label):</strong></label><br>
                <input type="text" id="label" name="label" value="{{ old('label') }}" required placeholder="Contoh: Auditor Internal PBJ">
            </p>

            <p>
                <label for="deskripsi"><strong>Deskripsi Peran:</strong></label><br>
                <textarea id="deskripsi" name="deskripsi" rows="2" cols="50" placeholder="Deskripsi tugas dan wewenang peran ini...">{{ old('deskripsi') }}</textarea>
            </p>

            <fieldset>
                <legend><strong>Pilih Hak Akses Fitur (Permissions) Awal:</strong></legend>
                @foreach($permissionsByModule as $module => $modulePermissions)
                    <fieldset>
                        <legend><strong>Modul: {{ strtoupper($module) }}</strong></legend>
                        @foreach($modulePermissions as $perm)
                            <label>
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}">
                                <strong>{{ $perm->label }}</strong> <em>({{ $perm->slug }})</em> - {{ $perm->deskripsi }}
                            </label><br>
                        @endforeach
                    </fieldset>
                    <br>
                @endforeach
            </fieldset>

            <p>
                <button type="submit"><strong>Simpan & Buat Role Baru</strong></button>
            </p>
        </form>
    </fieldset>
</section>

<hr>

<section>
    <h3>Daftar Peran Pengguna & Matriks Hak Akses</h3>

    <table border="1" cellpadding="6" cellspacing="0">
        <thead>
            <tr>
                <th>No.</th>
                <th>Kode & Nama Role</th>
                <th>Tipe Peran</th>
                <th>Jumlah Pengguna</th>
                <th>Hak Akses Aktif (Permissions)</th>
                <th>Atur Ulang Hak Akses & Data Role</th>
                <th>Aksi Hapus</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roles as $index => $role)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $role->label }}</strong><br>
                        <code>{{ $role->nama_role }}</code><br>
                        <small>{{ $role->deskripsi }}</small>
                    </td>
                    <td>
                        @if($role->is_system)
                            <strong>SISTEM (TERKUNCI)</strong>
                        @else
                            <em>Kustom</em>
                        @endif
                    </td>
                    <td>
                        {{ $role->users_count }} Pengguna
                    </td>
                    <td>
                        @if($role->nama_role === 'admin')
                            <strong>Semua Akses (Super Administrator)</strong>
                        @elseif($role->permissions->isEmpty())
                            <em>Belum memiliki hak akses khusus</em>
                        @else
                            <ul>
                                @foreach($role->permissions as $p)
                                    <li><strong>{{ $p->label }}</strong> <small>({{ $p->slug }})</small></li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td>
                        {{-- Form Ubah Data & Hak Akses Role --}}
                        <form action="{{ route('admin.roles.update', $role->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <fieldset>
                                <legend>Ubah Hak Akses: {{ $role->label }}</legend>
                                <p>
                                    <label><strong>Label:</strong></label><br>
                                    <input type="text" name="label" value="{{ $role->label }}" required>
                                </p>
                                <p>
                                    <label><strong>Deskripsi:</strong></label><br>
                                    <textarea name="deskripsi" rows="2" cols="30">{{ $role->deskripsi }}</textarea>
                                </p>

                                @if($role->nama_role === 'admin')
                                    <p><em>Role Administrator selalu memiliki seluruh hak akses sistem.</em></p>
                                @else
                                    <details>
                                        <summary><strong>Klik untuk Membuka Matriks Hak Akses</strong></summary>
                                        @foreach($permissionsByModule as $module => $modulePermissions)
                                            <p><strong>Modul {{ strtoupper($module) }}:</strong></p>
                                            @foreach($modulePermissions as $perm)
                                                <label>
                                                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                                        {{ $role->permissions->contains('id', $perm->id) ? 'checked' : '' }}>
                                                    {{ $perm->label }} <small>({{ $perm->slug }})</small>
                                                </label><br>
                                            @endforeach
                                        @endforeach
                                    </details>
                                @endif

                                <p>
                                    <button type="submit">Simpan Perubahan Role</button>
                                </p>
                            </fieldset>
                        </form>
                    </td>
                    <td>
                        @if($role->is_system)
                            <small><em>Role sistem dilindungi dan tidak dapat dihapus.</em></small>
                        @else
                            <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Hapus Role</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
@endsection
