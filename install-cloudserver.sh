#!/bin/bash
# ============================================================================
# Cloud Server (Opik) — One-Command Installer / Updater
# ============================================================================
#
# Cara pakai:
#   curl -L https://raw.githubusercontent.com/marwan585/opik/main/install-cloudserver.sh | sudo bash
#
# Atau download dulu:
#   curl -L -o install-cloudserver.sh https://raw.githubusercontent.com/marwan585/opik/main/install-cloudserver.sh
#   chmod +x install-cloudserver.sh
#   sudo ./install-cloudserver.sh
#
# Apa yang dilakukan script ini:
#   1. Backup .env dan database config
#   2. Download kode terbaru dari GitHub (marwan585/opik)
#   3. Overwrite file panel (preserve .env, storage, vendor, database)
#   4. Jalankan database migration (termasuk tabel Telegram bot)
#   5. Set permission www-data
#   6. Clear semua cache Laravel
#   7. Setup systemd service untuk Telegram bot worker
#   8. Reload PHP-FPM + nginx
#   9. Print langkah selanjutnya
#
# ============================================================================

set -e

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# --- Config ---
PANEL_DIR="${PANEL_DIR:-/var/www/pterodactyl}"
REPO_URL="https://github.com/marwan585/opik"
REPO_TARBALL="https://github.com/marwan585/opik/archive/refs/heads/main.tar.gz"
TMP_DIR="/tmp/opik-install-$$"
WEB_USER="www-data"
PHP_VERSION="${PHP_VERSION:-8.2}"

# Track if we entered maintenance mode (so we know to exit it)
ENTERED_MAINTENANCE=0

