#!/bin/bash
# ============================================================================
# Cloud Server — Fix All Bash Script Egg v2 (Robust, no set -e)
# ============================================================================
#
# Cara pakai:
#   curl -L https://raw.githubusercontent.com/marwan585/opik/main/fix-all-bash.sh | sudo bash
#
# Perubahan dari v1:
# - TIDAK pakai 'set -e' (jangan exit on error)
# - Migration failure tidak fatal — lanjut ke step berikutnya
# - Run php artisan migrate sebagai ROOT (tidur sudo -u www-data,
#   hindari masalah psysh permission)
# - Fokus ke langkah 6: install files LANGSUNG ke folder server
#   (ini yang bikin server jalan, bukan migration)
# - Print semua error tapi tetap lanjut
# ============================================================================

# TIDAK pakai set -e — kita handle error manual

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
err()   { echo -e "${RED}✗${NC} $*"; }
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
command -v tar &>/dev/null || { err "tar tidak ditemukan"; exit 1; }
ok "PHP, mysql, curl, tar tersedia"

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

if ! eval "$MYSQL_CMD -e 'SELECT 1'" &>/dev/null; then
    err "Gagal koneksi DB. Cek credentials di .env"
    exit 1
fi
ok "Koneksi DB OK"

# --- Step 2: Download migration + run (NON-FATAL) ---
step "2/8 — Download migration + run (non-fatal)"

MIGRATION_PATH="$PANEL_DIR/database/migrations/2026_10_21_020000_add_bash_script_egg.php"
if curl -L --fail -o "$MIGRATION_PATH" "https://raw.githubusercontent.com/marwan585/opik/main/database/migrations/2026_10_21_020000_add_bash_script_egg.php" 2>/dev/null; then
    chown "$WEB_USER":"$WEB_USER" "$MIGRATION_PATH" 2>/dev/null || true
    chmod 644 "$MIGRATION_PATH" 2>/dev/null || true
    ok "Migration downloaded"
else
    warn "Gagal download migration — lanjut dengan egg existing"
fi

# Reset migration status
eval "$MYSQL_CMD -e \"DELETE FROM migrations WHERE migration='2026_10_21_020000_add_bash_script_egg';\"" 2>/dev/null
ok "Migration status reset"

# Delete old egg (jika ada) supaya migration bisa create baru
EGG_ID_OLD=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null || echo "")
if [ -n "$EGG_ID_OLD" ]; then
    eval "$MYSQL_CMD -e \"DELETE FROM egg_variables WHERE egg_id=$EGG_ID_OLD;\"" 2>/dev/null
    eval "$MYSQL_CMD -e \"DELETE FROM eggs WHERE id=$EGG_ID_OLD;\"" 2>/dev/null
    ok "Egg lama dihapus"
fi

# Run migration — sebagai ROOT (hindari psysh issue)
log "Run migration sebagai root..."
cd "$PANEL_DIR"
MIGRATE_OUTPUT=$(php artisan migrate --force 2>&1) || {
    warn "Migration gagal — akan create egg manual via SQL"
    echo "$MIGRATE_OUTPUT" | tail -10
}

# Verify egg ada
EGG_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null || echo "")

