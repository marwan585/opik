@extends('layouts.admin')

@section('title')
    Telegram Bot
@endsection

@section('content-header')
    <h1>
        <i class="fa fa-telegram text-primary"></i>
        Telegram Bot Settings
        <small>Bot auto-create panel Pterodactyl via Telegram</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Cloud Server · Telegram Bot</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="box box-primary" style="border-top: 3px solid #3c8dbc;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cog"></i> &nbsp;Konfigurasi Bot</h3>
            </div>
            <form method="POST" action="{{ route('admin.cloud.telegram') }}">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="enabled">
                            <input type="checkbox" name="enabled" id="enabled" value="1" {{ $enabled ? 'checked' : '' }}>
                            <strong>Bot Aktif</strong>
                            <span class="text-muted small">— aktifkan worker untuk polling Telegram API</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="bot_token"><strong>Bot Token</strong> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="bot_token" id="bot_token"
                               value="{{ old('bot_token', $botToken) }}"
                               placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" required>
                        <p class="text-muted small">
                            Dapatkan dari <a href="https://t.me/BotFather" target="_blank">@BotFather</a> via /newbot.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="owner_id"><strong>Telegram Owner ID</strong> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="owner_id" id="owner_id"
                               value="{{ old('owner_id', $ownerId) }}"
                               placeholder="123456789" required>
                        <p class="text-muted small">
                            User ID Telegram Anda (admin utama). Dapatkan dari <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>.
                            Hanya ID ini yang bisa akses menu admin bot.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="group_id"><strong>Group ID</strong></label>
                        <input type="text" class="form-control" name="group_id" id="group_id"
                               value="{{ old('group_id', $groupId) }}"
                               placeholder="-1001234567890">
                        <p class="text-muted small">
                            ID grup tempat bot akan menerima perintah <code>/start</code> dari user.
                            Dapatkan dengan menambahkan <a href="https://t.me/RawDataBot" target="_blank">@RawDataBot</a> ke grup Anda.
                            Jika kosong, user hanya bisa pakai bot di private chat (dengan akses admin).
                        </p>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> &nbsp;Simpan
                    </button>
                    <a href="{{ route('admin.index') }}" class="btn btn-default">Batal</a>
                </div>
            </form>
        </div>

        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-terminal"></i> &nbsp;Cara Menjalankan Bot</h3>
            </div>
            <div class="box-body">
                <p>Bot harus dijalankan sebagai daemon (long-polling worker). Jalankan perintah berikut via <strong>systemd</strong> atau <strong>supervisor</strong>:</p>
                <pre style="background:#1e1e1e;color:#f8f8f2;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;"><code>php /var/www/pterodactyl/artisan cloudserver:telegram-bot</code></pre>
                <p class="small">Worker akan terus polling Telegram API sampai dihentikan (Ctrl+C atau <code>systemctl stop</code>).</p>

                <p class="small"><strong>Contoh systemd service</strong> (<code>/etc/systemd/system/cloud-tg-bot.service</code>):</p>
                <pre style="background:#1e1e1e;color:#f8f8f2;padding:12px;border-radius:6px;overflow-x:auto;font-size:11px;"><code>[Unit]
