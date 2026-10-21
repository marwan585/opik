#!/bin/bash
# ============================================================================
# Cloud Server — Fix Bash Script Egg (SIMPLE VERSION, no migration)
# ============================================================================
# Cara pakai:
#   curl -L -o /tmp/fix-bash.sh https://raw.githubusercontent.com/marwan585/opik/main/fix-all-bash.sh
#   sudo bash /tmp/fix-bash.sh
#
# Script ini TIDAK pakai php artisan migrate (yang bisa hang/error).
# Langsung SQL + install files. Lebih reliable.
# ============================================================================

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[0;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

PANEL_DIR="/var/www/pterodactyl"
VOLUMES_DIR="/var/lib/pterodactyl/volumes"
NODE_VERSION="v20.10.0"

log()  { echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $*"; }
ok()   { echo -e "${GREEN}✓${NC} $*"; }
warn() { echo -e "${YELLOW}⚠${NC} $*"; }
err()  { echo -e "${RED}✗${NC} $*"; }
step() { echo -e "\n${BOLD}${CYAN}━━━ $* ━━━${NC}"; }

# ===== Pre-flight =====
step "Pre-flight"
[ "$(id -u)" -ne 0 ] && { err "Run as root"; exit 1; }
[ ! -f "$PANEL_DIR/.env" ] && { err ".env tidak ditemukan"; exit 1; }
ok "OK"

# ===== Read DB credentials =====
step "1/6 — Baca DB"
DB_DATABASE=$(grep "^DB_DATABASE=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep "^DB_USERNAME=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep "^DB_PASSWORD=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_HOST=$(grep "^DB_HOST=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_PORT=$(grep "^DB_PORT=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")

[ -n "$DB_PASSWORD" ] && MYSQL="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p'${DB_PASSWORD}' ${DB_DATABASE}" || MYSQL="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} ${DB_DATABASE}"

eval "$MYSQL -e 'SELECT 1'" &>/dev/null || { err "Gagal koneksi DB"; exit 1; }
ok "DB: $DB_DATABASE"

# ===== Step 2: Create/update egg via SQL (NO migration) =====
step "2/6 — Create/update egg via SQL"

# Cari/buat nest
NEST_ID=$(eval "$MYSQL -N -e \"SELECT id FROM nests WHERE name='Script Hosting' LIMIT 1;\"" 2>/dev/null)
if [ -z "$NEST_ID" ]; then
    eval "$MYSQL -e \"INSERT INTO nests (uuid, author, name, description, created_at, updated_at) VALUES (UUID(), 'cloud@server.local', 'Script Hosting', 'Run scripts', NOW(), NOW());\"" 2>/dev/null
    NEST_ID=$(eval "$MYSQL -N -e \"SELECT id FROM nests WHERE name='Script Hosting' LIMIT 1;\"" 2>/dev/null)
fi
ok "Nest: $NEST_ID"

# Cari/create egg
EGG_ID=$(eval "$MYSQL -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null)

STARTUP_CMD="/mnt/server/.cloudserver/runner.sh"
DOCKER_IMAGES='{"Python 3.11 + Node.js 20":"ghcr.io/parkervcp/yolks:python_3.11"}'
INSTALL_SCRIPT='#!/bin/bash\nmkdir -p /mnt/server/.cloudserver\nexit 0'
CONFIG_STARTUP='{"done":"Cloud Server script ready","userInteraction":[]}'

if [ -z "$EGG_ID" ]; then
    eval "$MYSQL -e \"INSERT INTO eggs (nest_id, uuid, author, name, description, features, docker_images, file_denylist, config_files, config_startup, config_logs, config_stop, config_from, startup, script_is_privileged, script_install, script_entry, script_container, copy_script_from, created_at, updated_at) VALUES ($NEST_ID, UUID(), 'cloud@server.local', 'Bash Script (Python/Node.js)', 'Run Python/Node.js/Bash scripts', '[]', '$DOCKER_IMAGES', '[]', '{}', '$CONFIG_STARTUP', '{}', '^C', NULL, '$STARTUP_CMD', 1, '$INSTALL_SCRIPT', 'bash', 'ghcr.io/parkervcp/installers:debian', NULL, NOW(), NOW());\"" 2>/dev/null
    EGG_ID=$(eval "$MYSQL -N -e \"SELECT id FROM eggs WHERE name='Bash Script (Python/Node.js)' LIMIT 1;\"" 2>/dev/null)
    ok "Egg dibuat: $EGG_ID"
else
    # Update existing egg
    eval "$MYSQL -e \"UPDATE eggs SET nest_id=$NEST_ID, docker_images='$DOCKER_IMAGES', startup='$STARTUP_CMD', script_install='$INSTALL_SCRIPT', config_startup='$CONFIG_STARTUP', updated_at=NOW() WHERE id=$EGG_ID;\"" 2>/dev/null
    ok "Egg diupdate: $EGG_ID"
fi

echo "  startup: $STARTUP_CMD"
echo "  docker_images: $DOCKER_IMAGES"

# ===== Step 3: Get servers + download Node.js =====
step "3/6 — Cari server + download Node.js"

SERVERS=$(eval "$MYSQL -N -e \"SELECT id, uuid, name FROM servers WHERE egg_id=$EGG_ID;\"" 2>/dev/null)
if [ -z "$SERVERS" ]; then
    SERVERS=$(eval "$MYSQL -N -e \"SELECT id, uuid, name FROM servers;\"" 2>/dev/null)
fi

[ -z "$SERVERS" ] && { err "Tidak ada server"; exit 1; }

ok "Server ditemukan:"
echo "$SERVERS" | while IFS=$'\t' read -r sid suuid sname; do
    [ -n "$sid" ] && echo "  • ID=$sid name='$sname' uuid=$suuid"
    # Assign egg ke server
    eval "$MYSQL -e \"UPDATE servers SET egg_id=$EGG_ID WHERE id=$sid;\"" 2>/dev/null
done

# Download Node.js ke cache
NODE_CACHE="/tmp/node-${NODE_VERSION}"
if [ ! -f "$NODE_CACHE/bin/node" ]; then
    log "Download Node.js ${NODE_VERSION}..."
    rm -rf "$NODE_CACHE"
    mkdir -p "$NODE_CACHE"
    curl -fsSL "https://nodejs.org/dist/${NODE_VERSION}/node-${NODE_VERSION}-linux-x64.tar.xz" | tar -xJ --strip-components=1 -C "$NODE_CACHE"
fi
ok "Node.js: $($NODE_CACHE/bin/node --version)"

# ===== Step 4: Install files ke setiap server =====
step "4/6 — Install runner.sh + Node.js ke server"

echo "$SERVERS" | while IFS=$'\t' read -r SID SUUID SNAME; do
    [ -z "$SID" ] && continue
    echo ""
    echo ">>> $SNAME ($SUUID)"

    SF="$VOLUMES_DIR/$SUUID/files"
    [ ! -d "$SF" ] && SF="$VOLUMES_DIR/$SUUID"
    [ ! -d "$SF" ] && { warn "  Folder tidak ada: $VOLUMES_DIR/$SUUID"; continue; }

    mkdir -p "$SF/.cloudserver" "$SF/.local"

    # Copy Node.js
    log "  Install Node.js..."
    rm -rf "$SF/.local/bin" "$SF/.local/lib" "$SF/.local/include" "$SF/.local/share" 2>/dev/null
    cp -r "$NODE_CACHE"/* "$SF/.local/" 2>/dev/null
    [ -f "$SF/.local/bin/node" ] && ok "  Node.js: $($SF/.local/bin/node --version 2>/dev/null)" || warn "  Node.js gagal"

    # Write runner.sh
    log "  Write runner.sh..."
    cat > "$SF/.cloudserver/runner.sh" <<'RUNNER_EOF'
#!/bin/bash
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
        MT=$(grep MemTotal /proc/meminfo | awk '{print int($2/1024)}')
        MA=$(grep MemAvailable /proc/meminfo | awk '{print int($2/1024)}')
        [ -n "$MT" ] && [ -n "$MA" ] && echo "Memory   : $((MT-MA))MB / ${MT}MB"
    fi
    DU=$(df -h . 2>/dev/null | tail -1 | awk '{print $3" / "$2" ("$5")"}')
    [ -n "$DU" ] && echo "Disk     : $DU"
    echo ""
    echo "Tools:"
    command -v python3 >/dev/null 2>&1 && echo "  Python : $(python3 --version 2>&1)" || echo "  Python : NOT FOUND"
    command -v node >/dev/null 2>&1 && echo "  Node.js: $(node --version 2>&1)" || echo "  Node.js: NOT FOUND"
    command -v npm >/dev/null 2>&1 && echo "  npm    : $(npm --version 2>&1)" || echo "  npm    : NOT FOUND"
    command -v pip3 >/dev/null 2>&1 && echo "  pip3   : available" || echo "  pip3   : NOT FOUND"
    echo "=============================================="
    echo ""
fi

if [ -z "$SCRIPT_FILE" ]; then
    echo "Auto-detecting script..."
    for c in auto-run.sh main.py app.py bot.py index.js server.js app.js script.sh run.sh; do
        [ -f "$c" ] && SCRIPT_FILE="$c" && echo "  Found: $c" && break
    done
    if [ -z "$SCRIPT_FILE" ]; then
        echo "  No script found. Upload via Files menu."
        if [ "$INTERACTIVE_SHELL" = "1" ]; then
            echo ""
            echo "Entering bash shell. Type 'exit' to stop."
            exec bash
        else
            exit 0
        fi
    fi
fi

[ ! -f "$SCRIPT_FILE" ] && { echo "Script '$SCRIPT_FILE' not found!"; ls -la; [ "$INTERACTIVE_SHELL" = "1" ] && exec bash; exit 1; }

if [ "$AUTO_INSTALL" = "1" ]; then
    EXT="${SCRIPT_FILE##*.}"
    [ "$EXT" = "py" ] && [ -f requirements.txt ] && { echo ""; echo "Installing Python deps..."; pip3 install -r requirements.txt 2>&1 | tail -5; }
    [ "$EXT" = "js" ] && [ -f package.json ] && { echo ""; echo "Installing Node deps..."; npm install 2>&1 | tail -10; }
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
EC=$?
echo ""
echo "Script exited: $EC"

if [ "$INTERACTIVE_SHELL" = "1" ]; then
    echo ""
    echo "Entering bash shell. Type 'exit' to stop."
    exec bash
else
    sleep 3
    exit $EC
fi
RUNNER_EOF

    chmod +x "$SF/.cloudserver/runner.sh"
    chown -R root:root "$SF/.cloudserver" "$SF/.local" 2>/dev/null
    [ -f "$SF/.cloudserver/runner.sh" ] && ok "  runner.sh: $(du -h "$SF/.cloudserver/runner.sh" | cut -f1)" || warn "  runner.sh gagal"

    # Set status NULL
    eval "$MYSQL -e \"UPDATE servers SET status=NULL WHERE id=$SID;\"" 2>/dev/null
    ok "  Status: NULL (siap start)"

    echo "  Files:"
    ls -la "$SF/" 2>/dev/null | head -8
done

# ===== Step 5: Restart wings + clear cache =====
step "5/6 — Restart wings + clear cache"

systemctl restart wings 2>/dev/null && ok "Wings restarted" || warn "Wings restart gagal"
sleep 2

cd "$PANEL_DIR"
php artisan view:clear 2>/dev/null && ok "view:clear" || true
php artisan config:clear 2>/dev/null && ok "config:clear" || true
php artisan cache:clear 2>/dev/null && ok "cache:clear" || true
php -r "if(function_exists('opcache_reset'))opcache_reset();" 2>/dev/null || true

# Reload PHP-FPM
for svc in php8.2-fpm php8.1-fpm php8.3-fpm php-fpm; do
    systemctl reload "$svc" 2>/dev/null && ok "$svc reloaded" && break
done
# Reload web server
for svc in nginx apache2 httpd; do
    systemctl reload "$svc" 2>/dev/null && ok "$svc reloaded" && break
done

# ===== Step 6: Verify =====
step "6/6 — Verify"

echo "$SERVERS" | while IFS=$'\t' read -r SID SUUID SNAME; do
    [ -z "$SID" ] && continue
    SF="$VOLUMES_DIR/$SUUID/files"
    [ ! -d "$SF" ] && SF="$VOLUMES_DIR/$SUUID"
    echo ""
    echo ">>> $SNAME"
    [ -f "$SF/.cloudserver/runner.sh" ] && ok "  runner.sh: ADA" || err "  runner.sh: TIDAK ADA"
    [ -f "$SF/.local/bin/node" ] && ok "  Node.js: $($SF/.local/bin/node --version 2>/dev/null)" || err "  Node.js: TIDAK ADA"
    ST=$(eval "$MYSQL -N -e \"SELECT status FROM servers WHERE id=$SID;\"" 2>/dev/null)
    ok "  Status: ${ST:-NULL}"
done

# ===== Summary =====
echo ""
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}  ✓ FIX SELESAI!${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Langkah selanjutnya:"
echo "  1. Clear cache browser (Ctrl+Shift+R)"
echo "  2. Login panel → buka server Anda"
echo "  3. Klik Stop (kalau masih running)"
echo "  4. Klik Start → server LANGSUNG JALAN"
echo "  5. Upload script (.py/.js/.sh) via Files"
echo "  6. Restart server → script auto-run"
echo ""