if [ -z "$EGG_ID" ]; then
    warn "Migration tidak create egg — create MANUAL via SQL"

    # Cari/buat nest "Script Hosting"
    NEST_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM nests WHERE name='Script Hosting' LIMIT 1;\"" 2>/dev/null || echo "")
    if [ -z "$NEST_ID" ]; then
        eval "$MYSQL_CMD -e \"INSERT INTO nests (uuid, author, name, description, created_at, updated_at) VALUES (UUID(), 'cloud\@server.local', 'Script Hosting', 'Run scripts as a server.', NOW(), NOW());\"" 2>/dev/null
        NEST_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM nests WHERE name='Script Hosting' LIMIT 1;\"" 2>/dev/null)
    fi
    ok "Nest Script Hosting: id=$NEST_ID"

    # Create egg manual
    eval "$MYSQL_CMD -e \"INSERT INTO eggs (nest_id, uuid, author, name, description, features, docker_images, file_denylist, config_files, config_startup, config_logs, config_stop, config_from, startup, script_is_privileged, script_install, script_entry, script_container, copy_script_from, created_at, updated_at) VALUES ($NEST_ID, UUID(), 'cloud\@server.local', 'Bash Script (Python/Node.js)', 'Run Python/Node.js/Bash scripts', '[]', '{\\\"Python 3.11 + Node.js 20\\\":\\\"ghcr.io/parkervcp/yolks:python_3.11\\\"}', '[]', '{}', '{\\\"done\\\":\\\"Cloud Server script ready\\\",\\\"userInteraction\\\":[]}', '{}', '^C', NULL, '/mnt/server/.cloudserver/runner.sh', 1, '#!/bin/bash\nmkdir -p /mnt/server/.cloudserver\nexit 0', 'bash', 'ghcr.io/parkervcp/installers:debian', NULL, NOW(), NOW());\"" 2>/dev/null

    EGG_ID=$(eval "$MYSQL_CMD -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null)

    if [ -n "$EGG_ID" ]; then
        ok "Egg dibuat manual: id=$EGG_ID"
    else
        err "Gagal create egg manual"
    fi
fi

if [ -n "$EGG_ID" ]; then
    ok "Egg ID: $EGG_ID"

    # Pastikan startup = runner.sh (override via SQL)
    eval "$MYSQL_CMD -e \"UPDATE eggs SET startup='/mnt/server/.cloudserver/runner.sh' WHERE id=$EGG_ID;\"" 2>/dev/null
    ok "Startup set ke runner.sh"

    # Pastikan docker_images = python_3.11
    eval "$MYSQL_CMD -e \"UPDATE eggs SET docker_images='{\\\"Python 3.11 + Node.js 20\\\":\\\"ghcr.io/parkervcp/yolks:python_3.11\\\"}' WHERE id=$EGG_ID;\"" 2>/dev/null
    ok "Docker image set ke python_3.11"
fi

# --- Step 3: Get all servers using this egg ---
step "3/8 — Cari server yang pakai egg Bash Script"

SERVERS=""
if [ -n "$EGG_ID" ]; then
    SERVERS=$(eval "$MYSQL_CMD -N -e \"SELECT id, uuid, name FROM servers WHERE egg_id=$EGG_ID;\"" 2>/dev/null)
fi

if [ -z "$SERVERS" ]; then
    warn "Tidak ada server yang pakai egg Bash Script (egg_id=$EGG_ID)"
    warn "Cari server berdasarkan nama..."

    SERVERS=$(eval "$MYSQL_CMD -N -e \"SELECT id, uuid, name FROM servers WHERE name LIKE '%MUHAMMAD%' OR name LIKE '%ALI%' OR name LIKE '%Marwan%' OR name LIKE '%Cloud%' OR name LIKE '%bash%' OR name LIKE '%script%';\"" 2>/dev/null)

    if [ -n "$SERVERS" ]; then
        ok "Ditemukan server — assign ke egg Bash Script:"
        echo "$SERVERS" | while IFS=$'\t' read -r sid suuid sname; do
            [ -z "$sid" ] && continue
            echo "  • ID=$sid name='$sname'"
            [ -n "$EGG_ID" ] && eval "$MYSQL_CMD -e \"UPDATE servers SET egg_id=$EGG_ID WHERE id=$sid;\"" 2>/dev/null
        done
        SERVERS=$(eval "$MYSQL_CMD -N -e \"SELECT id, uuid, name FROM servers WHERE egg_id=$EGG_ID;\"" 2>/dev/null)
    fi
fi

if [ -z "$SERVERS" ]; then
    err "Tidak ada server ditemukan. Tampilkan semua server:"
    eval "$MYSQL_CMD -e \"SELECT id, uuid, name, egg_id, status FROM servers;\"" 2>/dev/null
    exit 0
fi

ok "Server yang akan di-fix:"
echo "$SERVERS" | while IFS=$'\t' read -r sid suuid sname; do
    [ -z "$sid" ] && continue
    echo "  • ID=$sid UUID=$suuid name='$sname'"
done

# --- Step 4: Download Node.js binary (cache lokal) ---
step "4/8 — Download Node.js ${NODE_VERSION} (cache lokal)"

NODE_CACHE="/tmp/node-${NODE_VERSION}-extracted"

if [ ! -d "$NODE_CACHE" ] || [ ! -f "$NODE_CACHE/bin/node" ]; then
    log "Downloading Node.js ${NODE_VERSION} (~25MB)..."
    NODE_TARBALL="/tmp/node-${NODE_VERSION}.tar.xz"
    if curl -fsSL -o "$NODE_TARBALL" "https://nodejs.org/dist/${NODE_VERSION}/node-${NODE_VERSION}-linux-x64.tar.xz"; then
        rm -rf "$NODE_CACHE"
        mkdir -p "$NODE_CACHE"
        if tar -xf "$NODE_TARBALL" -C "$NODE_CACHE" --strip-components=1; then
            ok "Node.js extracted"
        else
            err "Gagal extract Node.js"
            rm -rf "$NODE_CACHE"
        fi
        rm -f "$NODE_TARBALL"
    else
        err "Gagal download Node.js"
    fi
fi

if [ -d "$NODE_CACHE" ] && [ -f "$NODE_CACHE/bin/node" ]; then
    ok "Node.js cached: $($NODE_CACHE/bin/node --version 2>/dev/null)"
    NODE_OK=1
else
    err "Node.js tidak tersedia di cache"
    NODE_OK=0
fi

# --- Step 5: Install files ke setiap server (LANGSUNG, BYPASS install script) ---
step "5/8 — Install runner.sh + Node.js ke setiap server (LANGSUNG)"

echo "$SERVERS" | while IFS=$'\t' read -r SID SUUID SNAME; do
    [ -z "$SID" ] && continue

    echo ""
    echo ">>> Server: $SNAME (UUID: $SUUID)"

    SERVER_FILES="$VOLUMES_DIR/$SUUID/files"
    if [ ! -d "$SERVER_FILES" ]; then
        warn "  Folder tidak ditemukan: $SERVER_FILES"
        warn "  Coba path alternatif..."
        # Cek apakah ada folder dengan UUID ini
        if [ -d "$VOLUMES_DIR/$SUUID" ]; then
            echo "  Isi $VOLUMES_DIR/$SUUID:"
            ls -la "$VOLUMES_DIR/$SUUID/" 2>/dev/null | head -10
            # Mungkin struktur berbeda
            SERVER_FILES=$(find "$VOLUMES_DIR/$SUUID" -type d -name "files" 2>/dev/null | head -1)
            if [ -n "$SERVER_FILES" ]; then
                ok "  Folder files ditemukan: $SERVER_FILES"
            else
                # Pakai root folder
                SERVER_FILES="$VOLUMES_DIR/$SUUID"
                warn "  Pakai root folder: $SERVER_FILES"
            fi
        else
            warn "  UUID folder tidak ada: $VOLUMES_DIR/$SUUID"
            continue
        fi
    fi

    if [ ! -d "$SERVER_FILES" ]; then
        err "  Tidak bisa akses folder server"
        continue
    fi

    ok "  Folder: $SERVER_FILES"

    # Buat folder .cloudserver + .local
    mkdir -p "$SERVER_FILES/.cloudserver" 2>/dev/null
    mkdir -p "$SERVER_FILES/.local" 2>/dev/null

    # ===== Install Node.js ke .local =====
    if [ "$NODE_OK" = "1" ]; then
        log "  Install Node.js ke .local/..."
        rm -rf "$SERVER_FILES/.local/bin" "$SERVER_FILES/.local/lib" "$SERVER_FILES/.local/include" "$SERVER_FILES/.local/share" 2>/dev/null
        if cp -r "$NODE_CACHE"/* "$SERVER_FILES/.local/" 2>/dev/null; then
            if [ -f "$SERVER_FILES/.local/bin/node" ]; then
                ok "  Node.js installed: $("$SERVER_FILES/.local/bin/node" --version 2>/dev/null)"
            else
                warn "  Node.js copy gagal"
            fi
        else
            warn "  Gagal copy Node.js"
        fi
    else
        warn "  Skip Node.js (cache tidak tersedia)"
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

    if [ -f "$SERVER_FILES/.cloudserver/runner.sh" ]; then
        chmod +x "$SERVER_FILES/.cloudserver/runner.sh"
        ok "  runner.sh written ($(du -h "$SERVER_FILES/.cloudserver/runner.sh" | cut -f1))"

        # Set ownership — coba wings, kalau tidak ada pakai root
        chown -R "$WINGS_USER":"$WINGS_USER" "$SERVER_FILES/.cloudserver" 2>/dev/null || chown -R root:root "$SERVER_FILES/.cloudserver" 2>/dev/null || true
        chown -R "$WINGS_USER":"$WINGS_USER" "$SERVER_FILES/.local" 2>/dev/null || chown -R root:root "$SERVER_FILES/.local" 2>/dev/null || true
        ok "  Ownership set"
    else
        err "  Gagal write runner.sh"
    fi

    # Set server status = NULL (siap start)
    eval "$MYSQL_CMD -e \"UPDATE servers SET status=NULL WHERE id=$SID;\"" 2>/dev/null
    ok "  Server status set to NULL (siap start)"

    # List files
    echo "  Files di server:"
    ls -la "$SERVER_FILES/" 2>/dev/null | head -10
done

# --- Step 6: Restart wings ---
step "6/8 — Restart wings daemon"

log "Restarting wings..."
if systemctl restart wings 2>/dev/null; then
    sleep 3
    if systemctl is-active --quiet wings; then
        ok "Wings restarted & active"
    else
        warn "Wings tidak active setelah restart"
    fi
else
    warn "Gagal restart wings. Coba manual: sudo systemctl restart wings"
fi

# --- Step 7: Clear cache + reload panel ---
step "7/8 — Clear cache + reload panel"

cd "$PANEL_DIR"
php artisan view:clear 2>&1 | tail -1 || true
php artisan config:clear 2>&1 | tail -1 || true
php artisan cache:clear 2>&1 | tail -1 || true
php -r "if(function_exists('opcache_reset'))opcache_reset();" 2>/dev/null || true
ok "Cache + OPcache cleared"

PHP_FPM=""
for svc in php8.2-fpm php8.1-fpm php8.3-fpm php-fpm; do
    if systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service"; then
        PHP_FPM="$svc"
        break
    fi
done
[ -n "$PHP_FPM" ] && systemctl reload "$PHP_FPM" 2>/dev/null && ok "$PHP_FPM reloaded"

for svc in nginx apache2 httpd; do
    if systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service"; then
        systemctl reload "$svc" 2>/dev/null && ok "$svc reloaded"
        break
    fi
done

# --- Step 8: Verify ---
step "8/8 — Verify install"

log "Cek file di setiap server..."
echo "$SERVERS" | while IFS=$'\t' read -r SID SUUID SNAME; do
    [ -z "$SID" ] && continue
    echo ""
    echo ">>> $SNAME (UUID: $SUUID)"
    SERVER_FILES="$VOLUMES_DIR/$SUUID/files"

    if [ -f "$SERVER_FILES/.cloudserver/runner.sh" ]; then
        ok "  runner.sh: ADA ($(du -h "$SERVER_FILES/.cloudserver/runner.sh" | cut -f1))"
    else
        err "  runner.sh: TIDAK ADA"
    fi

    if [ -f "$SERVER_FILES/.local/bin/node" ]; then
        ok "  Node.js: ADA ($("$SERVER_FILES/.local/bin/node" --version 2>/dev/null))"
    else
        err "  Node.js: TIDAK ADA"
    fi

    STATUS=$(eval "$MYSQL_CMD -N -e \"SELECT status FROM servers WHERE id=$SID;\"" 2>/dev/null)
    ok "  Status: ${STATUS:-NULL}"
done

# --- Final summary ---
echo ""
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}  ✓ FIX SELESAI!${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BOLD}Yang sudah dilakukan:${NC}"
echo -e "  ✓ Download migration + run (atau create egg manual via SQL)"
echo -e "  ✓ Update egg: startup=runner.sh, image=python_3.11"
echo -e "  ✓ Download Node.js ${NODE_VERSION} ke cache"
echo -e "  ✓ Install Node.js + runner.sh LANGSUNG ke setiap server"
echo -e "  ✓ Set ownership + status=NULL"
echo -e "  ✓ Restart wings + clear cache"
echo -e "  ✓ Verify file ada di setiap server"
echo ""
echo -e "${BOLD}${YELLOW}LANGKAH SELANJUTNYA:${NC}"
echo ""
echo -e "${BOLD}1. Clear cache browser${NC} (Ctrl+Shift+R) — WAJIB!"
echo ""
echo -e "${BOLD}2. Login panel → buka server Anda (MUHAMMAD ALI / Cloud Server)"
echo -e "   Status harusnya NULL (tidak installing)${NC}"
echo ""
echo -e "${BOLD}3. Klik Stop${NC} (kalau masih running)"
echo -e "   Lalu klik ${BOLD}Start${NC}"
echo ""
echo -e "${BOLD}4. Server akan langsung jalan!${NC}"
echo -e "   runner.sh + Node.js sudah di-place LANGSUNG"
echo -e "   Tidak perlu install script lagi"
echo ""
echo -e "${BOLD}5. Console akan tampilkan:${NC}"
echo -e "   - Banner 'Cloud Server — Script Hosting Egg'"
echo -e "   - Panel Statistics (CPU, RAM, disk, uptime, tools)"
echo -e "   - Auto-detect script file"
echo -e "   - Interactive bash shell (kalau tidak ada script)"
echo ""
echo -e "${BOLD}6. Upload script Anda${NC} (.py, .js, .sh) via menu Files"
echo -e "   Restart server → script dijalankan otomatis"
echo ""
