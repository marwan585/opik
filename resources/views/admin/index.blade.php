@extends('layouts.admin')

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Administrative Overview<small>A quick glance at your Cloud Server system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Index</li>
    </ol>
@endsection

@section('content')
{{-- ========================================================================
  Cloud Server — Admin Dashboard (enhanced)
  Adds: stat widgets, recent activity preview, system info card.
  ======================================================================== --}}
<div class="row">
    {{-- Welcome / hero card --}}
    <div class="col-md-12">
        <div class="box box-primary" style="border-top: 3px solid #3c8dbc;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-tachometer"></i> &nbsp;Welcome, {{ \Pterodactyl\Models\User::getCurrentUser()->username ?? 'Admin' }}</h3>
            </div>
            <div class="box-body" style="background: linear-gradient(135deg, #1e2a3a 0%, #2c3e50 100%); color: #ecf0f1;">
                <div class="row">
                    <div class="col-sm-7">
                        <h2 style="color: #fff; margin-top: 0;">Cloud Server Control Panel</h2>
                        <p style="color: #bdc3c7;">Manage your infrastructure, monitor servers, and keep your deployment secure. Only the super admin (ID 1) can modify critical system settings.</p>
                        <p style="color: #95a5a6; font-size: 12px;">
                            <i class="fa fa-shield"></i> Auto-suspend protection is active &nbsp;|&nbsp;
                            <i class="fa fa-lock"></i> Permission fence is active
                        </p>
                    </div>
                    <div class="col-sm-5 text-center">
                        <div style="font-size: 64px; color: #3498db; line-height: 1;"><i class="fa fa-cloud"></i></div>
                        <div style="color: #ecf0f1; margin-top: 8px; font-weight: 600;">{{ config('app.name', 'Cloud Server') }}</div>
                        <div style="color: #95a5a6; font-size: 11px;">Panel v{{ config('app.version') }} · Fork v{{ config('app.fork-version') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Stat widgets --}}
<div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box" style="background: linear-gradient(135deg, #3c8dbc 0%, #2c5f8a 100%); color: #fff;">
            <span class="info-box-icon" style="background: rgba(0,0,0,0.15);"><i class="fa fa-server"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Servers</span>
                <span class="info-box-number">{{ \Pterodactyl\Models\Server::count() }}</span>
                <span class="info-box-text" style="font-size: 11px; opacity: 0.85;">
                    Active: {{ \Pterodactyl\Models\Server::whereNull('status')->count() }} ·
                    Suspended: {{ \Pterodactyl\Models\Server::where('status', \Pterodactyl\Models\Server::STATUS_SUSPENDED)->count() }}
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box" style="background: linear-gradient(135deg, #00a65a 0%, #00753a 100%); color: #fff;">
            <span class="info-box-icon" style="background: rgba(0,0,0,0.15);"><i class="fa fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Users</span>
                <span class="info-box-number">{{ \Pterodactyl\Models\User::count() }}</span>
                <span class="info-box-text" style="font-size: 11px; opacity: 0.85;">
                    Admins: {{ \Pterodactyl\Models\User::where('root_admin', 1)->count() }}
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box" style="background: linear-gradient(135deg, #f39c12 0%, #c97f0a 100%); color: #fff;">
            <span class="info-box-icon" style="background: rgba(0,0,0,0.15);"><i class="fa fa-sitemap"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Nodes</span>
                <span class="info-box-number">{{ \Pterodactyl\Models\Node::count() }}</span>
                <span class="info-box-text" style="font-size: 11px; opacity: 0.85;">
                    Locations: {{ \Pterodactyl\Models\Location::count() }}
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box" style="background: linear-gradient(135deg, #dd4b39 0%, #a83524 100%); color: #fff;">
            <span class="info-box-icon" style="background: rgba(0,0,0,0.15);"><i class="fa fa-database"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Databases</span>
                <span class="info-box-number">{{ \Pterodactyl\Models\Database::count() }}</span>
                <span class="info-box-text" style="font-size: 11px; opacity: 0.85;">
                    Hosts: {{ \Pterodactyl\Models\DatabaseHost::count() }}
                </span>
            </div>
        </div>
    </div>
</div>

{{-- System info + actions row --}}
<div class="row">
    <div class="col-xs-12">
        <div class="box
            @if($version->isLatestPanel())
                box-success
            @else
                box-danger
            @endif
        ">
            <div class="box-header with-border">
                <h3 class="box-title">System Information</h3>
            </div>
            <div class="box-body">
                @if ($version->isLatestPanel())
                    You are running <strong>Cloud Server</strong> (fork version <code>{{ config('app.fork-version') }}</code>) based on Panel version <code>{{ config('app.version') }}</code>. Your panel is up-to-date!
                @else
                    Your panel is <strong>not up-to-date!</strong> The latest version is <code>{{ $version->getPanel() }}</code> and you are currently running version <code>{{ config('app.version') }}</code>.
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDiscord() }}"><button class="btn btn-warning" style="width:100%;"><i class="fa fa-fw fa-support"></i> Get Help <small>(via Discord)</small></button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://pterodactyl.io"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-link"></i> Documentation</button></a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://github.com/marwan585/opik"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-github"></i> GitHub</button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDonations() }}"><button class="btn btn-success" style="width:100%;"><i class="fa fa-fw fa-money"></i> Support the Project</button></a>
    </div>
</div>
@endsection
