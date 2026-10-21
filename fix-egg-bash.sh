#!/bin/bash
# ============================================================================
# Cloud Server — Fix Bash Script Egg v2 (1-Command Auto Fixer)
# ============================================================================
#
# Cara pakai:
#   curl -L https://raw.githubusercontent.com/marwan585/opik/main/fix-egg-bash.sh | sudo bash
#
# Fix lengkap:
#   1. Download migration terbaru
#   2. Hapus egg lama + reset migration
#   3. Run migration (create egg baru: image python_3.11 + install Node via binary)
#   4. Reset SEMUA server yang stuck install (status=installing → NULL)
#   5. Clear cache + reload
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

# --- Pre-flight ---
step "Pre-flight checks"

[ "$(id -u)" -ne 0 ] && { err "Run as root: sudo $0"; exit 1; }
ok "Running as root"

[ ! -f "$PANEL_DIR/artisan" ] && { err "Panel tidak ditemukan di $PANEL_DIR"; exit 1; }
ok "Panel: $PANEL_DIR"

WEB_USER="www-data"
id -u nginx &>/dev/null && WEB_USER="nginx"
ok "Web user: $WEB_USER"

command -v php &>/dev/null || { err "PHP tidak ditemukan"; exit 1; }
command -v mysql &>/dev/null || { err "mysql CLI tidak ditemukan"; exit 1; }
command -v curl &>/dev/null || { err "curl tidak ditemukan"; exit 1; }
ok "PHP, mysql, curl tersedia"

# --- Read DB credentials ---
step "1/7 — Baca DB credentials"

DB_DATABASE=$(grep -E "^DB_DATABASE=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep -E "^DB_USERNAME=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep -E "^DB_PASSWORD=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_HOST=$(grep -E "^DB_HOST=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "127.0.0.1")
DB_PORT=$(grep -E "^DB_PORT=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "3306")

[ -z "$DB_DATABASE" ] && { err "DB_DATABASE tidak ditemukan di .env"; exit 1; }
ok "DB: $DB_DATABASE @ $DB_HOST:$DB_PORT"

if [ -n "$DB_PASSWORD" ]; then
    MYSQL_CMD="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p'${DB_PASSWORD}' ${DB_DATABASE}"
else
    MYSQL_CMD="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} ${DB_DATABASE}"
fi

eval "$MYSQL_CMD -e 'SELECT 1'" &>/dev/null || { err "Gagal koneksi DB"; exit 1; }
ok "Koneksi DB OK"

# --- Step 2: Download migration ---
step "2/7 — Download migration terbaru"

MIGRATION_PATH="$PANEL_DIR/database/migrations/$MIGRATION_FILE"
curl -L --fail -o "$MIGRATION_PATH" "$REPO_MIGRATION_URL" || { err "Gagal download migration"; exit 1; }
chown "$WEB_USER":"$WEB_USER" "$MIGRATION_PATH"
chmod 644 "$MIGRATION_PATH"
ok "Migration downloaded ($(du -h "$MIGRATION_PATH" | cut -f1))"

# --- Step 3: Reset ALL stuck servers (status=installing) ---
step "3/7 — Reset server yang stuck install"

log "Cari server dengan status='installing'..."
STUCK_COUNT=$(eval "$MYSQL_CMD -N -e \"SELECT COUNT(*) FROM servers WHERE status='installing';\"" 2>/dev/null || echo "0")

if [ "$STUCK_COUNT" -gt 0 ] 2>/dev/null; then
    ok "Ditemukan $STUCK_COUNT server stuck install:"
    eval "$MYSQL_CMD -e \"SELECT id, name, status FROM servers WHERE status='installing';\"" 2>/dev/null

    log "Reset status ke NULL..."
    eval "$MYSQL_CMD -e \"UPDATE servers SET status=NULL WHERE status='installing';\"" 2>/dev/null
    ok "✓ $STUCK_COUNT server di-reset — bisa start ulang"
else
    ok "Tidak ada server stuck install"
fi

# --- Step 4: Hapus egg lama ---
step "4/7 — Hapus egg lama"

EGG_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null || echo "")
if [ -n "$EGG_ID" ]; then
    eval "$MYSQL_CMD -e \"DELETE FROM egg_variables WHERE egg_id=$EGG_ID;\"" 2>/dev/null
    eval "$MYSQL_CMD -e \"DELETE FROM eggs WHERE id=$EGG_ID;\"" 2>/dev/null
    ok "Egg lama (id=$EGG_ID) dihapus"
else
    ok "Egg lama tidak ada — skip"
fi

# --- Step 5: Reset migration status ---
step "5/7 — Reset migration status"

eval "$MYSQL_CMD -e \"DELETE FROM migrations WHERE migration='$MIGRATION_FILE';\"" 2>/dev/null
ok "Migration status reset"

# --- Step 6: Run migration ---
step "6/7 — Run migration (egg baru: Python image + Node binary install)"

cd "$PANEL_DIR"
MIGRATE_OUTPUT=$(sudo -u "$WEB_USER" php artisan migrate --force 2>&1) || {
    err "Migration gagal!"
    echo "$MIGRATE_OUTPUT" | tail -20
    exit 1
}
echo "$MIGRATE_OUTPUT" | tail -10

