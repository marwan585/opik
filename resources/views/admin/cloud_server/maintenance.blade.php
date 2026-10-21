@extends('layouts.admin')

@section('title')
    Maintenance Mode
@endsection

@section('content-header')
    <h1>
        <i class="fa fa-wrench text-warning"></i>
        Maintenance Mode
        <small>Kunci akses panel — hanya Admin Utama (ID 1) yang bisa masuk</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Cloud Server · Maintenance</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="box {{ $enabled ? 'box-danger' : 'box-warning' }}" style="border-top: 3px solid {{ $enabled ? '#ef4444' : '#f59e0b' }};">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-power-off"></i> &nbsp;Status Maintenance</h3>
            </div>
            <form method="POST" action="{{ route('admin.cloud.maintenance') }}">
                @csrf
                <div class="box-body">
                    @if($enabled)
                        <div class="alert alert-danger" style="margin-bottom: 20px;">
                            <strong><i class="fa fa-exclamation-triangle"></i> Mode Maintenance AKTIF</strong><br>
                            Semua user dan admin biasa (id != 1) TIDAK bisa mengakses panel SPA. Hanya Anda (admin utama) yang bisa.
                        </div>
                    @else
                        <div class="alert alert-warning" style="margin-bottom: 20px;">
                            <strong><i class="fa fa-info-circle"></i> Mode Maintenance NONAKTIF</strong><br>
                            Panel berjalan normal. Semua user bisa login dan mengakses dashboard.
                        </div>
                    @endif

                    <div class="form-group">
                        <label for="enabled">
                            <input type="checkbox" name="enabled" id="enabled" value="1" {{ $enabled ? 'checked' : '' }}>
                            <strong>Aktifkan Maintenance Mode</strong>
                            <span class="text-muted small">— kunci semua akses panel kecuali admin utama</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="message"><strong>Pesan Maintenance (opsional)</strong></label>
                        <textarea class="form-control" name="message" id="message" rows="4"
                                  maxlength="1000"
                                  placeholder="Contoh: Panel sedang maintenance untuk upgrade. Kami akan kembali dalam 30 menit.">{{ old('message', $message) }}</textarea>
                        <p class="text-muted small">Pesan ini akan ditampilkan ke user yang mencoba akses panel saat maintenance aktif.</p>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-{{ $enabled ? 'danger' : 'warning' }}">
                        <i class="fa fa-save"></i> &nbsp;Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-5">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> &nbsp;Cara Kerja Maintenance</h3>
            </div>
            <div class="box-body">
                <ul class="small" style="padding-left: 18px; line-height: 1.7;">
                    <li><strong>Saat AKTIF:</strong>
                        <ul>
                            <li>User biasa dan admin biasa (id != 1) tidak bisa buka panel SPA.</li>
                            <li>Mereka akan lihat halaman maintenance.</li>
                            <li>Owner (Anda, ID 1) tetap bisa akses semua menu termasuk admin.</li>
                            <li>API endpoints tetap aktif — server yang sudah jalan tetap berjalan normal.</li>
                            <li>Wings dan koneksi daemon tetap berfungsi.</li>
                        </ul>
                    </li>
                    <li><strong>Saat NONAKTIF:</strong>
                        <ul>
                            <li>Semua user bisa login dan akses panel seperti biasa.</li>
                        </ul>
                    </li>
                    <li><strong>Catatan:</strong> Mode ini hanya mengunci akses UI panel, bukan server game yang sedang berjalan. Server game tetap aktif.</li>
                </ul>
            </div>
        </div>

        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-lightbulb-o"></i> &nbsp;Tips Penggunaan</h3>
            </div>
            <div class="box-body">
                <ol class="small" style="line-height: 1.7;">
                    <li>Aktifkan maintenance sebelum update panel atau migrasi database.</li>
                    <li>Tulis pesan yang jelas, misal: <em>"Maintenance 30 menit untuk upgrade keamanan"</em>.</li>
                    <li>Setelah selesai, jangan lupa <strong>nonaktifkan</strong> maintenance kembali.</li>
                    <li>Maintenance tidak akan menggangu server game yang sudah running — mereka tetap online.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
