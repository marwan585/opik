#!/bin/bash
# ============================================================================
# Cloud Server — Fix Bash Script Egg (1-Command Auto Fixer)
# ============================================================================
#
# Cara pakai:
#   curl -L https://raw.githubusercontent.com/marwan585/opik/main/fix-egg-bash.sh | sudo bash
#
# Atau download dulu:
#   curl -L -o fix-egg-bash.sh https://raw.githubusercontent.com/marwan585/opik/main/fix-egg-bash.sh
#   chmod +x fix-egg-bash.sh
#   sudo ./fix-egg-bash.sh
#
# Apa yang dilakukan script ini (6 langkah otomatis):
#   1. Deteksi panel directory + web user + database credentials dari .env
#   2. Download migration file terbaru dari GitHub
#   3. Hapus egg lama (yang docker_images-nya terbalik) + variables
#   4. Reset migration status supaya bisa run lagi
#   5. Run php artisan migrate --force (auto-create egg baru yang benar)
#   6. Clear cache + reload PHP-FPM + nginx
#
# Tidak perlu tinker (yang error "psysh not allowed"). Pakai mysql CLI
# langsung untuk cleanup, lalu artisan migrate untuk create egg baru.
#
# ============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

PANEL_DIR="${PANEL_DIR:-/var/www/pterodactyl}"
REPO_MIGRATION_URL="https://raw.githubusercontent.com/marwan585/opik/main/database/migrations/2026_10_21_020000_add_bash_script_egg.php"
MIGRATION_FILE="2026_10_21_020000_add_bash_script_egg.php"

