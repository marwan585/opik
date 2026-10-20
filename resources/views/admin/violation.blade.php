@extends('layouts.admin')

@section('title')
    Akses Ditolak
@endsection

@section('content-header')
    <h1>
        <i class="fa fa-ban text-danger"></i>
        Akses Ditolak
        <small>Pelanggaran izin terdeteksi</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Akses Ditolak</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="box box-danger" style="border-top: 3px solid #dd4b39;">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-shield text-danger"></i>&nbsp;
                    Pelanggaran Keamanan Terdeteksi
                </h3>
            </div>
            <div class="box-body" style="padding: 30px;">
                <div class="text-center" style="padding: 20px 0;">
                    <div style="font-size: 72px; color: #dd4b39; line-height: 1;">
                        <i class="fa fa-exclamation-triangle"></i>
                    </div>
                    <h2 style="color: #2c3e50; margin-top: 20px; font-weight: 600;">Menu Ini Dilindungi</h2>
                    <p style="color: #7f8c8d; font-size: 16px; margin-top: 10px; max-width: 480px; margin-left: auto; margin-right: auto;">
                        Anda mencoba mengakses menu yang hanya dapat diakses oleh
                        <strong>Admin Utama (ID 1)</strong>. Akses Anda telah diblokir dan
                        tindakan ini telah dicatat di audit log.
                    </p>
                </div>

                <hr style="border-color: #ecf0f1; margin: 30px 0;">

                <div class="row">
                    <div class="col-sm-6">
                        <div style="background: #f6f8fa; border-radius: 4px; padding: 15px;">
                            <h4 style="margin-top: 0; color: #34495e; font-size: 14px; text-transform: uppercase; letter-spacing: 0.04em;">
                                <i class="fa fa-user"></i> Akun Anda
                            </h4>
                            <table class="table table-condensed" style="margin-bottom: 0;">
                                <tr>
                                    <td style="border: none; color: #7f8c8d;">ID Admin</td>
                                    <td style="border: none;"><code>#{{ $adminId }}</code></td>
                                </tr>
                                <tr>
                                    <td style="border: none; color: #7f8c8d;">Username</td>
                                    <td style="border: none;"><code>{{ $adminName }}</code></td>
                                </tr>
                                <tr>
                                    <td style="border: none; color: #7f8c8d;">Waktu</td>
                                    <td style="border: none;"><code>{{ \Carbon\Carbon::now()->toDateTimeString() }}</code></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div style="background: #fef5f5; border-radius: 4px; padding: 15px;">
                            <h4 style="margin-top: 0; color: #c0392b; font-size: 14px; text-transform: uppercase; letter-spacing: 0.04em;">
                                <i class="fa fa-lock"></i> Rute Dilindungi
                            </h4>
                            <table class="table table-condensed" style="margin-bottom: 0;">
                                <tr>
                                    <td style="border: none; color: #7f8c8d;">Rute dicegah</td>
                                    <td style="border: none;"><code>/{{ $attemptedRoute }}</code></td>
                                </tr>
                                <tr>
                                    <td style="border: none; color: #7f8c8d;">Hanya pemilik</td>
                                    <td style="border: none;"><code>#{{ $ownerId }}</code></td>
                                </tr>
                                <tr>
                                    <td style="border: none; color: #7f8c8d;">Audit log</td>
                                    <td style="border: none;"><span class="badge badge-warning">TERCATAT</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning" style="margin-top: 25px; margin-bottom: 0;">
                    <h4 style="margin-top: 0;"><i class="fa fa-warning"></i> Catatan</h4>
                    Hanya <strong>Admin Utama (ID 1)</strong> yang dapat:
                    <ul style="margin-bottom: 0; margin-top: 8px;">
                        <li>Mengakses atau memodifikasi Locations, Databases, Nodes, Mounts, Nests, dan Settings</li>
                        <li>Menghapus server atau user</li>
                        <li>Masuk ke server milik user lain</li>
                        <li>Mengubah menu admin</li>
                    </ul>
                    Jika Anda merasa seharusnya memiliki akses, hubungi <strong>Admin Utama</strong>.
                </div>
            </div>
            <div class="box-footer text-center" style="padding: 20px;">
                <a href="{{ route('admin.index') }}" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
