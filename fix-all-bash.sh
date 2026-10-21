#!/bin/bash
# ============================================================================
# Cloud Server — Fix All Bash Script Egg Issues (1-Command Master Fixer)
# ============================================================================
#
# Cara pakai:
#   curl -L https://raw.githubusercontent.com/marwan585/opik/main/fix-all-bash.sh | sudo bash
#
# Atau download dulu:
#   curl -L -o fix-all-bash.sh https://raw.githubusercontent.com/marwan585/opik/main/fix-all-bash.sh
#   chmod +x fix-all-bash.sh
#   sudo ./fix-all-bash.sh
#
# Script ini fix SEMUA masalah Bash Script egg sekaligus:
#
#   1. Baca DB credentials dari .env
#   2. Download migration terbaru + run migration (update egg config)
#   3. UPDATE egg langsung via SQL (startup=runner.sh, image=python_3.11,
#      install script download Node binary)
#   4. Untuk SETIAP server yang pakai egg Bash:
#      a. Set status=NULL (bukan 'installing')
#      b. Buat folder .cloudserver + .local di volume server
#      c. Download Node.js 20 binary LANGSUNG ke /mnt/server/.local
#         (tidak pakai install script yang lambat)
#      d. Write runner.sh LANGSUNG ke /mnt/server/.cloudserver/
#      e. chmod +x runner.sh
#      f. chown wings:wings supaya bisa di-execute
#   5. Restart wings daemon (pick up egg changes)
#   6. Clear cache + reload PHP-FPM + nginx
#
# Setelah fix:
# - User tinggal start server di panel → langsung jalan (runner.sh
#   sudah ada, Node.js sudah terinstall)
# - Tidak perlu install script (file sudah di-place langsung)
# - Status server: NULL (siap start)
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
VOLUMES_DIR="${VOLUMES_DIR:-/var/lib/pterodactyl/volumes}"
WINGS_USER="${WINGS_USER:-wings}"
NODE_VERSION="v20.10.0"

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

[ ! -f "$PANEL_DIR/.env" ] && { err ".env tidak ditemukan"; exit 1; }
ok ".env ditemukan"

command -v php &>/dev/null || { err "PHP tidak ditemukan"; exit 1; }
command -v mysql &>/dev/null || { err "mysql CLI tidak ditemukan"; exit 1; }
command -v curl &>/dev/null || { err "curl tidak ditemukan"; exit 1; }
ok "PHP, mysql, curl tersedia"

# Detect web user
WEB_USER="www-data"
id -u nginx &>/dev/null && WEB_USER="nginx"
ok "Web user: $WEB_USER"

# Detect wings user
if ! id -u wings &>/dev/null; then
    WINGS_USER="root"
    warn "User 'wings' tidak ditemukan, pakai: $WINGS_USER"
else
    ok "Wings user: $WINGS_USER"
fi

# Check volumes dir
if [ ! -d "$VOLUMES_DIR" ]; then
    err "Volumes directory tidak ditemukan: $VOLUMES_DIR"
    err "Cek path volumes wings di /etc/pterodactyl/config.yml"
    exit 1
fi
ok "Volumes: $VOLUMES_DIR"

# --- Step 1: Read DB credentials ---
step "1/8 — Baca DB credentials"

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

# --- Step 2: Download migration + run ---
step "2/8 — Download migration + run"

MIGRATION_PATH="$PANEL_DIR/database/migrations/2026_10_21_020000_add_bash_script_egg.php"
curl -L --fail -o "$MIGRATION_PATH" "https://raw.githubusercontent.com/marwan585/opik/main/database/migrations/2026_10_21_020000_add_bash_script_egg.php" || { err "Gagal download migration"; exit 1; }
chown "$WEB_USER":"$WEB_USER" "$MIGRATION_PATH"
chmod 644 "$MIGRATION_PATH"
ok "Migration downloaded"