Description=Cloud Server Telegram Bot
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/pterodactyl
ExecStart=/usr/bin/php /var/www/pterodactyl/artisan cloudserver:telegram-bot
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target</code></pre>
                <p class="small">Aktifkan dengan: <code>sudo systemctl enable --now cloud-tg-bot</code></p>
            </div>
        </div>

        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-user-plus"></i> &nbsp;Tambah Akses User</h3>
            </div>
            <form method="POST" action="{{ route('admin.cloud.telegram.add-user') }}">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="telegram_user_id"><strong>Telegram User ID</strong></label>
                        <input type="number" class="form-control" name="telegram_user_id" id="telegram_user_id"
                               placeholder="123456789" required min="1">
                        <p class="text-muted small">User ID Telegram user yang ingin diberi akses.</p>
                    </div>
                    <div class="form-group">
                        <label for="display_name"><strong>Nama Display (opsional)</strong></label>
                        <input type="text" class="form-control" name="display_name" id="display_name"
                               placeholder="John Doe" maxlength="191">
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-plus"></i> &nbsp;Tambah User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-5">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-bar-chart"></i> &nbsp;Statistik Bot</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-xs-6 text-center" style="padding: 12px;">
                        <div style="font-size:32px;font-weight:700;color:#10b981;">{{ $stats['total_users'] }}</div>
                        <div class="text-muted small">User Aktif</div>
                    </div>
                    <div class="col-xs-6 text-center" style="padding: 12px;">
                        <div style="font-size:32px;font-weight:700;color:#ef4444;">{{ $stats['total_blocked'] }}</div>
                        <div class="text-muted small">User Dicabut</div>
                    </div>
                    <div class="col-xs-6 text-center" style="padding: 12px;">
                        <div style="font-size:32px;font-weight:700;color:#3b82f6;">{{ $stats['total_panels'] }}</div>
                        <div class="text-muted small">Panel Dibuat</div>
                    </div>
                    <div class="col-xs-6 text-center" style="padding: 12px;">
                        <div style="font-size:32px;font-weight:700;color:#f59e0b;">{{ $stats['total_failed'] }}</div>
                        <div class="text-muted small">Gagal</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-users"></i> &nbsp;User dengan Akses</h3>
            </div>
            <div class="box-body" style="padding:0;">
                @if($allowedUsers->isEmpty())
                    <p class="text-muted" style="padding:16px;">Belum ada user yang diberi akses.</p>
                @else
                    <table class="table table-condensed" style="margin:0;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Panel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allowedUsers as $u)
                            <tr>
                                <td><code>{{ $u->telegram_user_id }}</code></td>
                                <td>{{ $u->display_name ?: ($u->telegram_username ? '@'.$u->telegram_username : '-') }}</td>
                                <td>{{ $u->panels_created }}</td>
                                <td>
                                    @if(!$u->is_blocked)
                                    <form method="POST" action="{{ route('admin.cloud.telegram.revoke', $u->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-warning" onclick="return confirm('Cabut akses user ini?')">
                                            <i class="fa fa-ban"></i>
                                        </button>
                                    </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.cloud.telegram.delete-user', $u->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Hapus user ini?')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-history"></i> &nbsp;Panel Terbaru Dibuat</h3>
            </div>
            <div class="box-body" style="padding:0;max-height:300px;overflow-y:auto;">
                @if($recentCreations->isEmpty())
                    <p class="text-muted" style="padding:16px;">Belum ada panel dibuat via bot.</p>
                @else
                    <table class="table table-condensed" style="margin:0;">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Panel</th>
                                <th>Egg</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentCreations as $p)
                            <tr>
                                <td>{{ $p->telegram_username ? '@'.$p->telegram_username : $p->telegram_user_id }}</td>
                                <td>{{ $p->panel_username ?: '-' }}</td>
                                <td>{{ $p->egg_name ?: '-' }}</td>
                                <td>
                                    @if($p->status === 'created')
                                        <span class="label label-success">created</span>
                                    @elseif($p->status === 'failed')
                                        <span class="label label-danger">failed</span>
                                    @else
                                        <span class="label label-default">{{ $p->status }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> &nbsp;Cara Pakai</h3>
            </div>
            <div class="box-body">
                <ol class="small">
                    <li>Buat bot via <a href="https://t.me/BotFather" target="_blank">@BotFather</a> di Telegram, dapatkan <strong>Bot Token</strong>.</li>
                    <li>Dapatkan <strong>User ID</strong> Anda dari <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>.</li>
                    <li>Tambahkan bot ke grup Anda, jadikan admin, lalu dapatkan <strong>Group ID</strong> dari <a href="https://t.me/RawDataBot" target="_blank">@RawDataBot</a>.</li>
                    <li>Masukkan ketiga nilai di form kiri, klik <strong>Simpan</strong>.</li>
                    <li>Jalankan worker: <code>php artisan cloudserver:telegram-bot</code> (atau via systemd).</li>
                    <li>Buka bot di Telegram, ketik <code>/start</code> — Anda akan lihat menu admin.</li>
                    <li>User lain juga bisa <code>/start</code> di grup, tapi harus di-add dulu oleh Anda (menu admin → Tambah Akses User).</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
