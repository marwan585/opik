<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Cloud Server — Egg "Bash Script (Python/Node.js)"
 *
 * Egg ini memungkinkan user run Python/Node.js/Bash scripts.
 *
 * Runtime image: ghcr.io/parkervcp/yolks:python_3.11
 *   - Python 3.11 + pip3 sudah pre-installed
 *   - Node.js di-install via binary download (cepat, ~10 detik)
 *
 * Install script: download Node.js 20 binary ke /mnt/server/.local
 *   - TIDAK pakai apt-get (yang download ratusan MB dependencies)
 *   - Selesai dalam ~10 detik
 *
 * Startup script: auto-detect script file + run + panel stats
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
                'description' => 'Nama file script yang ingin dijalankan. Kosongkan untuk auto-detect (urutan: auto-run.sh, main.py, app.py, bot.py, index.js, server.js, app.js, script.sh, run.sh). Contoh: mybot.py',
                'env_variable' => 'SCRIPT_FILE',
                'default_value' => '',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'nullable|string|max:255',
            ],
            [
                'name' => 'Script Arguments',
                'description' => 'Argumen yang dikirim ke script. Contoh: --port 8080 --debug',
                'env_variable' => 'SCRIPT_ARGS',
                'default_value' => '',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'nullable|string|max:1000',
            ],
            [
                'name' => 'Auto-Install Dependencies',
                'description' => 'Set 1 untuk auto-install dependencies (pip install -r requirements.txt atau npm install) sebelum run script.',
                'env_variable' => 'AUTO_INSTALL',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
            [
                'name' => 'Show Panel Stats',
                'description' => 'Set 1 untuk tampilkan statistik server panel saat start.',
                'env_variable' => 'SHOW_STATS',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
            [
                'name' => 'Interactive Shell on Exit',
                'description' => 'Set 1 untuk masuk ke interactive shell (bash) setelah script selesai.',
                'env_variable' => 'INTERACTIVE_SHELL',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
            [
                'name' => 'Python Version',
                'description' => 'Versi Python yang dipakai. Default: python3',
                'env_variable' => 'PYTHON_BIN',
                'default_value' => 'python3',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string|max:50',
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

    private function getStartupScript(): string
    {
        return <<<'STARTUP'
#!/bin/bash
cd /mnt/server || cd /home/container || cd ~

# Add local Node.js to PATH (installed by install script)
export PATH="/mnt/server/.local/bin:$PATH"

echo "=============================================="
echo "  Cloud Server — Script Hosting Egg"
echo "=============================================="
echo ""
echo "Server started at: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Working directory: $(pwd)"
echo ""

# ===== Show Panel Statistics =====
if [ "{{SHOW_STATS}}" = "1" ]; then
    echo "===== Panel Statistics ====="
    echo "Hostname : $(hostname)"
    echo "CPU cores: $(nproc 2>/dev/null || echo '?')"
    if [ -f /proc/loadavg ]; then
        echo "Load avg : $(cat /proc/loadavg)"
    fi
    if [ -f /proc/meminfo ]; then
        MEM_TOTAL=$(grep MemTotal /proc/meminfo | awk '{print int($2/1024)}')
        MEM_AVAIL=$(grep MemAvailable /proc/meminfo | awk '{print int($2/1024)}')
        echo "Memory   : $((MEM_TOTAL - MEM_AVAIL))MB / ${MEM_TOTAL}MB used"
    fi
    echo ""
    echo "Tools available:"
    command -v python3 >/dev/null 2>&1 && echo "  Python: $(python3 --version 2>&1)" || echo "  Python: NOT FOUND"
    command -v node >/dev/null 2>&1 && echo "  Node.js: $(node --version 2>&1)" || echo "  Node.js: NOT FOUND"
    command -v npm >/dev/null 2>&1 && echo "  npm: $(npm --version 2>&1)" || echo "  npm: NOT FOUND"
    command -v pip3 >/dev/null 2>&1 && echo "  pip3: available" || echo "  pip3: NOT FOUND"
    echo "=============================================="
    echo ""
fi

# ===== Detect Script File =====
SCRIPT_FILE="{{SCRIPT_FILE}}"
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
        echo "Upload your script (.py, .js, or .sh) via Files menu, then restart."
        if [ "{{INTERACTIVE_SHELL}}" = "1" ]; then
            echo ""
            echo "Entering interactive bash shell. Type 'exit' to stop."
            exec bash
        else
            exit 0
        fi
    fi
fi

if [ ! -f "$SCRIPT_FILE" ]; then
    echo "Script file '$SCRIPT_FILE' not found!"
    echo "Files in current directory:"
    ls -la
    if [ "{{INTERACTIVE_SHELL}}" = "1" ]; then
        exec bash
    else
        exit 1
    fi
fi

# ===== Auto-Install Dependencies =====
if [ "{{AUTO_INSTALL}}" = "1" ]; then
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

# ===== Run Script =====
echo ""
echo "=============================================="
echo "  Running: $SCRIPT_FILE {{SCRIPT_ARGS}}"
echo "=============================================="
echo ""

EXT="${SCRIPT_FILE##*.}"
case "$EXT" in
    py|py3) {{PYTHON_BIN}} "$SCRIPT_FILE" {{SCRIPT_ARGS}} ;;
    js|mjs|cjs) node "$SCRIPT_FILE" {{SCRIPT_ARGS}} ;;
    sh|bash) bash "$SCRIPT_FILE" {{SCRIPT_ARGS}} ;;
    *) chmod +x "$SCRIPT_FILE" 2>/dev/null; ./"$SCRIPT_FILE" {{SCRIPT_ARGS}} ;;
esac

EXIT_CODE=$?
echo ""
echo "Script exited with code: $EXIT_CODE"

# ===== Interactive Shell After Exit =====
if [ "{{INTERACTIVE_SHELL}}" = "1" ]; then
    echo ""
    echo "Entering interactive bash shell. Type 'exit' to stop."
    exec bash
else
    sleep 3
    exit $EXIT_CODE
fi
STARTUP;
    }

    private function getInstallScript(): string
    {
        return <<<'INSTALL'
#!/bin/bash
set -e

# Cloud Server — Bash Script Egg Installation
# Runtime image: ghcr.io/parkervcp/yolks:python_3.11 (Python + pip pre-installed)
#
# Install script ini FAST (~10 detik) karena:
# 1. TIDAK pakai apt-get install (yang download ratusan MB dependencies)
# 2. Hanya download Node.js binary ( satu file ~25MB) ke /mnt/server/.local
# 3. Python + pip sudah pre-installed di runtime image

mkdir -p /mnt/server/.local

# ===== Install Node.js 20 via binary (FAST, no apt-get) =====
NODE_VERSION="v20.10.0"
NODE_DIR="/mnt/server/.local"

echo "Installing Node.js ${NODE_VERSION}..."
curl -fsSL "https://nodejs.org/dist/${NODE_VERSION}/node-${NODE_VERSION}-linux-x64.tar.xz" | tar -xJ --strip-components=1 -C "$NODE_DIR"

# Verify
echo ""
echo "Node.js installed:"
"$NODE_DIR/bin/node" --version
"$NODE_DIR/bin/npm" --version

echo ""
echo "=============================================="
echo "  Installation complete!"
echo "=============================================="
echo ""
echo "Python 3 + pip3: pre-installed in runtime image"
echo "Node.js 20 + npm: installed to /mnt/server/.local"
echo ""
echo "Upload your script (.py, .js, or .sh) via Files menu."
echo "Start server — script will auto-detect and run."
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