# --- Helper functions ---
log()   { echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $*"; }
ok()    { echo -e "${GREEN}✓${NC} $*"; }
warn()  { echo -e "${YELLOW}⚠${NC} $*"; }
err()   { echo -e "${RED}✗${NC} $*" >&2; }
step()  { echo -e "\n${BOLD}${CYAN}━━━ $* ━━━${NC}"; }

cleanup() {
    if [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
    # Always exit maintenance mode if we entered it
    if [ "$ENTERED_MAINTENANCE" = "1" ] && [ -f "$PANEL_DIR/artisan" ]; then
        cd "$PANEL_DIR"
        sudo -u "$WEB_USER" php artisan up 2>/dev/null || true
        warn "Maintenance mode auto-exited during cleanup."
    fi
}
trap cleanup EXIT INT TERM

# --- Pre-flight checks ---
step "Pre-flight checks"

# Must run as root
if [ "$(id -u)" -ne 0 ]; then
    err "Script ini harus dijalankan sebagai root. Gunakan: sudo $0"
    exit 1
fi
ok "Running as root"

# Check panel directory
if [ ! -d "$PANEL_DIR" ]; then
    err "Panel directory $PANEL_DIR tidak ditemukan."
    err "Jika panel Anda di path lain, jalankan: PANEL_DIR=/path/to/panel sudo $0"
    exit 1
fi
ok "Panel directory: $PANEL_DIR"

# Check artisan exists
if [ ! -f "$PANEL_DIR/artisan" ]; then
    err "File artisan tidak ditemukan di $PANEL_DIR. Apakah ini panel Pterodactyl?"
    exit 1
fi
ok "artisan ditemukan"

# Check PHP
if ! command -v php &> /dev/null; then
    err "PHP tidak ditemukan. Install PHP $PHP_VERSION dulu."
    exit 1
fi
PHP_VER=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
ok "PHP version: $PHP_VER"

# Check curl
if ! command -v curl &> /dev/null; then
    err "curl tidak ditemukan. Install: apt install -y curl"
    exit 1
fi
ok "curl tersedia"

# Check internet (can reach GitHub)
if ! curl -sI --max-time 10 "$REPO_URL" > /dev/null 2>&1; then
    err "Tidak bisa terhubung ke GitHub ($REPO_URL). Cek koneksi internet."
    exit 1
fi
ok "GitHub reachable"

# Detect web user (try nginx/www-data/apache)
if id -u nginx &> /dev/null; then
    WEB_USER="nginx"
elif id -u www-data &> /dev/null; then
    WEB_USER="www-data"
elif id -u apache &> /dev/null; then
    WEB_USER="apache"
fi
ok "Web user: $WEB_USER"

# Detect PHP-FPM service name
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
if ! systemctl list-unit-files | grep -q "^$PHP_FPM_SERVICE\.service"; then
    # Try alternative naming
    for svc in "php-fpm" "php8.1-fpm" "php8.0-fpm" "php7.4-fpm"; do
        if systemctl list-unit-files | grep -q "^$svc\.service"; then
            PHP_FPM_SERVICE="$svc"
            break
        fi
    done
fi
ok "PHP-FPM service: $PHP_FPM_SERVICE"

# Detect web server
WEB_SERVER=""
if systemctl list-unit-files | grep -q "^nginx\.service"; then
    WEB_SERVER="nginx"
elif systemctl list-unit-files | grep -q "^apache2\.service"; then
    WEB_SERVER="apache2"
fi
ok "Web server: ${WEB_SERVER:-unknown (skip reload)}"

echo ""
echo -e "${BOLD}Resume:${NC}"
echo -e "  Panel dir    : ${CYAN}$PANEL_DIR${NC}"
echo -e "  Web user     : ${CYAN}$WEB_USER${NC}"
echo -e "  PHP-FPM      : ${CYAN}$PHP_FPM_SERVICE${NC}"
echo -e "  Web server   : ${CYAN}${WEB_SERVER:-none}${NC}"
echo -e "  Source       : ${CYAN}$REPO_URL${NC}"
echo ""

# Confirm — skip prompt if stdin is not a tty (e.g., curl | bash piping)
if [ -t 0 ]; then
    read -p "Lanjutkan install? (y/N) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        warn "Dibatalkan oleh user."
        exit 0
    fi
else
    warn "Stdin bukan tty (curl | bash) — lanjut otomatis tanpa konfirmasi."
    warn "Untuk konfirmasi interaktif: download script dulu, lalu jalankan langsung."
fi

# --- Step 1: Enter maintenance mode ---
step "1/8 — Masuk maintenance mode"
cd "$PANEL_DIR"
if sudo -u "$WEB_USER" php artisan down 2>/dev/null; then
    ENTERED_MAINTENANCE=1
    ok "Maintenance mode AKTIF"
else
    warn "Tidak bisa masuk maintenance mode (mungkin sudah aktif atau ada error). Lanjut..."
fi

# --- Step 2: Backup critical files ---
step "2/8 — Backup file penting"

BACKUP_DIR="/var/www/pterodactyl-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup .env
if [ -f "$PANEL_DIR/.env" ]; then
    cp "$PANEL_DIR/.env" "$BACKUP_DIR/.env"
    ok ".env → $BACKUP_DIR/.env"
else
    warn ".env tidak ditemukan di $PANEL_DIR — pastikan ada setelah install!"
fi

# Backup database config
if [ -f "$PANEL_DIR/config/database.php" ]; then
    cp "$PANEL_DIR/config/database.php" "$BACKUP_DIR/database.php" 2>/dev/null || true
fi

# Backup any custom theme/js files in public/
if [ -d "$PANEL_DIR/public/themes" ]; then
    cp -r "$PANEL_DIR/public/themes" "$BACKUP_DIR/themes" 2>/dev/null || true
    ok "themes/ → backup"
fi

# Backup storage (just in case — usually large, skip with --no-recursion)
# Storage is not touched by install, but we copy directory listing for reference
ls -la "$PANEL_DIR/storage/" > "$BACKUP_DIR/storage-listing.txt" 2>/dev/null || true

# Save list of currently enabled features/settings for reference
sudo -u "$WEB_USER" php artisan tinker --execute="echo json_encode(\DB::table('settings')->where('key','LIKE','cloud:%')->get());" > "$BACKUP_DIR/cloud-settings.json" 2>/dev/null || true
ok "Cloud settings → $BACKUP_DIR/cloud-settings.json"

echo ""
ok "Backup selesai di: ${CYAN}$BACKUP_DIR${NC}"

# --- Step 3: Download latest code from GitHub ---
step "3/8 — Download kode terbaru dari GitHub"

log "Downloading tarball..."
mkdir -p "$TMP_DIR"
if ! curl -L --fail --progress-bar -o "$TMP_DIR/opik.tar.gz" "$REPO_TARBALL"; then
    err "Gagal download dari $REPO_TARBALL"
    exit 1
fi
ok "Download selesai ($(du -h "$TMP_DIR/opik.tar.gz" | cut -f1))"

log "Extracting tarball..."
tar xzf "$TMP_DIR/opik.tar.gz" -C "$TMP_DIR"
if [ ! -d "$TMP_DIR/opik-main" ]; then
    err "Extract gagal — folder opik-main tidak ditemukan"
    exit 1
fi
ok "Extract selesai"

SRC_DIR="$TMP_DIR/opik-main"

# --- Step 4: Overwrite panel files ---
step "4/8 — Update file panel"

# Files/dirs yang TIDAK boleh di-overwrite (preserve)
PRESERVE=(
    ".env"
    "storage"
    "vendor"
    "node_modules"
    "public/assets/svgs"
)

# Directories yang harus di-sync dari repo
SYNC_DIRS=(
    "app"
    "config"
    "database"
    "resources"
    "routes"
    "public"
    "bootstrap"
    "composer.json"
    "composer.lock"
    "package.json"
    "webpack.config.js"
    "yarn.lock"
    "README.md"
    "CHANGELOG.md"
    "Dockerfile"
    "docker-compose.example.yml"
    "NookLicense.md"
)

# Copy each sync dir/file
for item in "${SYNC_DIRS[@]}"; do
    if [ -e "$SRC_DIR/$item" ]; then
        if [ -d "$SRC_DIR/$item" ]; then
            # Directory: rsync-like copy (overwrite)
            cp -r "$SRC_DIR/$item" "$PANEL_DIR/"
        else
            # File: copy
            cp "$SRC_DIR/$item" "$PANEL_DIR/"
        fi
    fi
done

# Special handling for public/assets: AGGRESSIVELY clean ALL old bundle files
# then copy fresh from repo. This ensures no stale chunks remain.
log "Cleaning public/assets/ (hapus SEMUA file lama)..."

# Delete ALL .js files in public/assets/ (bundles, chunks, everything)
find "$PANEL_DIR/public/assets" -maxdepth 1 -type f -name "*.js" -delete 2>/dev/null || true

# Delete ALL .json files (manifest)
find "$PANEL_DIR/public/assets" -maxdepth 1 -type f -name "*.json" -delete 2>/dev/null || true

# Delete ALL .woff2 files (will be re-copied)
find "$PANEL_DIR/public/assets" -maxdepth 1 -type f -name "*.woff2" -delete 2>/dev/null || true

# Delete ALL .map files
find "$PANEL_DIR/public/assets" -maxdepth 1 -type f -name "*.map" -delete 2>/dev/null || true

# Copy new assets from repo (fresh copy)
if [ -d "$SRC_DIR/public/assets" ]; then
    cp -r "$SRC_DIR/public/assets/." "$PANEL_DIR/public/assets/"
fi

# Also remove old root-level bundle artifact (legacy)
rm -f "$PANEL_DIR"/public/bundle.*.js

# Restore .env (just in case it got overwritten)
if [ -f "$BACKUP_DIR/.env" ]; then
    cp "$BACKUP_DIR/.env" "$PANEL_DIR/.env"
fi

# Restore public/themes if backup exists (custom theme overrides)
if [ -d "$BACKUP_DIR/themes" ] && [ ! -d "$PANEL_DIR/public/themes" ]; then
    cp -r "$BACKUP_DIR/themes" "$PANEL_DIR/public/themes"
fi

ok "File panel diupdate"
log "Sample file versions:"
ls -la "$PANEL_DIR/public/assets/" | head -10

# --- Step 5: Composer install ---
step "5/8 — Install PHP dependencies (composer)"

if command -v composer &> /dev/null; then
    log "Running composer install..."
    cd "$PANEL_DIR"
    if sudo -u "$WEB_USER" composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -20; then
        ok "Composer install selesai"
    else
        warn "Composer install ada warning (mungkin OK) — lanjut..."
    fi
else
    warn "composer tidak ditemukan — skip dependency install"
    warn "Jika ada error class not found, install composer dan jalankan: cd $PANEL_DIR && composer install --no-dev --optimize-autoloader"
fi

# --- Step 6: Database migration ---
step "6/8 — Database migration (termasuk tabel Telegram Bot)"

cd "$PANEL_DIR"
log "Running migrate --force..."
if sudo -u "$WEB_USER" php artisan migrate --seed --force 2>&1 | tail -30; then
    ok "Migration selesai"
else
    warn "Migration ada warning — cek output di atas. Lanjut..."
fi

# Verify telegram tables exist
log "Verifikasi tabel Telegram Bot..."
if sudo -u "$WEB_USER" php artisan tinker --execute="echo \DB::schema()->hasTable('telegram_bot_users') ? 'TELEGRAM_TABLES_OK' : 'TELEGRAM_TABLES_MISSING';" 2>/dev/null | grep -q "TELEGRAM_TABLES_OK"; then
    ok "Tabel telegram_bot_users ✓"
else
    warn "Tabel telegram_bot_users TIDAK ditemukan — cek migration"
fi

# --- Step 7: Clear caches + set permissions ---
step "7/8 — Clear cache + set permission"

cd "$PANEL_DIR"

log "Clearing Laravel caches..."
sudo -u "$WEB_USER" php artisan view:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan config:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan cache:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan route:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan queue:clear 2>&1 | tail -1 || true
ok "Cache cleared"

log "Optimizing autoloader..."
sudo -u "$WEB_USER" php artisan config:cache 2>&1 | tail -1 || true
sudo -u "$WEB_USER" php artisan route:cache 2>&1 | tail -1 || true
sudo -u "$WEB_USER" php artisan view:cache 2>&1 | tail -1 || true
ok "Cache rebuilt"

log "Setting permissions..."
chown -R "$WEB_USER":"$WEB_USER" "$PANEL_DIR"/*
chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" 2>/dev/null || true
find "$PANEL_DIR/storage" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$PANEL_DIR/storage" -type f -exec chmod 644 {} \; 2>/dev/null || true
ok "Permission set"

# --- Step 8: Setup Telegram Bot systemd service ---
step "8/8 — Setup Telegram Bot worker (systemd)"

SYSTEMD_FILE="/etc/systemd/system/cloud-tg-bot.service"

log "Creating systemd service file..."
cat > "$SYSTEMD_FILE" <<EOF
[Unit]
Description=Cloud Server Telegram Bot Worker
After=network.target mysql.service postgresql.service

[Service]
Type=simple
User=$WEB_USER
WorkingDirectory=$PANEL_DIR
ExecStart=/usr/bin/php $PANEL_DIR/artisan cloudserver:telegram-bot
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=cloud-tg-bot

[Install]
WantedBy=multi-user.target
EOF
ok "Service file: $SYSTEMD_FILE"

log "Reloading systemd..."
systemctl daemon-reload
ok "systemd reloaded"

log "Enabling service (not starting yet — user needs to configure bot first)..."
systemctl enable cloud-tg-bot 2>&1 | tail -1
ok "Service enabled (auto-start on boot)"

# Check if config already exists — if so, RESTART service (always restart to pick up new code)
TG_TOKEN=$(sudo -u "$WEB_USER" php artisan tinker --execute="echo \DB::table('settings')->where('key','cloud:tg:bot_token')->value('value');" 2>/dev/null | tr -d '\n')
TG_ENABLED=$(sudo -u "$WEB_USER" php artisan tinker --execute="echo \DB::table('settings')->where('key','cloud:tg:enabled')->value('value');" 2>/dev/null | tr -d '\n')

if [ -n "$TG_TOKEN" ] && [ "$TG_TOKEN" != "" ] && [ "$TG_ENABLED" = "1" ]; then
    log "Bot configured — RESTARTING service (pick up new code)..."
    systemctl restart cloud-tg-bot
    sleep 3
    if systemctl is-active --quiet cloud-tg-bot; then
        ok "Telegram bot worker RUNNING (new code loaded)"
    else
        warn "Bot service gagal start — cek: journalctl -u cloud-tg-bot -n 50"
    fi
else
    warn "Bot belum dikonfigurasi — skip start. Configure di Admin > Cloud Server > Telegram Bot."
    warn "Setelah konfigurasi, jalankan: sudo systemctl restart cloud-tg-bot"
fi

# --- Reload web services ---
step "Reload web services"

# Exit maintenance mode
if [ "$ENTERED_MAINTENANCE" = "1" ]; then
    cd "$PANEL_DIR"
    sudo -u "$WEB_USER" php artisan up 2>&1 | tail -1
    ENTERED_MAINTENANCE=0
    ok "Maintenance mode NONAKTIF"
fi

# Restart queue worker
log "Restarting queue worker..."
sudo -u "$WEB_USER" php artisan queue:restart 2>&1 | tail -1 || true
ok "Queue restart signal sent"

# Clear OPcache (if enabled) so PHP picks up new code immediately
log "Clearing PHP OPcache..."
sudo -u "$WEB_USER" php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared (CLI)'; } else { echo 'OPcache not enabled in CLI'; }" 2>&1 | head -1 || true

# Reload PHP-FPM (this also clears FPM's OPcache)
log "Reloading $PHP_FPM_SERVICE..."
if systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null; then
    ok "PHP-FPM reloaded (OPcache cleared)"
else
    # Try restart if reload fails
    warn "Reload gagal, trying restart..."
    if systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null; then
        ok "PHP-FPM restarted (OPcache cleared)"
    else
        warn "Gagal reload/restart $PHP_FPM_SERVICE — cek: systemctl status $PHP_FPM_SERVICE"
    fi
fi

# Reload web server
if [ -n "$WEB_SERVER" ]; then
    log "Reloading $WEB_SERVER..."
    if systemctl reload "$WEB_SERVER" 2>/dev/null; then
        ok "$WEB_SERVER reloaded"
    else
        warn "Gagal reload $WEB_SERVER"
    fi
fi

# Final cleanup: cloudserver:cleanup-bundles command (if it exists)
log "Running cloudserver:cleanup-bundles (cleanup stale bundles)..."
sudo -u "$WEB_USER" php artisan cloudserver:cleanup-bundles --force 2>&1 | tail -5 || true

# --- Final summary ---
echo ""
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}  ✓ INSTALL SELESAI!${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BOLD}Yang sudah dilakukan:${NC}"
echo -e "  ${GREEN}✓${NC} Backup file penting → ${CYAN}$BACKUP_DIR${NC}"
echo -e "  ${GREEN}✓${NC} Download kode terbaru dari GitHub"
echo -e "  ${GREEN}✓${NC} Update file panel (preserve .env, storage, vendor)"
echo -e "  ${GREEN}✓${NC} HAPUS SEMUA bundle JS lama di public/assets/ (cleanup agresif)"
echo -e "  ${GREEN}✓${NC} Copy bundle JS baru dari repo"
echo -e "  ${GREEN}✓${NC} Install PHP dependencies (composer)"
echo -e "  ${GREEN}✓${NC} Database migration (tabel telegram_bot_users, telegram_panel_creations)"
echo -e "  ${GREEN}✓${NC} Clear + rebuild cache Laravel"
echo -e "  ${GREEN}✓${NC} Set permission www-data"
echo -e "  ${GREEN}✓${NC} Setup systemd service cloud-tg-bot (enabled, auto-start on boot)"
echo -e "  ${GREEN}✓${NC} RESTART cloud-tg-bot worker (pick up new code)"
echo -e "  ${GREEN}✓${NC} Clear PHP OPcache + reload PHP-FPM"
echo -e "  ${GREEN}✓${NC} Reload web server + cleanup stale bundles"
echo ""
echo -e "${BOLD}${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${YELLOW}  LANGKAH SELANJUTNYA (Wajib)${NC}"
echo -e "${BOLD}${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BOLD}1. Clear cache browser Anda${NC} (WAJIB — supaya bundle JS baru di-download)"
echo -e "   ${CYAN}Ctrl+Shift+R${NC} (desktop) atau ${CYAN}Settings → Privacy → Clear browsing data${NC} (mobile)"
echo ""
echo -e "${BOLD}2. Konfigurasi Telegram Bot${NC} (jika belum)"
echo -e "   Login panel → Admin → Cloud Server → ${CYAN}Telegram Bot${NC}"
echo -e "   Isi: Bot Token (dari @BotFather), Owner ID (dari @userinfobot), Group ID (dari @RawDataBot)"
echo -e "   Centang ${CYAN}Bot Aktif${NC} → Simpan"
echo -e "   Lalu restart worker: ${CYAN}sudo systemctl restart cloud-tg-bot${NC}"
echo ""
echo -e "${BOLD}3. Test login page baru${NC}"
echo -e "   Logout → lihat halaman login baru (centered card + gradient background)"
echo ""
echo -e "${BOLD}4. Test buat panel via Telegram Bot${NC}"
echo -e "   Buka bot di Telegram → /start → Buat Panel Baru → pilih RAM → pilih egg → input username"
echo -e "   Kredensial akan dikirim ke private chat Anda"
echo ""
echo -e "${BOLD}5. Test Maintenance Mode (opsional)${NC}"
echo -e "   Admin → Cloud Server → ${CYAN}Maintenance Mode${NC} → toggle on/off"
echo ""
echo -e "${BOLD}Commands berguna:${NC}"
echo -e "  ${CYAN}sudo systemctl status cloud-tg-bot${NC}      # cek status bot worker"
echo -e "  ${CYAN}sudo systemctl restart cloud-tg-bot${NC}     # restart bot worker"
echo -e "  ${CYAN}sudo journalctl -u cloud-tg-bot -f${NC}      # lihat log bot live"
echo -e "  ${CYAN}sudo systemctl reload $PHP_FPM_SERVICE${NC}   # reload PHP-FPM"
if [ -n "$WEB_SERVER" ]; then
    echo -e "  ${CYAN}sudo systemctl reload $WEB_SERVER${NC}            # reload web server"
fi
echo ""
echo -e "${BOLD}Backup tersimpan di:${NC} ${CYAN}$BACKUP_DIR${NC}"
echo -e "  (simpan baik-baik kalau perlu rollback)"
echo ""
echo -e "${BOLD}Jika ada masalah:${NC}"
echo -e "  - Cek log Laravel: ${CYAN}tail -f $PANEL_DIR/storage/logs/laravel.log${NC}"
echo -e "  - Cek log bot:     ${CYAN}sudo journalctl -u cloud-tg-bot -n 100${NC}"
echo -e "  - Rollback .env:   ${CYAN}cp $BACKUP_DIR/.env $PANEL_DIR/.env${NC}"
echo ""
echo -e "${GREEN}Selamat! Cloud Server panel sudah ter-update dengan 3 fitur baru.${NC}"
echo -e "${GREEN}  1. Telegram Bot — auto create panel via Telegram${NC}"
echo -e "${GREEN}  2. Maintenance Mode — kunci akses panel${NC}"
echo -e "${GREEN}  3. Modern Login UI — glassmorphism + aurora${NC}"
echo ""

exit 0
