@extends('layouts.admin')

@section('title')
    System Monitoring
@endsection

@section('content-header')
    <h1>
        <i class="fa fa-heartbeat text-danger"></i>
        System Monitoring
        <small>Live CPU, RAM, disk &amp; runtime stats</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Cloud Server · System Monitoring</li>
    </ol>
@endsection

@section('content')
{{-- Helper: format bytes --}}
@php
    function fmtBytes($b) {
        if (!is_numeric($b) || $b === null) return '—';
        $u = ['B','KB','MB','GB','TB','PB'];
        $i = 0; while ($b >= 1024 && $i < count($u)-1) { $b /= 1024; $i++; }
        return round($b, 2).' '.$u[$i];
    }
    function pctColor($p) {
        if (!is_numeric($p)) return 'bg-gray';
        if ($p >= 90) return 'bg-red';
        if ($p >= 70) return 'bg-yellow';
        return 'bg-green';
    }
@endphp

<div class="row">
    {{-- CPU / Load Average --}}
    <div class="col-md-6 col-sm-12">
        <div class="box box-success" style="border-top: 3px solid #00a65a;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-microchip"></i> &nbsp;CPU Load</h3>
                <div class="box-tools"><span class="badge badge-success">{{ $cpuCount }} core(s)</span></div>
            </div>
            <div class="box-body">
                @if ($loadAvg)
                    <div class="row text-center">
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ $loadAvg['m1'] }}</h5>
                                <span class="description-text">1 min avg</span>
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ $loadAvg['m5'] }}</h5>
                                <span class="description-text">5 min avg</span>
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ $loadAvg['m15'] }}</h5>
                                <span class="description-text">15 min avg</span>
                            </div>
                        </div>
                    </div>
                    @php $loadPct = $cpuCount > 0 ? min(100, ($loadAvg['m1'] / $cpuCount) * 100) : 0; @endphp
                    <div style="margin-top: 12px;">
                        <p class="small text-muted">Load relative to CPU count: {{ round($loadPct, 1) }}%</p>
                        <div class="progress" style="height: 22px;">
                            <div class="progress-bar {{ pctColor($loadPct) }}" style="width: {{ min(100, $loadPct) }}%; line-height: 22px;">
                                {{ round($loadPct, 1) }}%
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-muted">Load average tidak tersedia (hanya Linux).</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Memory --}}
    <div class="col-md-6 col-sm-12">
        <div class="box box-info" style="border-top: 3px solid #3c8dbc;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-memory"></i> &nbsp;Memory</h3>
            </div>
            <div class="box-body">
                @if ($memInfo)
                    <div class="row text-center">
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ fmtBytes($memInfo['used']) }}</h5>
                                <span class="description-text">Used</span>
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ fmtBytes($memInfo['available']) }}</h5>
                                <span class="description-text">Available</span>
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ fmtBytes($memInfo['total']) }}</h5>
                                <span class="description-text">Total</span>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 12px;">
                        <div class="progress" style="height: 22px;">
                            <div class="progress-bar {{ pctColor($memInfo['used_pct']) }}" style="width: {{ min(100, $memInfo['used_pct']) }}%; line-height: 22px;">
                                {{ round($memInfo['used_pct'], 1) }}%
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-muted">Memory info tidak tersedia (hanya Linux).</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Disk --}}
    <div class="col-md-6 col-sm-12">
        <div class="box box-warning" style="border-top: 3px solid #f39c12;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-hdd"></i> &nbsp;Disk Space</h3>
            </div>
            <div class="box-body">
                @if ($disk)
                    <div class="row text-center">
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ fmtBytes($disk['used']) }}</h5>
                                <span class="description-text">Used</span>
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ fmtBytes($disk['free']) }}</h5>
                                <span class="description-text">Free</span>
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="description-block">
                                <h5 class="description-header">{{ fmtBytes($disk['total']) }}</h5>
                                <span class="description-text">Total</span>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 12px;">
                        <div class="progress" style="height: 22px;">
                            <div class="progress-bar {{ pctColor($disk['used_pct']) }}" style="width: {{ min(100, $disk['used_pct']) }}%; line-height: 22px;">
                                {{ round($disk['used_pct'], 1) }}%
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-muted">Disk info tidak tersedia.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Uptime & Panel Stats --}}
    <div class="col-md-6 col-sm-12">
        <div class="box box-primary" style="border-top: 3px solid #3c8dbc;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-clock-o"></i> &nbsp;Uptime &amp; Panel Stats</h3>
            </div>
            <div class="box-body">
                <p>
                    <strong>System Uptime:</strong>
                    @if ($uptime)
                        {{ floor($uptime / 86400) }} days, {{ floor(($uptime % 86400) / 3600) }} hours, {{ floor(($uptime % 3600) / 60) }} min
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </p>
                <hr style="margin: 10px 0;">
                <div class="row text-center">
                    <div class="col-xs-3">
                        <div class="description-block">
                            <h5 class="description-header">{{ $panelStats['servers_total'] }}</h5>
                            <span class="description-text">Servers</span>
                            <small class="text-muted">({{ $panelStats['servers_active'] }} active, {{ $panelStats['servers_suspended'] }} suspended)</small>
                        </div>
                    </div>
                    <div class="col-xs-3">
                        <div class="description-block">
                            <h5 class="description-header">{{ $panelStats['users_total'] }}</h5>
                            <span class="description-text">Users</span>
                            <small class="text-muted">({{ $panelStats['users_admins'] }} admins)</small>
                        </div>
                    </div>
                    <div class="col-xs-3">
                        <div class="description-block">
                            <h5 class="description-header">{{ $panelStats['nodes_total'] }}</h5>
                            <span class="description-text">Nodes</span>
                        </div>
                    </div>
                    <div class="col-xs-3">
                        <div class="description-block">
                            <h5 class="description-header">@if ($dbSize){{ fmtBytes($dbSize) }}@else—@endif</h5>
                            <span class="description-text">DB Size</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- PHP Runtime --}}
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-code"></i> &nbsp;PHP Runtime</h3>
            </div>
            <div class="box-body">
                <table class="table table-condensed">
                    <tr><td style="width: 40%;">PHP Version</td><td><code>{{ $phpInfo['version'] }}</code></td></tr>
                    <tr><td>SAPI</td><td><code>{{ $phpInfo['sapi'] }}</code></td></tr>
                    <tr><td>Memory Limit</td><td><code>{{ $phpInfo['memory_limit'] }}</code></td></tr>
                    <tr><td>Max Execution Time</td><td><code>{{ $phpInfo['max_exec'] }}s</code></td></tr>
                    <tr><td>OPcache</td><td>
                        @if ($phpInfo['opcache'])
                            <span class="badge badge-success">ENABLED</span>
                        @else
                            <span class="badge badge-default">disabled</span>
                        @endif
                    </td></tr>
                    <tr><td>Extensions Loaded</td><td><small class="text-muted">{{ $phpInfo['ext_count'] }} ext:</small> <code style="font-size: 11px;">{{ $phpInfo['extensions'] }}{{ $phpInfo['ext_count'] > 30 ? ', ...' : '' }}</code></td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- App / Laravel --}}
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cube"></i> &nbsp;App &amp; Laravel</h3>
            </div>
            <div class="box-body">
                <table class="table table-condensed">
                    <tr><td style="width: 40%;">Laravel Version</td><td><code>{{ $appInfo['laravel'] }}</code></td></tr>
                    <tr><td>Panel Version</td><td><code>{{ $appInfo['panel'] }}</code></td></tr>
                    <tr><td>Fork Version</td><td><code>Cloud Server {{ $appInfo['fork'] }}</code></td></tr>
                    <tr><td>Environment</td><td><code>{{ $appInfo['environment'] }}</code></td></tr>
                    <tr><td>Debug Mode</td><td>
                        @if ($appInfo['debug'])
                            <span class="badge badge-warning">ON</span>
                        @else
                            <span class="badge badge-success">off</span>
                        @endif
                    </td></tr>
                    <tr><td>Queue Driver</td><td><code>{{ $appInfo['queue_driver'] }}</code></td></tr>
                    <tr><td>Cache Driver</td><td><code>{{ $appInfo['cache_driver'] }}</code></td></tr>
                    <tr><td>Session Driver</td><td><code>{{ $appInfo['session_driver'] }}</code></td></tr>
                    <tr><td>Database Driver</td><td><code>{{ $appInfo['db_driver'] }}</code></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="info-box bg-gray">
            <span class="info-box-icon"><i class="fa fa-refresh"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Auto-refresh</span>
                <span class="info-box-number" id="refresh-status">Setiap 5 detik</span>
                <span class="info-box-text" style="font-size: 11px; opacity: 0.85;">
                    Last refresh: <code id="last-refresh">{{ $collectedAt }}</code>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 5 seconds — fetch the JSON metrics endpoint and
// re-render the page. We use full-page reload for simplicity so the
// blade template renders fresh data.
(function() {
    let countdown = 5;
    const statusEl = document.getElementById('refresh-status');
    const lastRefreshEl = document.getElementById('last-refresh');

    setInterval(function() {
        countdown--;
        if (countdown <= 0) {
            statusEl.textContent = 'Refreshing...';
            // Reload the page to get fresh data
            window.location.reload();
        } else {
            statusEl.textContent = countdown + ' detik lagi';
        }
    }, 1000);
})();
</script>
@endsection
