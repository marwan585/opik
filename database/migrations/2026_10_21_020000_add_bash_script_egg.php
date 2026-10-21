<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Cloud Server — Egg "Bash Script (Python/Node.js)"
 *
 * Egg ini memungkinkan user run Python/Node.js/Bash scripts.
 *
 * Strategi:
 * - Runtime image: ghcr.io/parkervcp/yolks:python_3.11 (Python + pip pre-installed)
 * - Install script: download Node.js 20 binary (~25MB, ~10 detik)
 *   + download runner.sh ke /mnt/server/.cloudserver/
 * - Startup: hanya 1 baris — panggil runner.sh
 *   (TIDAK inline script panjang di startup, supaya tidak ada
 *    masalah dengan {{VARIABLE}} interpolation yang bikin script
 *    rusak saat variable kosong)
 *
 * runner.sh yang di-download saat install berisi logic:
 * - Tampilkan panel stats (CPU, RAM, disk, uptime, tools)
 * - Auto-detect script file (.py/.js/.sh)
 * - Auto-install dependencies (pip/npm)
 * - Run script dengan interpreter sesuai extension
 * - Interactive shell setelah script exit
 */
return new class () extends Migration {
    public function up(): void
    {
        // 1. Buat nest "Script Hosting" jika belum ada
        $nest = DB::table('nests')->where('name', 'Script Hosting')->first();
        if (!$nest) {
            $now = now();
            DB::table('nests')->insert([
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'author' => 'cloud@server.local',
                'name' => 'Script Hosting',
                'description' => 'Run Python, Node.js, or Bash scripts as a server.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $nest = DB::table('nests')->where('name', 'Script Hosting')->first();
        }
        $nestId = $nest->id;

        // 2. Buat atau update egg
        $egg = DB::table('eggs')->where('name', 'Bash Script (Python/Node.js)')->first();
        if ($egg) {
            $this->updateEgg($egg->id, $nestId);
        } else {
            $eggId = $this->createEgg($nestId);
            $this->createEggVariables($eggId);
        }
    }

    private function createEgg(int $nestId): int
    {
        $now = now();
        $startup = $this->getStartupScript();
        $installScript = $this->getInstallScript();

        $eggId = DB::table('eggs')->insertGetId([
            'nest_id' => $nestId,
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'author' => 'cloud@server.local',
            'name' => 'Bash Script (Python/Node.js)',
            'description' => 'Run Python, Node.js, or Bash scripts. Auto-detects script type by file extension (.py, .js, .sh). Auto-installs dependencies (pip/npm). Displays panel statistics on start.',
            'features' => json_encode([]),
            'docker_images' => json_encode([
                'Python 3.11 + Node.js 20' => 'ghcr.io/parkervcp/yolks:python_3.11',
            ]),
            'file_denylist' => json_encode([]),
            'config_files' => '{}',
            'config_startup' => json_encode([
                'done' => 'Cloud Server script ready',
                'userInteraction' => [],
            ]),
            'config_logs' => '{}',
            'config_stop' => '^C',
            'config_from' => null,
            'startup' => $startup,
            'script_is_privileged' => true,
            'script_install' => $installScript,
            'script_entry' => 'bash',
            'script_container' => 'ghcr.io/parkervcp/installers:debian',
            'copy_script_from' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $eggId;
    }

    private function updateEgg(int $eggId, int $nestId): void
    {
        $startup = $this->getStartupScript();
        $installScript = $this->getInstallScript();

        DB::table('eggs')->where('id', $eggId)->update([
            'nest_id' => $nestId,
            'description' => 'Run Python, Node.js, or Bash scripts. Auto-detects script type by file extension (.py, .js, .sh). Auto-installs dependencies (pip/npm). Displays panel statistics on start.',
            'docker_images' => json_encode([
                'Python 3.11 + Node.js 20' => 'ghcr.io/parkervcp/yolks:python_3.11',
            ]),
            'startup' => $startup,
            'script_install' => $installScript,
            'script_container' => 'ghcr.io/parkervcp/installers:debian',
            'updated_at' => now(),
        ]);

        DB::table('egg_variables')->where('egg_id', $eggId)->delete();
        $this->createEggVariables($eggId);
    }

    private function createEggVariables(int $eggId): void
    {
        $now = now();
        $variables = [
            [
                'name' => 'Script File',
                'description' => 'Nama file script. Kosongkan untuk auto-detect (auto-run.sh, main.py, app.py, bot.py, index.js, server.js, app.js, script.sh, run.sh).',
                'env_variable' => 'SCRIPT_FILE',
                'default_value' => '',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'nullable|string|max:255',
            ],
            [
                'name' => 'Script Arguments',
                'description' => 'Argumen ke script. Contoh: --port 8080',
                'env_variable' => 'SCRIPT_ARGS',
                'default_value' => '',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'nullable|string|max:1000',
            ],
            [
                'name' => 'Auto-Install Dependencies',
                'description' => '1 = auto-install (pip/npm), 0 = skip.',
                'env_variable' => 'AUTO_INSTALL',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
            [
                'name' => 'Show Panel Stats',
                'description' => '1 = tampilkan statistik saat start, 0 = skip.',
                'env_variable' => 'SHOW_STATS',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
            [
                'name' => 'Interactive Shell on Exit',
                'description' => '1 = masuk bash setelah script exit, 0 = stop server.',
                'env_variable' => 'INTERACTIVE_SHELL',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
        ];

        foreach ($variables as $var) {
            DB::table('egg_variables')->insert(array_merge($var, [
                'egg_id' => $eggId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * Startup script — SIMPLE, hanya 1 baris.
     *
     * Jangan inline script panjang di startup karena:
     * 1. {{VARIABLE}} interpolation bisa rusak kalau variable kosong
     * 2. Multi-line script dengan $() dan escape characters bisa
     *    di-print mentah ke console, bukan di-execute
     * 3. Sulit debug
     *
     * Solusi: startup hanya panggil runner.sh yang sudah didownload
     * saat install script. Runner baca env variable langsung dari
     * shell, jadi tidak ada interpolation issue.
     */
    private function getStartupScript(): string
    {
        // 1-line startup — panggil runner.sh
        // Runner akan baca SCRIPT_FILE, SCRIPT_ARGS, dll dari env
        return '/mnt/server/.cloudserver/runner.sh';
    }

    private function getInstallScript(): string
    {
        // Install script:
        // 1. Download Node.js 20 binary (~25MB, ~10 detik)
        // 2. Download runner.sh (script yang dipanggil saat startup)
        // 3. Done
        return <<<'INSTALL'
#!/bin/bash
set -e

# ===== Cloud Server — Bash Script Egg Installation =====
# Image: ghcr.io/parkervcp/installers:debian
# Runtime: ghcr.io/parkervcp/yolks:python_3.11 (Python 3.11 + pip pre-installed)
#
# Install script ini FAST karena:
# - TIDAK pakai apt-get install (yang download ratusan MB dependencies)
# - Hanya download Node.js binary (satu file ~25MB)
# - Hanya download runner.sh (satu file kecil ~3KB)

mkdir -p /mnt/server/.cloudserver
mkdir -p /mnt/server/.local

# ===== 1. Install Node.js 20 via binary (FAST) =====
NODE_VERSION="v20.10.0"
NODE_DIR="/mnt/server/.local"

echo "Installing Node.js ${NODE_VERSION}..."
curl -fsSL "https://nodejs.org/dist/${NODE_VERSION}/node-${NODE_VERSION}-linux-x64.tar.xz" | tar -xJ --strip-components=1 -C "$NODE_DIR"

echo "Node.js installed:"
"$NODE_DIR/bin/node" --version
"$NODE_DIR/bin/npm" --version

# ===== 2. Download runner.sh =====
echo ""
echo "Downloading runner.sh..."
cat > /mnt/server/.cloudserver/runner.sh <<'RUNNER_EOF'
#!/bin/bash
# Cloud Server — Script Runner
# Dipanggil oleh startup command saat server start.
# Baca env variable: SCRIPT_FILE, SCRIPT_ARGS, AUTO_INSTALL,
# SHOW_STATS, INTERACTIVE_SHELL

cd /mnt/server 2>/dev/null || cd /home/container 2>/dev/null || cd ~

# Add local Node.js to PATH (installed by install script)
export PATH="/mnt/server/.local/bin:/mnt/server/.local/lib/node_modules/.bin:$PATH"

# Defaults if env not set
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

# ===== Show Panel Statistics =====
if [ "$SHOW_STATS" = "1" ]; then
    echo "===== Panel Statistics ====="
    echo "Hostname : $(hostname 2>/dev/null || echo '?')"
    echo "CPU cores: $(nproc 2>/dev/null || echo '?')"

    if [ -f /proc/loadavg ]; then
        echo "Load avg : $(cat /proc/loadavg)"
    fi

    if [ -f /proc/meminfo ]; then
        MEM_TOTAL=$(grep MemTotal /proc/meminfo | awk '{print int($2/1024)}')
        MEM_AVAIL=$(grep MemAvailable /proc/meminfo | awk '{print int($2/1024)}')
        if [ -n "$MEM_TOTAL" ] && [ -n "$MEM_AVAIL" ]; then
            echo "Memory   : $((MEM_TOTAL - MEM_AVAIL))MB / ${MEM_TOTAL}MB used"
        fi
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

# ===== Detect Script File =====
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
        echo ""
        echo "Upload your script (.py, .js, or .sh) via Files menu, then restart server."
        echo ""
        if [ "$INTERACTIVE_SHELL" = "1" ]; then
            echo "Entering interactive bash shell. Type 'exit' to stop."
            exec bash
        else
            echo "INTERACTIVE_SHELL=0, stopping server."
            sleep 2
            exit 0
        fi
    fi
fi

if [ ! -f "$SCRIPT_FILE" ]; then
    echo "Script file '$SCRIPT_FILE' not found!"
    echo "Files in current directory:"
    ls -la 2>/dev/null
    echo ""
    if [ "$INTERACTIVE_SHELL" = "1" ]; then
        echo "Entering interactive bash shell."
        exec bash
    else
        exit 1
    fi
fi

# ===== Auto-Install Dependencies =====
if [ "$AUTO_INSTALL" = "1" ]; then
    EXT="${SCRIPT_FILE##*.}"
    if [ "$EXT" = "py" ] || [ "$EXT" = "py3" ]; then
        if [ -f requirements.txt ]; then
            echo ""
            echo "===== Installing Python dependencies ====="
            pip3 install -r requirements.txt 2>&1 | tail -5
            echo "=========================================="
        fi
    fi
    if [ "$EXT" = "js" ] || [ "$EXT" = "mjs" ] || [ "$EXT" = "cjs" ]; then
        if [ -f package.json ]; then
            echo ""
            echo "===== Installing Node.js dependencies ====="
            npm install 2>&1 | tail -10
            echo "=========================================="
        fi
    fi
fi

# ===== Run Script =====
echo ""
echo "=============================================="
echo "  Running: $SCRIPT_FILE $SCRIPT_ARGS"
echo "=============================================="
echo ""

EXT="${SCRIPT_FILE##*.}"
case "$EXT" in
    py|py3)
        python3 "$SCRIPT_FILE" $SCRIPT_ARGS
        EXIT_CODE=$?
        ;;
    js|mjs|cjs)
        node "$SCRIPT_FILE" $SCRIPT_ARGS
        EXIT_CODE=$?
        ;;
    sh|bash)
        bash "$SCRIPT_FILE" $SCRIPT_ARGS
        EXIT_CODE=$?
        ;;
    *)
        chmod +x "$SCRIPT_FILE" 2>/dev/null
        ./"$SCRIPT_FILE" $SCRIPT_ARGS
        EXIT_CODE=$?
        ;;
esac

echo ""
echo "=============================================="
echo "  Script exited with code: $EXIT_CODE"
echo "=============================================="

# ===== Interactive Shell After Exit =====
if [ "$INTERACTIVE_SHELL" = "1" ]; then
    echo ""
    echo "Entering interactive bash shell."
    echo "You can run:"
    echo "  - python3 (interactive Python)"
    echo "  - node (interactive Node.js)"
    echo "  - npm install <package>"
    echo "  - pip3 install <package>"
    echo "Type 'exit' to stop server."
    echo ""
    exec bash
else
    echo "INTERACTIVE_SHELL=0, stopping server."
    sleep 3
    exit $EXIT_CODE
fi
RUNNER_EOF

chmod +x /mnt/server/.cloudserver/runner.sh

echo ""
echo "=============================================="
echo "  Installation complete!"
echo "=============================================="
echo ""
echo "Python 3.11 + pip3: pre-installed in runtime image"
echo "Node.js 20 + npm: installed to /mnt/server/.local"
echo "Runner script: /mnt/server/.cloudserver/runner.sh"
echo ""
echo "Cara pakai:"
echo "  1. Upload script (.py, .js, atau .sh) via Files menu"
echo "  2. Start server di menu Console"
echo "  3. Script akan auto-detect dan dijalankan"
echo ""

exit 0
INSTALL;
    }

    public function down(): void
    {
        $egg = DB::table('eggs')->where('name', 'Bash Script (Python/Node.js)')->first();
        if ($egg) {
            DB::table('egg_variables')->where('egg_id', $egg->id)->delete();
            DB::table('eggs')->where('id', $egg->id)->delete();
        }
        $nest = DB::table('nests')->where('name', 'Script Hosting')->first();
        if ($nest) {
            $eggCount = DB::table('eggs')->where('nest_id', $nest->id)->count();
            if ($eggCount === 0) {
                DB::table('nests')->where('id', $nest->id)->delete();
            }
        }
    }
};
