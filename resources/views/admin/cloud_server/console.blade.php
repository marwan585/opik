@extends('layouts.admin')

@section('title')
    Admin Console
@endsection

@section('content-header')
    <h1>
        <i class="fa fa-terminal text-primary"></i>
        Admin Console
        <small>Cleanup, update, dan restart semuanya dalam 1 klik — owner only</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Cloud Server · Admin Console</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        @if (session('console_results'))
            <div class="box box-success" style="border-top: 3px solid #10b981;">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-check-circle"></i> &nbsp;Result Log</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
                    </div>
                </div>
                <div class="box-body" style="background:#1e1e1e;color:#f8f8f2;padding:14px;font-family:monospace;font-size:12px;max-height:400px;overflow-y:auto;">
                    @foreach (session('console_results') as $line)
                        <div style="margin-bottom:4px;line-height:1.5;">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="box box-danger" style="border-top: 3px solid #ef4444;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-bolt"></i> &nbsp;Quick Actions</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small" style="margin-bottom: 18px;">
                    <i class="fa fa-warning text-warning"></i>
                    Aksi di bawah ini akan mempengaruhi seluruh panel. Pastikan tidak ada user aktif sebelum menjalankan.
                    Backup otomatis dilakukan sebelum update.
                </p>

                <div class="row">
                    <div class="col-md-4" style="margin-bottom: 12px;">
                        <form method="POST" action="{{ route('admin.cloud.console.cleanup') }}">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-block btn-lg" onclick="return confirm('Hapus semua file lama (cache, bundle JS, log, sessions)?')">
                                <i class="fa fa-broom"></i>
                                <div style="font-size:13px;font-weight:600;margin-top:4px;">Cleanup Old Files</div>
                                <div style="font-size:10px;font-weight:normal;opacity:0.85;">Hapus cache, bundle lama, log, sessions</div>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4" style="margin-bottom: 12px;">
                        <form method="POST" action="{{ route('admin.cloud.console.update') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block btn-lg" onclick="return confirm('Update panel dari GitHub marwan585/opik? Panel akan masuk maintenance mode sebentar.')">
                                <i class="fa fa-cloud-download"></i>
                                <div style="font-size:13px;font-weight:600;margin-top:4px;">Update from GitHub</div>
                                <div style="font-size:10px;font-weight:normal;opacity:0.85;">Download + sync + migrate + restart</div>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4" style="margin-bottom: 12px;">
                        <form method="POST" action="{{ route('admin.cloud.console.restart') }}">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-block btn-lg" onclick="return confirm('Restart semua service (queue, PHP-FPM, nginx, bot worker)?')">
                                <i class="fa fa-refresh"></i>
                                <div style="font-size:13px;font-weight:600;margin-top:4px;">Restart All Services</div>
                                <div style="font-size:10px;font-weight:normal;opacity:0.85;">Queue + PHP-FPM + nginx + bot</div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> &nbsp;Detail Aksi</h3>
            </div>
            <div class="box-body" style="padding:0;">
                <table class="table table-condensed" style="margin:0;">
                    <tr>
                        <th style="width:30%;"><i class="fa fa-broom text-warning"></i> &nbsp;Cleanup Old Files</th>
                        <td>
                            <ul class="small" style="padding-left:18px;margin:0;">
                                <li>Run <code>cloudserver:cleanup-bundles</code> (hapus bundle JS lama)</li>
                                <li>Clear Laravel cache (view, config, cache, route)</li>
                                <li>Clear OPcache</li>
                                <li>Flush application cache (incl. TG bot sessions)</li>
                                <li>Delete log files > 7 hari</li>
                                <li>Truncate laravel.log jika > 50MB</li>
                                <li>Clear framework cache data + sessions</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th><i class="fa fa-cloud-download text-primary"></i> &nbsp;Update from GitHub</th>
                        <td>
                            <ul class="small" style="padding-left:18px;margin:0;">
                                <li>Maintenance mode ON + stop bot worker</li>
                                <li>Download tarball terbaru dari <code>marwan585/opik main</code></li>
                                <li>Sync app, config, database, resources, routes (preserve .env, storage, vendor, public/themes)</li>
                                <li>Replace <code>public/assets/</code> entirely (delete all old, copy new)</li>
                                <li>Set permission www-data</li>
                                <li><code>composer install --no-dev --optimize-autoloader</code></li>
                                <li><code>php artisan migrate --seed --force</code></li>
                                <li>Cleanup orphaned users (@cloudserver.local dengan 0 server)</li>
                                <li>Clear + rebuild cache, OPcache reset</li>
                                <li>Maintenance mode OFF + reload PHP-FPM + nginx</li>
                                <li>Restart bot worker (jika dikonfigurasi)</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th><i class="fa fa-refresh text-danger"></i> &nbsp;Restart All Services</th>
                        <td>
                            <ul class="small" style="padding-left:18px;margin:0;">
                                <li><code>php artisan queue:restart</code></li>
                                <li>Clear OPcache</li>
                                <li>Reload/restart PHP-FPM</li>
                                <li>Reload web server (nginx/apache)</li>
                                <li>Restart cloud-tg-bot worker (jika dikonfigurasi)</li>
                                <li>Clear application cache</li>
                            </ul>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-tachometer"></i> &nbsp;Status Live</h3>
                <div class="box-tools pull-right">
                    <span class="badge badge-success" id="auto-refresh-badge" style="background:#10b981;">●</span>
                </div>
            </div>
            <div class="box-body" style="padding:0;">
                <table class="table table-condensed" id="status-table" style="margin:0;">
                    <tr>
                        <td><i class="fa fa-folder text-muted"></i> &nbsp;Panel dir</td>
                        <td class="text-right text-muted small">{{ $panelDir }}</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-hdd-o text-muted"></i> &nbsp;Panel size</td>
                        <td class="text-right text-muted small">{{ $panelSize }}</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-file-code-o text-muted"></i> &nbsp;Bundle files</td>
                        <td class="text-right text-muted small" id="bundle-info">{{ $bundleCount }} files ({{ round($bundleSize / 1024, 1) }} KB)</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-tag text-muted"></i> &nbsp;Active bundle</td>
                        <td class="text-right text-muted small" id="active-bundle">{{ $manifestBundle }}</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-git text-muted"></i> &nbsp;Last commit</td>
                        <td class="text-right text-muted small">{{ $lastCommit ?: 'not a git repo' }}</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-file-text-o text-muted"></i> &nbsp;Log size</td>
                        <td class="text-right text-muted small">{{ $logSize > 0 ? round($logSize / 1024, 1) . ' KB' : 'empty' }}</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-telegram text-muted"></i> &nbsp;Bot configured</td>
                        <td class="text-right">
                            @if ($tgConfigured)
                                <span class="label label-success">YES</span>
                            @else
                                <span class="label label-default">NO</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-microchip text-muted"></i> &nbsp;Load avg</td>
                        <td class="text-right text-muted small" id="load-avg">—</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-memory text-muted"></i> &nbsp;Memory</td>
                        <td class="text-right text-muted small" id="mem-info">—</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-server"></i> &nbsp;Services Status</h3>
            </div>
            <div class="box-body" style="padding:0;">
                <table class="table table-condensed" id="services-table" style="margin:0;">
                    @foreach ($services as $svc => $status)
                    <tr data-service="{{ $svc }}">
                        <td><code>{{ $svc }}</code></td>
                        <td class="text-right">
                            @if ($status === 'active')
                                <span class="label label-success">{{ $status }}</span>
                            @elseif ($status === 'failed' || $status === 'inactive')
                                <span class="label label-danger">{{ $status }}</span>
                            @else
                                <span class="label label-default">{{ $status }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @if (empty($services))
                    <tr><td colspan="2" class="text-muted text-center small">Tidak ada service terdeteksi</td></tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-lightbulb-o"></i> &nbsp;Tips</h3>
            </div>
            <div class="box-body">
                <ul class="small" style="padding-left:18px;line-height:1.7;">
                    <li><strong>Cleanup</strong>: Aman dijalankan kapan saja. Tidak akan mengganggu user aktif.</li>
                    <li><strong>Update</strong>: Panel akan masuk maintenance mode ~30 detik. Pastikan tidak ada user kritis.</li>
                    <li><strong>Restart</strong>: Akan disconnect semua user sebentar (~5 detik) saat PHP-FPM reload.</li>
                    <li>Setelah Update, <strong>clear cache browser</strong> (Ctrl+Shift+R) untuk lihat perubahan UI.</li>
                    <li>Backup otomatis dilakukan di <code>/var/www/pterodactyl-backup-*</code> sebelum update.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh status every 10 seconds
(function() {
    function refresh() {
        fetch('{{ route("admin.cloud.console.status") }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            // Update bundle info
            const bundleInfo = document.getElementById('bundle-info');
            if (bundleInfo) {
                bundleInfo.textContent = data.bundleCount + ' files (' + (data.bundleSize / 1024).toFixed(1) + ' KB)';
            }
            const activeBundle = document.getElementById('active-bundle');
            if (activeBundle) {
                activeBundle.textContent = data.manifestBundle;
            }
            // Update load avg
            const loadAvg = document.getElementById('load-avg');
            if (loadAvg && data.loadAvg) {
                loadAvg.textContent = data.loadAvg.m1.toFixed(2) + ', ' + data.loadAvg.m5.toFixed(2) + ', ' + data.loadAvg.m15.toFixed(2);
            }
            // Update mem info
            const memInfo = document.getElementById('mem-info');
            if (memInfo && data.memInfo) {
                const used = (data.memInfo.used / 1024 / 1024 / 1024).toFixed(1);
                const total = (data.memInfo.total / 1024 / 1024 / 1024).toFixed(1);
                memInfo.textContent = data.memInfo.used_pct + '% (' + used + ' / ' + total + ' GB)';
            }
            // Update services table
            const servicesTable = document.getElementById('services-table');
            if (servicesTable && data.services) {
                const existing = {};
                servicesTable.querySelectorAll('tr[data-service]').forEach(tr => {
                    existing[tr.dataset.service] = tr;
                });
                // Update existing + add new
                Object.keys(data.services).forEach(svc => {
                    const status = data.services[svc];
                    let tr = existing[svc];
                    if (!tr) {
                        tr = document.createElement('tr');
                        tr.dataset.service = svc;
                        const td1 = document.createElement('td');
                        td1.innerHTML = '<code>' + svc + '</code>';
                        tr.appendChild(td1);
                        const td2 = document.createElement('td');
                        td2.className = 'text-right';
                        tr.appendChild(td2);
                        servicesTable.appendChild(tr);
                    }
                    const td2 = tr.querySelector('td.text-right');
                    let labelClass = 'label-default';
                    if (status === 'active') labelClass = 'label-success';
                    else if (status === 'failed' || status === 'inactive') labelClass = 'label-danger';
                    td2.innerHTML = '<span class="label ' + labelClass + '">' + status + '</span>';
                });
            }
        })
        .catch(err => {
            const badge = document.getElementById('auto-refresh-badge');
            if (badge) { badge.style.background = '#ef4444'; }
        });
    }
    refresh();
    setInterval(refresh, 10000);
})();
</script>
@endsection