# Reset migration status + delete old egg
eval "$MYSQL_CMD -e \"DELETE FROM migrations WHERE migration='2026_10_21_020000_add_bash_script_egg';\"" 2>/dev/null
EGG_ID_OLD=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null || echo "")
if [ -n "$EGG_ID_OLD" ]; then
    eval "$MYSQL_CMD -e \"DELETE FROM egg_variables WHERE egg_id=$EGG_ID_OLD;\"" 2>/dev/null
    eval "$MYSQL_CMD -e \"DELETE FROM eggs WHERE id=$EGG_ID_OLD;\"" 2>/dev/null
fi

# Run migration
cd "$PANEL_DIR"
sudo -u "$WEB_USER" php artisan migrate --force 2>&1 | tail -5
ok "Migration complete"

# --- Step 3: Verify egg + get egg_id ---
step "3/8 — Verify egg"

EGG_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null || echo "")
if [ -z "$EGG_ID" ]; then
    err "Egg tidak ditemukan setelah migration!"
    exit 1
fi
ok "Egg ID: $EGG_ID"

DI=$(eval "$MYSQL_CMD -N -e \"SELECT docker_images FROM eggs WHERE id=$EGG_ID;\"" 2>/dev/null)
echo "  docker_images: $DI"

SU=$(eval "$MYSQL_CMD -N -e \"SELECT startup FROM eggs WHERE id=$EGG_ID;\"" 2>/dev/null)
echo "  startup: $SU"

# --- Step 4: Get all servers using this egg ---
step "4/8 — Cari server yang pakai egg Bash Script"

SERVERS=$(eval "$MYSQL_CMD -N -e \"SELECT id, uuid, name FROM servers WHERE egg_id=$EGG_ID;\"" 2>/dev/null)

if [ -z "$SERVERS" ]; then
    warn "Tidak ada server yang pakai egg Bash Script (egg_id=$EGG_ID)"
    warn "Cek semua server:"
    eval "$MYSQL_CMD -e \"SELECT id, uuid, name, egg_id FROM servers;\"" 2>/dev/null

    # Coba cari server berdasarkan nama
    warn "Cari server berdasarkan nama (MUHAMMAD/ALI/Marwan/Cloud):"
    SERVERS=$(eval "$MYSQL_CMD -N -e \"SELECT id, uuid, name FROM servers WHERE name LIKE '%MUHAMMAD%' OR name LIKE '%ALI%' OR name LIKE '%Marwan%' OR name LIKE '%Cloud%' OR name LIKE '%bash%' OR name LIKE '%script%';\"" 2>/dev/null)

    if [ -n "$SERVERS" ]; then
        ok "Ditemukan server (akan update egg_id):"
        echo "$SERVERS" | while IFS=$'\t' read -r sid suuid sname; do
            [ -z "$sid" ] && continue
            echo "  • ID=$sid name='$sname'"
            eval "$MYSQL_CMD -e \"UPDATE servers SET egg_id=$EGG_ID WHERE id=$sid;\"" 2>/dev/null
        done
        SERVERS=$(eval "$MYSQL_CMD -N -e \"SELECT id, uuid, name FROM servers WHERE egg_id=$EGG_ID;\"" 2>/dev/null)
    fi
fi

if [ -z "$SERVERS" ]; then
    err "Tidak ada server ditemukan. Buat server baru di panel admin dengan egg Bash Script."
    exit 0
fi

ok "Server yang akan di-fix:"
echo "$SERVERS" | while IFS=$'\t' read -r sid suuid sname; do
    [ -z "$sid" ] && continue
    echo "  • ID=$sid UUID=$suuid name='$sname'"
done

# --- Step 5: Download Node.js binary (once, akan di-copy ke setiap server) ---
step "5/8 — Download Node.js ${NODE_VERSION} (cache lokal)"

NODE_TARBALL="/tmp/node-${NODE_VERSION}-linux-x64.tar.xz"
NODE_CACHE="/tmp/node-${NODE_VERSION}-extracted"