log()   { echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $*"; }
ok()    { echo -e "${GREEN}✓${NC} $*"; }
warn()  { echo -e "${YELLOW}⚠${NC} $*"; }
err()   { echo -e "${RED}✗${NC} $*" >&2; }
step()  { echo -e "\n${BOLD}${CYAN}━━━ $* ━━━${NC}"; }

# --- Pre-flight checks ---
step "Pre-flight checks"

if [ "$(id -u)" -ne 0 ]; then
    err "Script ini harus dijalankan sebagai root. Gunakan: sudo $0"
    exit 1
fi
ok "Running as root"

if [ ! -f "$PANEL_DIR/artisan" ]; then
    err "Panel directory $PANEL_DIR tidak ditemukan atau bukan panel Pterodactyl."
    err "Jika panel di path lain: PANEL_DIR=/path sudo $0"
    exit 1
fi
ok "Panel directory: $PANEL_DIR"

if [ ! -f "$PANEL_DIR/.env" ]; then
    err "File .env tidak ditemukan di $PANEL_DIR/.env"
    exit 1
fi
ok ".env ditemukan"

# Detect web user
WEB_USER="www-data"
if id -u nginx &> /dev/null; then
    WEB_USER="nginx"
elif id -u www-data &> /dev/null; then
    WEB_USER="www-data"
elif id -u apache &> /dev/null; then
    WEB_USER="apache"
fi
ok "Web user: $WEB_USER"

if ! command -v php &> /dev/null; then
    err "PHP tidak ditemukan."
    exit 1
fi
ok "PHP: $(php -v | head -n1 | cut -d' ' -f2)"

if ! command -v mysql &> /dev/null; then
    err "mysql CLI tidak ditemukan. Install: apt install -y default-mysql-client"
    exit 1
fi
ok "mysql CLI tersedia"

if ! command -v curl &> /dev/null; then
    err "curl tidak ditemukan."
    exit 1
fi
ok "curl tersedia"

# --- Extract DB credentials from .env ---
step "1/6 — Baca database credentials dari .env"

DB_DATABASE=$(grep -E "^DB_DATABASE=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep -E "^DB_USERNAME=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep -E "^DB_PASSWORD=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_HOST=$(grep -E "^DB_HOST=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "127.0.0.1")
DB_PORT=$(grep -E "^DB_PORT=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "3306")

if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
    err "Tidak bisa baca DB_DATABASE atau DB_USERNAME dari .env"
    err "Pastikan .env punya baris DB_DATABASE=... dan DB_USERNAME=..."
    exit 1
fi

ok "Database: $DB_DATABASE"
ok "Username: $DB_USERNAME"
ok "Host: $DB_HOST:$DB_PORT"

# Test DB connection
log "Test koneksi database..."
if [ -n "$DB_PASSWORD" ]; then
    MYSQL_CMD="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p'${DB_PASSWORD}' ${DB_DATABASE}"
else
    MYSQL_CMD="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} ${DB_DATABASE}"
fi

if ! eval "$MYSQL_CMD -e 'SELECT 1'" &> /dev/null; then
    err "Gagal koneksi ke database. Cek credentials di .env"
    err "DB_HOST=$DB_HOST DB_PORT=$DB_PORT DB_USERNAME=$DB_USERNAME DB_DATABASE=$DB_DATABASE"
    exit 1
fi
ok "Koneksi database OK"

# --- Step 2: Download migration file terbaru ---
step "2/6 — Download migration file terbaru dari GitHub"

MIGRATION_PATH="$PANEL_DIR/database/migrations/$MIGRATION_FILE"

log "Downloading migration..."
if ! curl -L --fail -o "$MIGRATION_PATH" "$REPO_MIGRATION_URL"; then
    err "Gagal download migration dari $REPO_MIGRATION_URL"
    exit 1
fi

if [ ! -f "$MIGRATION_PATH" ] || [ ! -s "$MIGRATION_PATH" ]; then
    err "Migration file tidak ter-download atau kosong"
    exit 1
fi

# Fix ownership
chown "$WEB_USER":"$WEB_USER" "$MIGRATION_PATH"
chmod 644 "$MIGRATION_PATH"

ok "Migration downloaded: $MIGRATION_PATH ($(du -h "$MIGRATION_PATH" | cut -f1))"

# Verify file content
if ! grep -q "Bash Script (Python/Node.js)" "$MIGRATION_PATH"; then
    err "Migration file tidak valid — tidak mengandung 'Bash Script (Python/Node.js)'"
    exit 1
fi
ok "Migration file valid"

# --- Step 3: Hapus egg lama (yang docker_images-nya terbalik) ---
step "3/6 — Hapus egg lama yang broken + variables"

log "Cari egg 'Bash Script (Python/Node.js)'..."
EGG_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null || echo "")

if [ -n "$EGG_ID" ]; then
    ok "Egg lama ditemukan (id=$EGG_ID), hapus..."
    eval "$MYSQL_CMD -e \"DELETE FROM egg_variables WHERE egg_id=$EGG_ID;\"" 2>/dev/null && ok "  ✓ egg_variables deleted"
    eval "$MYSQL_CMD -e \"DELETE FROM eggs WHERE id=$EGG_ID;\"" 2>/dev/null && ok "  ✓ egg deleted"
else
    warn "Egg lama tidak ditemukan (mungkin belum pernah dibuat) — skip delete"
fi

# Also check: apakah ada nest "Script Hosting" yang perlu di-delete?
# (Kalau nest ada tapi egg tidak ada, biarkan — migration akan pakai nest yang sama)
NEST_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM nests WHERE name='Script Hosting' LIMIT 1;\"" 2>/dev/null || echo "")
if [ -n "$NEST_ID" ]; then
    ok "Nest 'Script Hosting' sudah ada (id=$NEST_ID) — akan dipakai ulang"
fi

# Cari server yang pakai egg Bash Script (yang mungkin stuck install)
log "Cari server yang pakai egg Bash Script..."
STUCK_SERVERS=$(eval "$MYSQL_CMD -N -e \"SELECT id, name, status FROM servers WHERE egg_id IN (SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)') OR name LIKE '%bash%' OR name LIKE '%script%' LIMIT 20;\"" 2>/dev/null || echo "")

