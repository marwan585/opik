@extends('layouts.admin')

@section('title')
    IP Access List
@endsection

@section('content-header')
    <h1>
        <i class="fa fa-list-alt text-info"></i>
        IP Access List
        <small>IP device yang sudah mengakses panel</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Cloud Server · IP Access List</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-info" style="border-top: 3px solid #3c8dbc;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-globe"></i> &nbsp;Riwayat Login Berdasarkan IP</h3>
                <div class="box-tools">
                    <span class="badge badge-primary">{{ $entries->count() }} entri</span>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                @if ($entries->isEmpty())
                    <p class="text-center text-muted" style="padding: 30px;">
                        Belum ada login yang tercatat. Activity log tabel belum punya event <code>auth:success</code>.
                    </p>
                @else
                    <table class="table table-hover table-striped" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 30px;">#</th>
                                <th>IP Address</th>
                                <th>User</th>
                                <th>Login Count</th>
                                <th>User-Agent</th>
                                <th>First Seen</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $i => $entry)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <code style="font-size: 13px;">{{ $entry->ip }}</code>
                                        {{-- IP geolocation is not done here; add a MaxMind/GeoIP lookup if needed. --}}
                                    </td>
                                    <td>
                                        @if ($entry->user)
                                            <a href="{{ route('admin.users.view', $entry->user->id) }}">
                                                <strong>{{ $entry->user->username }}</strong>
                                            </a>
                                            <br>
                                            <small class="text-muted">{{ $entry->user->email }}</small>
                                        @else
                                            <span class="text-muted">— user deleted —</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $entry->login_count > 50 ? 'danger' : ($entry->login_count > 10 ? 'warning' : 'success') }}">
                                            {{ $entry->login_count }}×
                                        </span>
                                    </td>
                                    <td style="max-width: 280px;">
                                        <small class="text-muted" style="word-break: break-word; display: inline-block;">
                                            {{ $entry->user_agent ?: '—' }}
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ \Carbon\Carbon::parse($entry->first_seen)->diffForHumans() }}
                                            <br>
                                            <code>{{ $entry->first_seen }}</code>
                                        </small>
                                    </td>
                                    <td>
                                        <strong class="text-primary">
                                            {{ \Carbon\Carbon::parse($entry->last_seen)->diffForHumans() }}
                                        </strong>
                                        <br>
                                        <code>{{ $entry->last_seen }}</code>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="box-footer">
                <p class="small text-muted" style="margin: 0;">
                    <i class="fa fa-info-circle"></i>
                    Data diambil dari tabel <code>activity_logs</code> dengan event
                    <code>auth:success</code>. Maks 500 entri terbaru.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