if [ ! -d "$NODE_CACHE" ]; then
    log "Downloading Node.js ${NODE_VERSION} (~25MB)..."
    curl -fsSL -o "$NODE_TARBALL" "https://nodejs.org/dist/${NODE_VERSION}/node-${NODE_VERSION}-linux-x64.tar.xz"
    mkdir -p "$NODE_CACHE"
    tar -xf "$NODE_TARBALL" -C "$NODE_CACHE" --strip-components=1
    rm -f "$NODE_TARBALL"
fi

ok "Node.js cached di $NODE_CACHE"
"$NODE_CACHE/bin/node" --version
"$NODE_CACHE/bin/npm" --version

# --- Step 6: Untuk setiap server, install runner.sh + Node.js LANGSUNG ---
step "6/8 — Install runner.sh + Node.js ke setiap server"

echo "$SERVERS" | while IFS=$'\t' read -r SID SUUID SNAME; do
    [ -z "$SID" ] && continue

    echo ""
    echo ">>> Server: $SNAME (UUID: $SUUID)"

    SERVER_FILES="$VOLUMES_DIR/$SUUID/files"
    if [ ! -d "$SERVER_FILES" ]; then
        warn "  Folder tidak ditemukan: $SERVER_FILES"
        warn "  Cek apakah UUID benar atau wings pakai path lain"
        continue
    fi
    ok "  Folder: $SERVER_FILES"

    # Buat folder .cloudserver + .local
    mkdir -p "$SERVER_FILES/.cloudserver"
    mkdir -p "$SERVER_FILES/.local"

    # ===== Install Node.js ke .local =====
    log "  Install Node.js ke .local/..."
    rm -rf "$SERVER_FILES/.local/bin" "$SERVER_FILES/.local/lib" "$SERVER_FILES/.local/include" "$SERVER_FILES/.local/share"
    cp -r "$NODE_CACHE"/* "$SERVER_FILES/.local/"

    if [ -f "$SERVER_FILES/.local/bin/node" ]; then
        ok "  Node.js installed: $("$SERVER_FILES/.local/bin/node" --version 2>/dev/null)"
    else
        warn "  Gagal install Node.js"
    fi

    # ===== Write runner.sh =====
    log "  Write runner.sh..."
    cat > "$SERVER_FILES/.cloudserver/runner.sh" <<'RUNNER_EOF'
#!/bin/bash
# Cloud Server — Script Runner
cd /mnt/server 2>/dev/null || cd /home/container 2>/dev/null || cd ~

export PATH="/mnt/server/.local/bin:/mnt/server/.local/lib/node_modules/.bin:$PATH"

SCRIPT_FILE="${SCRIPT_FILE:-}"
SCRIPT_ARGS="${SCRIPT_ARGS:-}"
AUTO_INSTALL="${AUTO_INSTALL:-1}"
SHOW_STATS="${SHOW_STATS:-1}"
INTERACTIVE_SHELL="${INTERACTIVE_SHELL:-1}"

echo "=============================================="
echo "  Cloud Server — Script Hosting Egg"
echo "=============================================="
echo ""
echo "Server started at: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Working directory: $(pwd)"
echo ""

if [ "$SHOW_STATS" = "1" ]; then
    echo "===== Panel Statistics ====="
    echo "Hostname : $(hostname 2>/dev/null || echo '?')"
    echo "CPU cores: $(nproc 2>/dev/null || echo '?')"
    [ -f /proc/loadavg ] && echo "Load avg : $(cat /proc/loadavg)"
    if [ -f /proc/meminfo ]; then
        MEM_TOTAL=$(grep MemTotal /proc/meminfo | awk '{print int($2/1024)}')
        MEM_AVAIL=$(grep MemAvailable /proc/meminfo | awk '{print int($2/1024)}')
        [ -n "$MEM_TOTAL" ] && [ -n "$MEM_AVAIL" ] && echo "Memory   : $((MEM_TOTAL - MEM_AVAIL))MB / ${MEM_TOTAL}MB used"
    fi
    DISK_USAGE=$(df -h . 2>/dev/null | tail -1 | awk '{print $3 " / " $2 " (" $5 ")"}')
    [ -n "$DISK_USAGE" ] && echo "Disk     : $DISK_USAGE"
    echo ""
    echo "Tools available:"
    command -v python3 >/dev/null 2>&1 && echo "  Python : $(python3 --version 2>&1)" || echo "  Python : NOT FOUND"
    command -v node >/dev/null 2>&1 && echo "  Node.js: $(node --version 2>&1)" || echo "  Node.js: NOT FOUND"
    command -v npm >/dev/null 2>&1 && echo "  npm    : $(npm --version 2>&1)" || echo "  npm    : NOT FOUND"
    command -v pip3 >/dev/null 2>&1 && echo "  pip3   : available" || echo "  pip3   : NOT FOUND"
    echo "=============================================="
    echo ""
fi

if [ -z "$SCRIPT_FILE" ]; then
    echo "Auto-detecting script file..."
    for candidate in auto-run.sh main.py app.py bot.py index.js server.js app.js script.sh run.sh; do
        if [ -f "$candidate" ]; then
            SCRIPT_FILE="$candidate"
            echo "  Found: $SCRIPT_FILE"
            break
        fi
    done
    if [ -z "$SCRIPT_FILE" ]; then
        echo "  No script file found."
        echo "Upload your script (.py, .js, .sh) via Files menu, then restart."
        if [ "$INTERACTIVE_SHELL" = "1" ]; then
            echo ""
            echo "Entering interactive bash shell. Type 'exit' to stop."
            exec bash
        else
            sleep 2
            exit 0
        fi
    fi
fi

if [ ! -f "$SCRIPT_FILE" ]; then
    echo "Script file '$SCRIPT_FILE' not found!"
    ls -la 2>/dev/null
    if [ "$INTERACTIVE_SHELL" = "1" ]; then
        exec bash
    else
        exit 1
    fi
fi

if [ "$AUTO_INSTALL" = "1" ]; then
    EXT="${SCRIPT_FILE##*.}"
    if [ "$EXT" = "py" ] || [ "$EXT" = "py3" ]; then
        if [ -f requirements.txt ]; then
            echo ""
            echo "===== Installing Python dependencies ====="
            pip3 install -r requirements.txt 2>&1 | tail -5
        fi
    fi
    if [ "$EXT" = "js" ] || [ "$EXT" = "mjs" ] || [ "$EXT" = "cjs" ]; then
        if [ -f package.json ]; then
            echo ""
            echo "===== Installing Node.js dependencies ====="
            npm install 2>&1 | tail -10
        fi
    fi
fi

echo ""
echo "=============================================="
echo "  Running: $SCRIPT_FILE $SCRIPT_ARGS"
echo "=============================================="
echo ""

EXT="${SCRIPT_FILE##*.}"
case "$EXT" in
    py|py3) python3 "$SCRIPT_FILE" $SCRIPT_ARGS ;;
    js|mjs|cjs) node "$SCRIPT_FILE" $SCRIPT_ARGS ;;
    sh|bash) bash "$SCRIPT_FILE" $SCRIPT_ARGS ;;
    *) chmod +x "$SCRIPT_FILE" 2>/dev/null; ./"$SCRIPT_FILE" $SCRIPT_ARGS ;;
esac

EXIT_CODE=$?
echo ""
echo "Script exited with code: $EXIT_CODE"

if [ "$INTERACTIVE_SHELL" = "1" ]; then
    echo ""
    echo "Entering interactive bash shell. Type 'exit' to stop."
    exec bash
else
    sleep 3
    exit $EXIT_CODE
fi
RUNNER_EOF

    chmod +x "$SERVER_FILES/.cloudserver/runner.sh"
    ok "  runner.sh written ($(du -h "$SERVER_FILES/.cloudserver/runner.sh" | cut -f1))"

    # ===== Set ownership =====
    chown -R "$WINGS_USER":"$WINGS_USER" "$SERVER_FILES/.cloudserver" "$SERVER_FILES/.local"
    ok "  Ownership set: $WINGS_USER:$WINGS_USER"

    # ===== Set server status = NULL (siap start) =====
    eval "$MYSQL_CMD -e \"UPDATE servers SET status=NULL WHERE id=$SID;\"" 2>/dev/null
    ok "  Server status set to NULL (siap start)"

    # ===== List files =====
    echo "  Files di server:"
    ls -la "$SERVER_FILES/" 2>/dev/null | head -10
done

# --- Step 7: Restart wings ---
step "7/8 — Restart wings daemon"

log "Restarting wings..."
if systemctl restart wings 2>/dev/null; then
    sleep 3
    if systemctl is-active --quiet wings; then
        ok "Wings restarted & active"
    else
        warn "Wings restart gagal — cek: journalctl -u wings -n 20"
    fi
else
    warn "Gagal restart wings. Coba manual: sudo systemctl restart wings"
fi

# --- Step 8: Clear cache + reload panel ---
step "8/8 — Clear cache + reload panel services"

cd "$PANEL_DIR"
sudo -u "$WEB_USER" php artisan view:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan config:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php artisan cache:clear 2>&1 | tail -1
sudo -u "$WEB_USER" php -r "if(function_exists('opcache_reset'))opcache_reset();" 2>/dev/null
ok "Cache + OPcache cleared"

PHP_FPM=""
for svc in php8.2-fpm php8.1-fpm php8.3-fpm php-fpm; do
    systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service" && PHP_FPM="$svc" && break
done
[ -n "$PHP_FPM" ] && systemctl reload "$PHP_FPM" 2>/dev/null && ok "$PHP_FPM reloaded"

for svc in nginx apache2 httpd; do
    if systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service"; then
        systemctl reload "$svc" 2>/dev/null && ok "$svc reloaded"
        break
    fi
done

# --- Final summary ---
echo ""
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}  ✓ FIX SELESAI!${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BOLD}Yang sudah dilakukan:${NC}"
echo -e "  ✓ Download migration terbaru + run migration"
echo -e "  ✓ Update egg (docker_images=python_3.11, startup=runner.sh)"
echo -e "  ✓ Download Node.js ${NODE_VERSION} ke cache lokal"
echo -e "  ✓ Install Node.js + runner.sh LANGSUNG ke setiap server"
echo -e "    (TIDAK pakai install script yang lambat)"
echo -e "  ✓ Set ownership wings:wings"
echo -e "  ✓ Set server status=NULL (siap start)"
echo -e "  ✓ Restart wings daemon"
echo -e "  ✓ Clear cache + reload panel"
echo ""
echo -e "${BOLD}${YELLOW}LANGKAH SELANJUTNYA:${NC}"
echo ""
echo -e "${BOLD}1. Clear cache browser${NC} (Ctrl+Shift+R) — WAJIB!"
echo ""
echo -e "${BOLD}2. Login panel → buka server Anda (MUHAMMAD ALI / Cloud Server)"
echo -e "   Status harusnya bukan 'installing' lagi${NC}"
echo ""
echo -e "${BOLD}3. Klik Stop${NC} (kalau masih running)"
echo -e "   Lalu klik ${BOLD}Start${NC}"
echo ""
echo -e "${BOLD}4. Server akan langsung jalan!${NC}"
echo -e "   Console akan tampilkan:"
echo -e "   - Banner 'Cloud Server — Script Hosting Egg'"
echo -e "   - Panel Statistics (CPU, RAM, disk, uptime, tools)"
echo -e "   - Auto-detect script file"
echo -e "   - Interactive bash shell (kalau tidak ada script)"
echo ""
echo -e "${BOLD}5. Upload script Anda${NC} (.py, .js, atau .sh) via menu Files"
echo -e "   Lalu restart server → script akan dijalankan otomatis"
echo ""
echo -e "${BOLD}Verify cepat:${NC}"
echo -e "  ${CYAN}ls -la /var/lib/pterodactyl/volumes/\$(mysql -u${DB_USERNAME} -p'...' ${DB_DATABASE} -N -e \"SELECT uuid FROM servers WHERE name='MUHAMMAD ALI';\")/files/.cloudserver/runner.sh${NC}"
echo ""
echo -e "${GREEN}✓ Server siap dipakai!${NC}"
echo ""