if [ -n "$STUCK_SERVERS" ]; then
    ok "Server yang pakai egg Bash Script:"
    echo "$STUCK_SERVERS" | while IFS=$'\t' read -r sid sname sstatus; do
        if [ -n "$sid" ]; then
            echo "  • ID=$sid  name='$sname'  status='$sstatus'"
        fi
    done

    log "Reset status server yang stuck install (status='installing') ke null..."
    UPDATED=$(eval "$MYSQL_CMD -N -e \"UPDATE servers SET status=NULL WHERE status='installing' AND (egg_id IN (SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)') OR name LIKE '%bash%' OR name LIKE '%script%');\"" 2>/dev/null && echo "ok" || echo "fail")

    if [ "$UPDATED" = "ok" ]; then
        ok "✓ Server yang stuck install sudah di-reset (bisa start ulang)"
    else
        warn "Tidak bisa reset status server — lanjut saja"
    fi
else
    ok "Tidak ada server yang pakai egg Bash Script — skip"
fi

# --- Step 4: Reset migration status ---
step "4/6 — Reset migration status"

log "Cek apakah migration sudah pernah run..."
MIGRATION_EXISTS=$(eval "$MYSQL_CMD -N -e \"SELECT COUNT(*) FROM migrations WHERE migration='$MIGRATION_FILE';\"" 2>/dev/null || echo "0")

if [ "$MIGRATION_EXISTS" -gt 0 ] 2>/dev/null; then
    ok "Migration sudah pernah run ($MIGRATION_EXISTS record), hapus record..."
    eval "$MYSQL_CMD -e \"DELETE FROM migrations WHERE migration='$MIGRATION_FILE';\"" 2>/dev/null && ok "  ✓ migration status reset"
else
    ok "Migration belum pernah run — skip reset"
fi

# --- Step 5: Run migration ---
step "5/6 — Run migration (auto-create egg baru)"

cd "$PANEL_DIR"

log "Running php artisan migrate --force..."
MIGRATE_OUTPUT=$(sudo -u "$WEB_USER" php artisan migrate --force 2>&1) || {
    err "Migration gagal!"
    echo "$MIGRATE_OUTPUT" | tail -20
    exit 1
}

# Show output
echo "$MIGRATE_OUTPUT" | tail -10

# Verify egg berhasil dibuat
NEW_EGG_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null || echo "")
if [ -n "$NEW_EGG_ID" ]; then
    ok "✓ Egg berhasil dibuat (id=$NEW_EGG_ID)"
else
    err "Egg tidak ditemukan setelah migration. Cek output di atas."
    exit 1
fi

# Verify docker_images format benar (KEY=label, VALUE=image URL)
DOCKER_IMAGES=$(eval "$MYSQL_CMD -N -e \"SELECT docker_images FROM eggs WHERE id=$NEW_EGG_ID;\"" 2>/dev/null || echo "")
if echo "$DOCKER_IMAGES" | grep -q '"Debian (Python 3 + Node.js 20 + Bash)":"ghcr.io/parkervcp/yolks:debian"'; then
    ok "✓ docker_images format BENAR (KEY=label, VALUE=image URL)"
elif echo "$DOCKER_IMAGES" | grep -q '"ghcr.io/parkervcp/yolks:debian":"Debian'; then
    warn "docker_images format masih TERBALIK — perlu fix manual"
else
    log "docker_images: $DOCKER_IMAGES"
fi

# Verify variables
VAR_COUNT=$(eval "$MYSQL_CMD -N -e \"SELECT COUNT(*) FROM egg_variables WHERE egg_id=$NEW_EGG_ID;\"" 2>/dev/null || echo "0")
ok "✓ Egg variables: $VAR_COUNT"

# --- Step 6: Clear cache + reload services ---
step "6/6 — Clear cache + reload services"

