<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Services\Nests\NestCreationService;

/**
 * Cloud Server — Tambah egg "Bash Script" yang auto-detect Python/Node.js
 *
 * Egg ini memungkinkan user:
 *  1. Upload script project (file .py atau .js) ke menu Files
 *  2. Start server di menu Console
 *  3. Saat start, otomatis:
 *     - Tampilkan statistik server panel (CPU, RAM, uptime)
 *     - Deteksi bahasa script berdasarkan extensi file
 *     - Jika .py → jalankan dengan python3 (auto pip install)
 *     - Jika .js → jalankan dengan node (auto npm install)
 *     - Jika .sh → jalankan dengan bash
 *  4. User bisa ketik command langsung di console terminal
 *     untuk run command nodejs/python interactively
 *
 * Cara pakai (admin):
 *  - Setelah migration, nest "Script Hosting" akan muncul di panel admin
 *  - Egg "Bash Script (Python/Node.js)" tersedia di nest tersebut
 *  - Saat create server baru, pilih egg ini
 *  - Upload script ke folder /home/container via Files menu
 *  - Start server → auto-detect + run + tampilkan statistik
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
                'description' => 'Run Python, Node.js, or Bash scripts as a server. Auto-detects script type and runs it. Includes panel statistics display on start.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $nest = DB::table('nests')->where('name', 'Script Hosting')->first();
        }
        $nestId = $nest->id;

        // 2. Buat egg "Bash Script (Python/Node.js)" jika belum ada
        $egg = DB::table('eggs')->where('name', 'Bash Script (Python/Node.js)')->first();
        if ($egg) {
            // Update existing egg
            $this->updateEgg($egg->id, $nestId);
        } else {
            // Create new egg
            $eggId = $this->createEgg($nestId);
            $this->createEggVariables($eggId);
        }
    }

    private function createEgg(int $nestId): int
    {
        $now = now();

        // Startup script: auto-detect script type, show stats, run script
        $startup = $this->getStartupScript();
        $installScript = $this->getInstallScript();

        // Note: kolom 'tags' dan 'update_url' TIDAK ada di tabel eggs
        // versi Pterodactyl ini. Jangan masukkan ke insert.
        // Kolom 'author' juga TIDAK mass-assignable lewat DB::table,
        // tapi ada di tabel — kita insert manual.
        //
        // Format docker_images: { "Label": "image:tag" }
        // KEY = label (yang tampil di dropdown), VALUE = image URL.
        // Jangan dibalik!
        $eggId = DB::table('eggs')->insertGetId([
            'nest_id' => $nestId,
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'author' => 'cloud@server.local',
            'name' => 'Bash Script (Python/Node.js)',
            'description' => 'Run Python, Node.js, or Bash scripts. Auto-detects script type by file extension (.py, .js, .sh). Auto-installs dependencies (pip/npm). Displays panel statistics on start. Drop into interactive shell if no script found.',
            'features' => json_encode([]),
            'docker_images' => json_encode([
                'Debian (Python 3 + Node.js 20 + Bash)' => 'ghcr.io/parkervcp/yolks:debian',
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
            'description' => 'Run Python, Node.js, or Bash scripts. Auto-detects script type by file extension (.py, .js, .sh). Auto-installs dependencies (pip/npm). Displays panel statistics on start. Drop into interactive shell if no script found.',
            'docker_images' => json_encode([
                'Debian (Python 3 + Node.js 20 + Bash)' => 'ghcr.io/parkervcp/yolks:debian',
            ]),
            'startup' => $startup,
            'script_install' => $installScript,
            'script_container' => 'ghcr.io/parkervcp/installers:debian',
            'updated_at' => now(),
        ]);

        // Re-create variables (delete old + insert new)
        DB::table('egg_variables')->where('egg_id', $eggId)->delete();
        $this->createEggVariables($eggId);
    }

    private function createEggVariables(int $eggId): void
    {
        $now = now();
        $variables = [
            [
                'name' => 'Script File',
                'description' => 'Nama file script yang ingin dijalankan. Kosongkan untuk auto-detect (urutan: auto-run.sh, main.py, app.py, index.js, server.js, script.sh). Contoh: mybot.py',
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
                'description' => 'Set 1 untuk auto-install dependencies (pip install -r requirements.txt atau npm install) sebelum run script. Set 0 untuk skip.',
                'env_variable' => 'AUTO_INSTALL',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
            [
                'name' => 'Show Panel Stats',
                'description' => 'Set 1 untuk tampilkan statistik server panel (CPU, RAM, uptime, disk) saat start. Set 0 untuk skip.',
                'env_variable' => 'SHOW_STATS',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
            [
                'name' => 'Interactive Shell on Exit',
                'description' => 'Set 1 untuk masuk ke interactive shell (bash) setelah script selesai atau gagal. Set 0 untuk stop server setelah script exit.',
                'env_variable' => 'INTERACTIVE_SHELL',
                'default_value' => '1',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|boolean',
            ],
            [
                'name' => 'Python Version',
                'description' => 'Versi Python yang dipakai (python3 default). Contoh: python3, python3.11, python3.10',
                'env_variable' => 'PYTHON_BIN',
                'default_value' => 'python3',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string|max:50',
            ],
            [
                'name' => 'Node.js Version',
                'description' => 'Versi Node.js yang dipakai (node default). Contoh: node, node20, node18',
                'env_variable' => 'NODE_BIN',
                'default_value' => 'node',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string|max:50',
            ],
            [
                'name' => 'Stats Refresh Interval',
                'description' => 'Interval refresh statistik (detik). Default 5. Set 0 untuk tampilkan sekali saja.',
                'env_variable' => 'STATS_INTERVAL',
                'default_value' => '5',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|numeric|min:0|max:3600',
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
        // Multi-line bash startup script
        // Using {{SCRIPT_FILE}}, {{SCRIPT_ARGS}}, {{AUTO_INSTALL}}, etc — Pterodactyl env variable interpolation
        return <<<'STARTUP'
#!/bin/bash
cd /mnt/server || cd /home/container || cd ~

# ===== Cloud Server — Bash Script Runner =====
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
    
    # Load average
    if [ -f /proc/loadavg ]; then
        LOAD=$(cat /proc/loadavg)
        echo "Load avg : $LOAD"
    fi
    
    # Memory
    if [ -f /proc/meminfo ]; then
        MEM_TOTAL=$(grep MemTotal /proc/meminfo | awk '{print int($2/1024)}')
        MEM_AVAIL=$(grep MemAvailable /proc/meminfo | awk '{print int($2/1024)}')
        MEM_USED=$((MEM_TOTAL - MEM_AVAIL))
        echo "Memory   : ${MEM_USED}MB / ${MEM_TOTAL}MB used"
    fi
    
    # Disk
    DISK_USAGE=$(df -h . 2>/dev/null | tail -1 | awk '{print $3 " / " $2 " (" $5 ")"}')
    echo "Disk     : $DISK_USAGE"
    
    # Uptime
    if [ -f /proc/uptime ]; then
        UP_SEC=$(awk '{print int($1)}' /proc/uptime)
        UP_H=$((UP_SEC / 3600))
        UP_M=$(((UP_SEC % 3600) / 60))
        UP_S=$((UP_SEC % 60))
        echo "Uptime   : ${UP_H}h ${UP_M}m ${UP_S}s"
    fi
    
    # Tools available
    echo ""
    echo "Tools available:"
    command -v python3 >/dev/null 2>&1 && echo "  ✓ Python: $(python3 --version 2>&1)"
    command -v node >/dev/null 2>&1 && echo "  ✓ Node.js: $(node --version 2>&1)"
    command -v npm >/dev/null 2>&1 && echo "  ✓ npm: $(npm --version 2>&1)"
    command -v pip3 >/dev/null 2>&1 && echo "  ✓ pip3: $(pip3 --version 2>&1 | head -1)"
    command -v git >/dev/null 2>&1 && echo "  ✓ git: $(git --version 2>&1)"
    command -v curl >/dev/null 2>&1 && echo "  ✓ curl: $(curl --version 2>&1 | head -1)"
    echo "=============================================="
    echo ""
fi

# ===== Detect Script File =====
SCRIPT_FILE="{{SCRIPT_FILE}}"
if [ -z "$SCRIPT_FILE" ]; then
    # Auto-detect script file
    echo "Auto-detecting script file..."
    for candidate in auto-run.sh main.py app.py bot.py index.js server.js app.js script.sh run.sh; do
        if [ -f "$candidate" ]; then
            SCRIPT_FILE="$candidate"
            echo "  ✓ Found: $SCRIPT_FILE"
            break
        fi
    done
    if [ -z "$SCRIPT_FILE" ]; then
        echo "  ✗ No script file found (looked for: auto-run.sh, main.py, app.py, bot.py, index.js, server.js, app.js, script.sh, run.sh)"
        echo ""
        echo "Upload your script to /home/container via Files menu, then restart server."
        echo ""
        if [ "{{INTERACTIVE_SHELL}}" = "1" ]; then
            echo "Entering interactive bash shell. Type 'exit' to stop server."
            echo "You can run python3, node, npm, pip3 commands directly."
            echo ""
            exec bash
        else
            echo "INTERACTIVE_SHELL=0, stopping server."
            exit 0
        fi
    fi
fi

if [ ! -f "$SCRIPT_FILE" ]; then
    echo "✗ Script file '$SCRIPT_FILE' not found!"
    echo "Available files in current directory:"
    ls -la
    if [ "{{INTERACTIVE_SHELL}}" = "1" ]; then
        echo ""
        echo "Entering interactive bash shell."
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
            echo "=========================================="
        fi
        if [ -f Pipfile ]; then
            echo ""
            echo "===== Installing Pipenv dependencies ====="
            command -v pipenv >/dev/null 2>&1 && pipenv install || pip3 install pipenv && pipenv install
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
echo "  Running: $SCRIPT_FILE {{SCRIPT_ARGS}}"
echo "=============================================="
echo ""

EXT="${SCRIPT_FILE##*.}"

case "$EXT" in
    py|py3)
        {{PYTHON_BIN}} "$SCRIPT_FILE" {{SCRIPT_ARGS}}
        EXIT_CODE=$?
        ;;
    js|mjs|cjs)
        {{NODE_BIN}} "$SCRIPT_FILE" {{SCRIPT_ARGS}}
        EXIT_CODE=$?
        ;;
    sh|bash)
        bash "$SCRIPT_FILE" {{SCRIPT_ARGS}}
        EXIT_CODE=$?
        ;;
    *)
        echo "Unknown script extension: $EXT"
        echo "Trying to execute directly..."
        chmod +x "$SCRIPT_FILE" 2>/dev/null
        ./"$SCRIPT_FILE" {{SCRIPT_ARGS}}
        EXIT_CODE=$?
        ;;
esac

echo ""
echo "=============================================="
echo "  Script exited with code: $EXIT_CODE"
echo "=============================================="

# ===== Interactive Shell After Exit =====
if [ "{{INTERACTIVE_SHELL}}" = "1" ]; then
    echo ""
    echo "Entering interactive bash shell."
    echo "You can run:"
    echo "  - python3 (interactive Python)"
    echo "  - node (interactive Node.js)"
    echo "  - npm install <package>"
    echo "  - pip3 install <package>"
    echo "  - ls, cd, cat, etc (bash commands)"
    echo "Type 'exit' to stop server."
    echo ""
    exec bash
else
    echo "INTERACTIVE_SHELL=0, stopping server."
    sleep 3
    exit $EXIT_CODE
fi
STARTUP;
    }

    private function getInstallScript(): string
    {
        return <<<'INSTALL'
#!/bin/bash
# Cloud Server — Bash Script Egg Installation
# Image: ghcr.io/parkervcp/installers:debian

apt-get update -y
apt-get install -y --no-install-recommends curl ca-certificates python3 python3-pip nodejs npm git bash

# Verify installations
echo "Python version:"
python3 --version 2>&1 || echo "WARNING: python3 not installed"

echo "Node.js version:"
node --version 2>&1 || echo "WARNING: node not installed"

echo "npm version:"
npm --version 2>&1 || echo "WARNING: npm not installed"

echo "pip3 version:"
pip3 --version 2>&1 || echo "WARNING: pip3 not installed"

# Clean apt cache
apt-get clean
rm -rf /var/lib/apt/lists/*

echo ""
echo "=============================================="
echo "  Installation complete!"
echo "=============================================="
echo ""
echo "Python, Node.js, dan tools sudah terinstall."
echo "Upload script Anda (.py, .js, atau .sh) ke"
echo "/home/container via Files menu di panel."
echo ""
echo "Saat server start, script akan auto-detect dan"
echo "dijalankan. Dependencies (requirements.txt atau"
echo "package.json) akan auto-install."
echo ""
INSTALL;
    }

    public function down(): void
    {
        // Find egg and delete
        $egg = DB::table('eggs')->where('name', 'Bash Script (Python/Node.js)')->first();
        if ($egg) {
            DB::table('egg_variables')->where('egg_id', $egg->id)->delete();
            DB::table('eggs')->where('id', $egg->id)->delete();
        }
        // Find nest and delete (only if no eggs remain)
        $nest = DB::table('nests')->where('name', 'Script Hosting')->first();
        if ($nest) {
            $eggCount = DB::table('eggs')->where('nest_id', $nest->id)->count();
            if ($eggCount === 0) {
                DB::table('nests')->where('id', $nest->id)->delete();
            }
        }
    }
};
