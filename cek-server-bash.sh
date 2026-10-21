#!/bin/bash
# Cloud Server — Cek status server Bash Script
# Cara pakai: sudo bash cek-server-bash.sh

PANEL_DIR="${PANEL_DIR:-/var/www/pterodactyl}"

[ "$(id -u)" -ne 0 ] && { echo "Run as root: sudo $0"; exit 1; }

# Read DB credentials
DB_DATABASE=$(grep -E "^DB_DATABASE=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep -E "^DB_USERNAME=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep -E "^DB_PASSWORD=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_HOST=$(grep -E "^DB_HOST=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "127.0.0.1")
DB_PORT=$(grep -E "^DB_PORT=" "$PANEL_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "3306")

if [ -n "$DB_PASSWORD" ]; then
    MYSQL_CMD="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p'${DB_PASSWORD}' ${DB_DATABASE}"
else
    MYSQL_CMD="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} ${DB_DATABASE}"
fi

echo "=============================================="
echo "  Cek Server Bash Script"
echo "=============================================="
echo ""

# Cari semua server yang pakai egg Bash Script
echo "=== Server yang pakai egg Bash Script ==="
eval "$MYSQL_CMD -e \"
SELECT s.id, s.uuid, s.name, s.status, s.node_id
FROM servers s
JOIN eggs e ON s.egg_id = e.id
WHERE e.name = 'Bash Script (Python/Node.js)';
\"" 2>/dev/null

echo ""
echo "=== Semua server (kalau egg belum ter-assign) ==="
eval "$MYSQL_CMD -e \"
SELECT id, uuid, name, status FROM servers ORDER BY id DESC LIMIT 10;
\"" 2>/dev/null

echo ""
echo "=============================================="
echo "  Cek file runner.sh untuk setiap server"
echo "=============================================="

# Loop through servers and check files
SERVERS=$(eval "$MYSQL_CMD -N -e \"
SELECT uuid, name FROM servers
WHERE egg_id IN (SELECT id FROM eggs WHERE name = 'Bash Script (Python/Node.js)')
OR name LIKE '%bash%' OR name LIKE '%script%';
\"" 2>/dev/null)

if [ -z "$SERVERS" ]; then
    echo "Tidak ada server ditemukan."
    echo ""
    echo "Cek manual semua server:"
    eval "$MYSQL_CMD -e \"SELECT id, uuid, name FROM servers;\"" 2>/dev/null
    exit 0
fi

echo "$SERVERS" | while IFS=$'\t' read -r uuid name; do
    if [ -z "$uuid" ]; then continue; fi
    echo ""
    echo "--- Server: $name (UUID: $uuid) ---"
    SERVER_PATH="/var/lib/pterodactyl/volumes/${uuid}/files"

    if [ ! -d "$SERVER_PATH" ]; then
        echo "  ✗ Folder server tidak ditemukan: $SERVER_PATH"
        echo "    Mungkin wings pakai path lain. Cek:"
        echo "    ls /var/lib/pterodactyl/volumes/"
        continue
    fi

    echo "  Folder: $SERVER_PATH"

    # Cek runner.sh
    if [ -f "$SERVER_PATH/.cloudserver/runner.sh" ]; then
        echo "  ✓ runner.sh ADA"
        echo "    Size: $(du -h "$SERVER_PATH/.cloudserver/runner.sh" | cut -f1)"
        echo "    Executable: $([ -x "$SERVER_PATH/.cloudserver/runner.sh" ] && echo 'YES' || echo 'NO')"
    else
        echo "  ✗ runner.sh TIDAK ADA — install script belum jalan atau gagal"
    fi

    # Cek Node.js
    if [ -f "$SERVER_PATH/.local/bin/node" ]; then
        echo "  ✓ Node.js ADA: $(su -s /bin/bash -c "$SERVER_PATH/.local/bin/node --version" www-data 2>/dev/null || echo '?')"
    else
        echo "  ✗ Node.js TIDAK ADA — install script belum download"
    fi

    # Cek script file user
    echo "  Files di /home/container:"
    ls -la "$SERVER_PATH/" 2>/dev/null | head -10
done

echo ""
echo "=============================================="
echo "  Status wings daemon"
echo "=============================================="
systemctl status wings --no-pager 2>/dev/null | head -10

echo ""
echo "=============================================="
echo "  Log wings (20 baris terakhir)"
echo "=============================================="
journalctl -u wings -n 20 --no-pager 2>/dev/null | tail -20

echo ""
echo "=============================================="
echo "  Tips"
echo "=============================================="
echo "Kalau runner.sh TIDAK ADA:"
echo "  1. Jalankan fix: curl -L https://raw.githubusercontent.com/marwan585/opik/main/fix-egg-bash.sh | sudo bash"
echo "  2. Restart wings: sudo systemctl restart wings"
echo "  3. Login panel → Stop server → Start lagi"
echo ""
echo "Kalau runner.sh ADA tapi server masih stop:"
echo "  1. Cek log wings di atas"
echo "  2. Cek permission: sudo chown -R wings:wings /var/lib/pterodactyl/volumes/"
echo "  3. Restart wings: sudo systemctl restart wings"