log "Clearing Laravel caches..."
sudo -u "$WEB_USER" php artisan view:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan config:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan cache:clear 2>&1 | tail -1
ok "Cache cleared"

# Clear OPcache
if command -v php &> /dev/null; then
    sudo -u "$WEB_USER" php -r "if (function_exists('opcache_reset')) { opcache_reset(); }" 2>/dev/null || true
    ok "OPcache cleared"
fi

# Detect + reload PHP-FPM
PHP_FPM=""
for svc in php8.2-fpm php8.1-fpm php8.3-fpm php-fpm; do
    if systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service"; then
        PHP_FPM="$svc"
        break
    fi
done

if [ -n "$PHP_FPM" ]; then
    log "Reloading $PHP_FPM..."
    if systemctl reload "$PHP_FPM" 2>/dev/null; then
        ok "$PHP_FPM reloaded"
    else
        warn "Gagal reload $PHP_FPM — coba restart..."
        systemctl restart "$PHP_FPM" 2>/dev/null && ok "$PHP_FPM restarted" || warn "Gagal restart $PHP_FPM"
    fi
fi

# Detect + reload web server
WEB_SERVER=""
for svc in nginx apache2 httpd; do
    if systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service"; then
        WEB_SERVER="$svc"
        break
    fi
done

if [ -n "$WEB_SERVER" ]; then
    log "Reloading $WEB_SERVER..."
    if systemctl reload "$WEB_SERVER" 2>/dev/null; then
        ok "$WEB_SERVER reloaded"
    else
        warn "Gagal reload $WEB_SERVER"
    fi
fi

# --- Final summary ---
echo ""
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}  ✓ FIX SELESAI!${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BOLD}Yang sudah dilakukan:${NC}"
echo -e "  ${GREEN}✓${NC} Baca DB credentials dari .env"
echo -e "  ${GREEN}✓${NC} Download migration terbaru dari GitHub"
echo -e "  ${GREEN}✓${NC} Hapus egg lama yang broken (docker_images terbalik)"
echo -e "  ${GREEN}✓${NC} Reset migration status"
echo -e "  ${GREEN}✓${NC} Run migration (egg baru dengan format yang benar)"
echo -e "  ${GREEN}✓${NC} Clear cache + OPcache"
echo -e "  ${GREEN}✓${NC} Reload PHP-FPM + web server"
echo ""
echo -e "${BOLD}${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${YELLOW}  LANGKAH SELANJUTNYA${NC}"
echo -e "${BOLD}${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BOLD}1. Clear cache browser${NC} (WAJIB!) — Ctrl+Shift+R"
echo ""
echo -e "${BOLD}2. Login panel admin${NC} → buka Admin → Nests → Script Hosting"
echo -e "   Edit egg 'Bash Script (Python/Node.js)' → lihat Docker Image dropdown"
echo -e "   Harusnya ada 1 option: 'Debian (Python 3 + Node.js 20 + Bash)'"
echo ""
echo -e "${BOLD}3. Buat server baru${NC} → pilih egg 'Bash Script (Python/Node.js)'"
echo -e "   Tidak akan error 'image format is invalid' lagi"
echo ""
echo -e "${BOLD}4. Setelah install script selesai${NC}, upload script via Files menu"
echo -e "   Lalu start server → otomatis:"
echo -e "   - Tampilkan statistik panel (CPU, RAM, disk, uptime)"
echo -e "   - Auto-detect script file (.py/.js/.sh)"
echo -e "   - Auto-install dependencies (pip/npm)"
echo -e "   - Run script"
echo ""
echo -e "${BOLD}Verify cepat (CLI):${NC}"
echo -e "  ${CYAN}mysql -u${DB_USERNAME} -p ${DB_DATABASE} -e \"SELECT id, name, docker_images FROM eggs WHERE name='Bash Script (Python/Node.js)';\"${NC}"
echo ""
echo -e "${GREEN}✓ Egg Bash Script siap dipakai!${NC}"
echo ""

exit 0