# Verify
NEW_EGG_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null || echo "")
if [ -n "$NEW_EGG_ID" ]; then
    ok "✓ Egg baru dibuat (id=$NEW_EGG_ID)"

    # Verify docker_images
    DI=$(eval "$MYSQL_CMD -N -e \"SELECT docker_images FROM eggs WHERE id=$NEW_EGG_ID;\"" 2>/dev/null)
    echo "  docker_images: $DI"

    # Verify install script (harus TIDAK ada apt-get)
    SI=$(eval "$MYSQL_CMD -N -e \"SELECT script_install FROM eggs WHERE id=$NEW_EGG_ID;\"" 2>/dev/null)
    if echo "$SI" | grep -q "apt-get install"; then
        warn "⚠ Install script masih ada apt-get! Manual fix needed."
    else
        ok "✓ Install script tidak ada apt-get (FAST install)"
    fi

    # Verify startup script (harus simple, hanya panggil runner.sh)
    SU=$(eval "$MYSQL_CMD -N -e \"SELECT startup FROM eggs WHERE id=$NEW_EGG_ID;\"" 2>/dev/null)
    if echo "$SU" | grep -q "runner.sh"; then
        ok "✓ Startup script simple (panggil runner.sh)"
    else
        warn "⚠ Startup script bukan runner.sh — masih kompleks"
    fi
else
    err "Egg tidak dibuat — cek error di atas"
    exit 1
fi

# ===== Reinstall semua server yang pakai egg Bash Script =====
# supaya download runner.sh yang baru
step "6.5/7 — Trigger reinstall server yang pakai egg Bash"

log "Cari server yang pakai egg Bash Script..."
SERVERS_USING_EGG=$(eval "$MYSQL_CMD -N -e \"SELECT id, name FROM servers WHERE egg_id='$NEW_EGG_ID';\"" 2>/dev/null || echo "")

if [ -n "$SERVERS_USING_EGG" ]; then
    ok "Server yang pakai egg Bash Script:"
    echo "$SERVERS_USING_EGG" | while IFS=$'\t' read -r sid sname; do
        if [ -n "$sid" ]; then
            echo "  • ID=$sid  name='$sname'"

            # Set status=installing supaya panel re-trigger install
            # saat user start server
            eval "$MYSQL_CMD -e \"UPDATE servers SET status='installing' WHERE id=$sid;\"" 2>/dev/null && \
                echo "    → status set to 'installing' (akan re-install saat start)"
        fi
    done
    ok "✓ Server akan re-install saat user start (download runner.sh baru)"
else
    ok "Tidak ada server yang pakai egg Bash Script — skip reinstall"
fi

# --- Step 7: Clear cache + reload ---
step "7/7 — Clear cache + reload"

cd "$PANEL_DIR"
sudo -u "$WEB_USER" php artisan view:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan config:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan cache:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php -r "if(function_exists('opcache_reset'))opcache_reset();" 2>/dev/null
ok "Cache + OPcache cleared"

# Reload PHP-FPM
PHP_FPM=""
for svc in php8.2-fpm php8.1-fpm php8.3-fpm php-fpm; do
    systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service" && PHP_FPM="$svc" && break
done
[ -n "$PHP_FPM" ] && systemctl reload "$PHP_FPM" 2>/dev/null && ok "$PHP_FPM reloaded"

# Reload web server
for svc in nginx apache2 httpd; do
    if systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service"; then
        systemctl reload "$svc" 2>/dev/null && ok "$svc reloaded"
        break
    fi
done

# --- Summary ---
echo ""
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}  ✓ FIX SELESAI!${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BOLD}Yang sudah dilakukan:${NC}"
echo -e "  ✓ Download migration terbaru (Python image + Node binary install)"
echo -e "  ✓ Reset $STUCK_COUNT server yang stuck install"
echo -e "  ✓ Hapus egg lama + create egg baru"
echo -e "  ✓ Clear cache + reload services"
echo ""
echo -e "${BOLD}${YELLOW}LANGKAH SELANJUTNYA:${NC}"
echo ""
echo -e "${BOLD}1. Clear cache browser${NC} (Ctrl+Shift+R)"
echo ""
echo -e "${BOLD}2. Login panel → buka server Anda (MUHAMMAD ALI / Marwan)"
echo -e "   Status sekarang harusnya bukan 'installing' lagi${NC}"
echo ""
echo -e "${BOLD}3. Klik Stop${NC} (kalau masih running)"
echo -e "   Lalu klik ${BOLD}Start${NC} lagi"
echo ""
echo -e "${BOLD}4. Install akan selesai dalam ~10 DETIK${NC} (bukan 1 jam!)"
echo -e "   Karena sekarang pakai:"
echo -e "   - Runtime image: ghcr.io/parkervcp/yolks:python_3.11 (Python pre-installed)"
echo -e "   - Install script: download Node.js binary (~25MB, bukan apt-get ratusan MB)"
echo ""
echo -e "${BOLD}5. Setelah install selesai${NC}, upload script via Files"
echo -e "   Lalu start server → otomatis detect + run"
echo ""
echo -e "${BOLD}Jika server masih stuck install:${NC}"
echo -e "  - Stop server di panel"
echo -e "  - Jalankan: ${CYAN}sudo systemctl restart wings${NC}"
echo -e "  - Start server lagi"
echo ""
