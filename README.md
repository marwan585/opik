# 🌩️ Cloud Server Panel (Opik)

> Fork dari [NookTheme](https://github.com/Nookure/NookTheme) untuk [Pterodactyl](https://pterodactyl.io) — dengan UI modern, Telegram Bot auto-deploy, Maintenance Mode, dan banyak fitur lainnya.

![Cloud Server Panel](https://i.imgur.com/AFjHGBr.png)

---

## ✨ Fitur Utama

### 1. 🤖 Telegram Bot — Auto Create Panel
Bot Telegram yang memungkinkan user membuat panel Pterodactyl baru secara otomatis lewat chat.

- **Admin menu** (hanya owner ID 1): tambah/hapus user, cabut akses, hapus server
- **User menu** (setelah di-add admin): buat panel baru, list panel saya
- **Flow buat panel**: pilih RAM (1-7 GB) → pilih egg → input username → bot otomatis buat user + server
- Kredensial otomatis dikirim ke **private chat** user
- Worker via systemd, auto-restart, log via `journalctl`

### 2. 🔧 Maintenance Mode
Toggle on/off di admin → Cloud Server → Maintenance Mode.

- Saat **AKTIF**: user biasa & admin biasa tidak bisa buka panel SPA — lihat halaman maintenance custom
- Owner (admin ID 1) tetap bisa akses semua menu
- API endpoints tetap aktif — server game yang sudah running **tetap online**
- Wings daemon tetap berfungsi normal

### 3. 🎨 Modern Login UI
Halaman login dengan tampilan premium profesional.

- **Split-screen layout** di desktop: showcase panel kiri, form kanan
- **Mobile responsive**: single card dengan brand header
- Subtle aurora gradient background + grid overlay
- Modern typography (large brand wordmark, light subtitle)
- Glassmorphism card dengan soft border + layered shadow
- Input fields dengan indigo accent on focus
- Gradient submit button dengan hover lift effect
- 3 feature list di showcase panel (Auto-Deploy, Monitoring, Manajemen Akses)

### 4. 🛡️ Security Stack
- **Owner-only admin menus** — menu critical hanya bisa diakses admin ID 1
- **IP Access List** — log setiap IP yang login ke panel
- **System Monitoring** — live CPU/RAM/disk + panel counters
- **Auto-suspend server** yang over-disk atau suspicious activity
- **Aggressive no-cache headers** untuk HTML, supaya browser tidak cache bundle lama

### 5. 🎯 UI/UX Improvements
- Dashboard dengan hero card + stat tiles + glassmorphism cards
- Navigation bar dengan gradient background + glassmorphism
- ServerRow dengan mini progress bars (CPU/RAM/Disk)
- Smooth transitions dan animations

---

## 📦 Instalasi

### 🚀 Cara Cepat (1 Command)

```bash
curl -L https://raw.githubusercontent.com/marwan585/opik/main/install-cloudserver.sh | sudo bash
```

Atau download dulu untuk dapat prompt konfirmasi interaktif:

```bash
curl -L -o install-cloudserver.sh https://raw.githubusercontent.com/marwan585/opik/main/install-cloudserver.sh
chmod +x install-cloudserver.sh
sudo ./install-cloudserver.sh
```

**Installer otomatis melakukan 8 langkah:**

1. Masuk maintenance mode (`php artisan down`)
2. Backup file penting (`.env`, themes, cloud settings) → `/var/www/pterodactyl-backup-YYYYMMDD-HHMMSS/`
3. Download tarball terbaru dari GitHub
4. Update file panel (preserve `.env`, `storage/`, `vendor/`, `public/themes/`)
5. Install PHP dependencies (`composer install --no-dev --optimize-autoloader`)
6. Run database migration (`migrate --seed --force`) — termasuk tabel Telegram bot
7. Clear semua cache + rebuild cache + set permission `www-data`
8. Setup systemd service `cloud-tg-bot` (enabled, auto-start on boot) + exit maintenance + reload PHP-FPM + nginx

**Auto-detect:**
- Panel directory (default `/var/www/pterodactyl`, override via `PANEL_DIR=/path`)
- Web user (`nginx` / `www-data` / `apache`)
- PHP-FPM service (`php8.2-fpm` / `php8.1-fpm` / `php-fpm`)
- Web server (`nginx` / `apache2`)

Idempotent — bisa dijalankan berkali-kali. Backup disimpan dengan timestamp, jadi rollback mudah.

---

### 📋 Manual Install (Step-by-Step)

Untuk yang mau kontrol penuh, jalankan langkah berikut satu per satu.

#### Prerequisites
- PHP **8.2+** (8.3 recommended)
- Composer
- MySQL/MariaDB
- Redis (optional, recommended)
- Nginx atau Apache
- Pterodactyl Wings (untuk daemon)
- Panel Pterodactyl sudah terinstall (ini adalah theme/upgrade, bukan panel baru)

#### Step 1: Masuk Maintenance Mode

```bash
cd /var/www/pterodactyl
php artisan down
```

#### Step 2: Backup `.env`

```bash
cp .env /tmp/.env.backup
```

#### Step 3: Download Kode Terbaru

Karena tarball release bisa ketinggalan, pilih salah satu cara:

**Opsi A — Download dari GitHub (recommended):**

```bash
cd /tmp
curl -L -o opik.tar.gz https://github.com/marwan585/opik/archive/refs/heads/main.tar.gz
tar xzf opik.tar.gz
cd opik-main
```

**Opsi B — Clone via git (untuk update selanjutnya tinggal `git pull`):**

```bash
cd /var/www
git clone https://github.com/marwan585/opik.git opik-temp
cd opik-temp
```

#### Step 4: Copy Files ke Panel Directory

```bash
# Sync semua folder utama (overwrite)
cp -r app /var/www/pterodactyl/
cp -r config /var/www/pterodactyl/
cp -r database /var/www/pterodactyl/
cp -r resources /var/www/pterodactyl/
cp -r routes /var/www/pterodactyl/
cp -r public /var/www/pterodactyl/
cp -r bootstrap /var/www/pterodactyl/

# Copy file individual
cp composer.json composer.lock package.json webpack.config.js yarn.lock /var/www/pterodactyl/

# Restore .env
cp /tmp/.env.backup /var/www/pterodactyl/.env
```

#### Step 5: Install PHP Dependencies

```bash
cd /var/www/pterodactyl
composer install --no-dev --optimize-autoloader
```

#### Step 6: Run Database Migration

```bash
php artisan migrate --seed --force
```

Ini akan membuat 2 tabel baru:
- `telegram_bot_users` — daftar user yang punya akses ke Telegram bot
- `telegram_panel_creations` — log setiap panel dibuat via bot

#### Step 7: Clear & Rebuild Cache

```bash
php artisan view:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Rebuild cache for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Step 8: Set Permissions

```bash
# Detect web user (nginx / www-data / apache)
chown -R www-data:www-data /var/www/pterodactyl/*
chmod -R 755 /var/www/pterodactyl/storage
chmod -R 755 /var/www/pterodactyl/bootstrap/cache
```

#### Step 9: Restart Queue + Reload Web Server

```bash
php artisan queue:restart
php artisan up

# Reload PHP-FPM (sesuaikan versi PHP Anda)
sudo systemctl reload php8.2-fpm

# Reload web server
sudo systemctl reload nginx
# atau: sudo systemctl reload apache2
```

#### Step 10: Setup Telegram Bot Worker (systemd)

```bash
sudo tee /etc/systemd/system/cloud-tg-bot.service <<EOF
[Unit]
Description=Cloud Server Telegram Bot Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/pterodactyl
ExecStart=/usr/bin/php /var/www/pterodactyl/artisan cloudserver:telegram-bot
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=cloud-tg-bot

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable cloud-tg-bot
```

---

## ⚙️ Konfigurasi Telegram Bot

Setelah install selesai, konfigurasi bot di panel:

### 1. Buat Bot Telegram

1. Buka Telegram, chat dengan [@BotFather](https://t.me/BotFather)
2. Ketik `/newbot`, ikuti instruksi
3. Dapatkan **Bot Token** (format: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
4. Set bot privacy mode ke **disabled** (agar bisa baca pesan di grup):
   - Chat @BotFather → `/setprivacy` → pilih bot → `Disable`

### 2. Dapatkan Telegram User ID Anda

1. Chat dengan [@userinfobot](https://t.me/userinfobot)
2. User ID Anda akan ditampilkan (format: `123456789`)

### 3. Dapatkan Group ID

1. Tambahkan [@RawDataBot](https://t.me/RawDataBot) ke grup Anda
2. Bot akan kirim pesan dengan info grup, cari `"chat": {"id": -1001234567890}`
3. Itu adalah **Group ID** Anda (biasanya dimulai dengan `-100`)
4. Hapus @RawDataBot dari grup setelah dapat ID

### 4. Set di Panel Admin

1. Login ke panel sebagai admin ID 1
2. Buka **Admin → Cloud Server → Telegram Bot**
3. Isi form:
   - **Bot Token**: token dari @BotFather
   - **Telegram Owner ID**: User ID Anda
   - **Group ID**: ID grup tempat bot akan dipakai
4. Centang **Bot Aktif** → **Simpan**

### 5. Start Bot Worker

```bash
sudo systemctl start cloud-tg-bot
sudo systemctl status cloud-tg-bot
# Lihat log live:
sudo journalctl -u cloud-tg-bot -f
```

### 6. Test Bot

1. Buka bot di Telegram (private chat), ketik `/start`
2. Anda akan lihat **admin menu** dengan opsi:
   - ➕ Tambah Akses User
   - 👥 List User
   - 🗑 Hapus User
   - 🚫 Cabut Akses
   - 🖥 Hapus Server
3. Tambahkan user lain via menu (butuh Telegram User ID user tersebut)
4. User tersebut `/start` bot di grup → muncul menu buat panel

### Alur Buat Panel

User → `/start` di grup → menu "🚀 Buat Panel Baru" → pilih RAM (1-7 GB) → pilih egg → input username → bot:
1. Buat user baru di panel (email: `username@cloudserver.local`)
2. Generate random password 16 karakter
3. Cari node + allocation yang available
4. Buat server baru dengan spec yang dipilih
5. Kirim kredensial (URL panel, username, password, server info) ke **private chat** user
6. Notify admin via DM
7. Log creation di `telegram_panel_creations` table

---

## 🔧 Konfigurasi Maintenance Mode

1. Buka **Admin → Cloud Server → Maintenance Mode**
2. Centang **Aktifkan Maintenance Mode**
3. Tulis pesan (opsional, maks 1000 karakter)
4. **Simpan**

### Cara Kerja

| Saat AKTIF | Saat NONAKTIF |
|------------|---------------|
| User biasa & admin biasa (id ≠ 1) tidak bisa buka panel SPA | Semua user bisa login normal |
| Mereka lihat halaman maintenance custom (animasi wrench) | Dashboard tampil normal |
| Owner (admin ID 1) tetap bisa akses semua menu | — |
| API endpoints (`/api/*`) tetap aktif | — |
| Wings daemon tetap berfungsi | — |
| Server game yang sudah running tetap online | — |

**Catatan:** Maintenance hanya mengunci akses UI panel, bukan server game yang sedang berjalan.

---

## 🆘 Troubleshooting

### Dashboard hitam setelah update

Penyebab paling umum: browser cache bundle lama.

**Solusi:**
1. Hard refresh: `Ctrl+Shift+R` (desktop) atau `Settings → Privacy → Clear browsing data → Cached images and files` (mobile)
2. Jika masih, jalankan:
   ```bash
   cd /var/www/pterodactyl
   php artisan cloudserver:cleanup-bundles --force
   sudo systemctl reload php8.2-fpm
   sudo systemctl reload nginx
   ```
3. Verifikasi manifest menunjuk ke bundle yang ada:
   ```bash
   cat public/assets/manifest.json | grep main.js
   ls -la public/assets/
   ```
   File `bundle.*.js` yang ada di `ls` harus sama dengan yang di manifest.

### Telegram bot tidak respon

1. Cek service berjalan:
   ```bash
   sudo systemctl status cloud-tg-bot
   ```
2. Cek log:
   ```bash
   sudo journalctl -u cloud-tg-bot -n 100
   ```
3. Cek konfigurasi sudah benar:
   - Bot Token valid (test dengan `curl https://api.telegram.org/bot<TOKEN>/getMe`)
   - Owner ID > 0
   - Bot sudah di-add ke grup dan jadi admin
4. Restart service:
   ```bash
   sudo systemctl restart cloud-tg-bot
   ```

### Maintenance mode tidak bisa dimatikan

Jika Anda kunci sendiri dan tidak bisa masuk:

```bash
cd /var/www/pterodactyl
php artisan tinker
>>> \DB::table('settings')->where('key','cloud:maintenance:enabled')->update(['value' => '0']);
>>> \Cache::forget('cloud:maintenance:state');
>>> exit
```

### Error "Class not found" setelah update

Composer dependencies tidak ter-install:

```bash
cd /var/www/pterodactyl
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
```

### Migration error

```bash
cd /var/www/pterodactyl
php artisan migrate:status
php artisan migrate --force
```

Jika tabel sudah ada (duplicate), jalankan:
```bash
php artisan migrate:fresh --force  # HATI-HATI: hapus semua data!
```

---

## 📚 Dokumentasi

- [Panel Documentation](https://pterodactyl.io/panel/1.0/getting_started.html)
- [Wings Documentation](https://pterodactyl.io/wings/1.0/installing.html)
- [Community Guides](https://pterodactyl.io/community/about.html)

---

## 🛠️ Development

### Build Frontend Bundle

```bash
cd /var/www/pterodactyl

# Install Node.js dependencies
yarn install

# Development build (with source maps)
yarn run build

# Production build (minified, no source maps)
yarn run build:production
```

### Struktur Folder Penting

```
app/
├── Console/Commands/CloudServer/
│   ├── AutoSuspendCommand.php       # Auto-suspend server over-limit
│   ├── CleanupBundlesCommand.php    # Hapus bundle JS lama
│   ├── InstallCommand.php           # Installer command
│   └── TelegramBotRunCommand.php    # Telegram bot worker
├── Http/
│   ├── Controllers/Admin/CloudServer/
│   │   ├── TelegramBotSettingsController.php
│   │   ├── MaintenanceController.php
│   │   ├── PopupSettingsController.php
│   │   ├── IpAccessListController.php
│   │   └── SystemMonitoringController.php
│   └── Middleware/
│       ├── AdminOwnerOnly.php            # Gate admin menu ke owner ID 1
│       ├── CloudServerMaintenance.php    # Maintenance mode check
│       └── SetSecurityHeaders.php        # Security + no-cache headers
├── Models/
│   ├── TelegramBotUser.php
│   └── TelegramPanelCreation.php
├── Services/CloudServer/
│   └── TelegramBotService.php       # Logic bot Telegram
└── ...

resources/scripts/components/
├── auth/                             # Login, Forgot Password, Reset, 2FA
├── dashboard/                        # Dashboard, Account, Activity Log
├── server/                           # Server console, files, databases, dll
└── elements/                         # Reusable UI components

resources/views/
├── admin/cloud_server/               # Blade templates untuk admin cloud menus
└── errors/maintenance.blade.php      # Halaman maintenance user-facing
```

---

## 📜 License

Pterodactyl® Copyright © 2015 - 2023 Dane Everitt and contributors.

> Nookure is not affiliated with Pterodactyl® Panel or its contributors.

Pterodactyl code released under the [MIT License](./LICENSE.md).

NookTheme code edits released under the [GNU GPLv3 License](./NookLicense.md).

Cloud Server (Opik) fork additions released under the [GNU GPLv3 License](./NookLicense.md).

---

## 🙏 Credits

- [Pterodactyl Panel](https://pterodactyl.io) — base panel
- [NookTheme](https://github.com/Nookure/NookTheme) — base theme
- [marwan585](https://github.com/marwan585) — Cloud Server fork additions

---

## ⭐ Star History

<a href="https://star-history.com/#marwan585/opik&Timeline">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://api.star-history.com/svg?repos=marwan585/opik&type=Timeline&theme=dark" />
    <source media="(prefers-color-scheme: light)" srcset="https://api.star-history.com/svg?repos=marwan585/opik&type=Timeline" />
    <img alt="Star History Chart" src="https://api.star-history.com/svg?repos=marwan585/opik&type=Timeline" />
  </picture>
</a>
