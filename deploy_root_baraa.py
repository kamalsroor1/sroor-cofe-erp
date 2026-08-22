import sys
import os
import subprocess
import paramiko
from bump_version import bump_version

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HOST = "145.79.20.98"
PORT = 65002
USER = "u910151740"
PASS = "Ks@Rr12699"
PHP_BIN = "/opt/alt/php83/usr/bin/php"

# 0. Automatically bump version by 0.0.1
new_ver = bump_version()
print(f"\n========================================================")
print(f"🚀 AUTO-INCREMENTING VERSION TO: v{new_ver}")
print(f"========================================================")

# 1. Build Vite frontend assets locally
root_dir = os.path.dirname(os.path.abspath(__file__))
backend_dir = os.path.join(root_dir, "backend")

print("\n>> Building Vite frontend assets...")
subprocess.run(["npm", "run", "build"], cwd=backend_dir, shell=True, check=True)

# 2. Commit and push version bump
print("\n>> Committing and pushing release to GitHub...")
subprocess.run(["git", "add", "."], cwd=root_dir, shell=True, check=True)
subprocess.run(["git", "commit", "-m", f"chore(release): bump version to v{new_ver}"], cwd=root_dir, shell=True)
subprocess.run(["git", "push"], cwd=root_dir, shell=True, check=True)

def run_ssh(ssh, cmd):
    print(f"\n>> Command: {cmd}")
    stdin, stdout, stderr = ssh.exec_command(cmd, get_pty=True)
    while True:
        line = stdout.readline()
        if not line:
            break
        print("   " + line.rstrip())
    return stdout.channel.recv_exit_status()

print("\n>> Connecting to Hostinger via SSH...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASS, timeout=30)
print("Connected successfully!")

deploy_script = """
set -e
echo "========================================================"
echo "Deploying Cloud ERP POS to baraa-solutions.com"
echo "========================================================"

PHP_BIN="/opt/alt/php83/usr/bin/php"
BASE_DIR="/home/u910151740/domains/baraa-solutions.com"
REPO_DIR="$BASE_DIR/erp_repo"
PUBLIC_DIR="$BASE_DIR/public_html"

echo "1. Fetching/Cloning repository into $REPO_DIR..."
if [ ! -d "$REPO_DIR/.git" ]; then
    mkdir -p "$REPO_DIR"
    cd "$REPO_DIR"
    git init
    git remote add origin https://github.com/kamalsroor1/sroor-cofe-erp.git
fi

cd "$REPO_DIR"
git fetch --all --prune
git checkout feature/api-migration
git reset --hard origin/feature/api-migration

BACKEND_DIR="$REPO_DIR/backend"
cd "$BACKEND_DIR"

echo "2. Creating storage and database directories..."
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database
chmod -R 775 storage bootstrap/cache database

echo "3. Setting up isolated .env file..."
cat << 'EOF' > .env
APP_NAME="Baraa ERP"
APP_ENV=production
APP_KEY=base64:tXF3QONxB/cYe60Ot3W2alvTg8xocTqFr+K2gXfobB8=
APP_DEBUG=false
APP_URL=https://baraa-solutions.com
CENTRAL_DOMAIN=baraa-solutions.com

LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u910151740_baraa_central
DB_USERNAME=u910151740_baraa_admin
DB_PASSWORD='BaraaErp@2026#Secure'

TENANT_DB_PREFIX=u910151740_
TENANT_DB_SUFFIX=

SESSION_DRIVER=database
SESSION_LIFETIME=43200
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file
CACHE_PREFIX=baraa_erp_

EOF

echo "4. Installing Composer dependencies..."
$PHP_BIN $(which composer) install --no-dev --optimize-autoloader --no-interaction

echo "5. Running MySQL Migrations safely on u910151740_baraa_central..."
$PHP_BIN artisan migrate --force
$PHP_BIN artisan tenants:migrate --force
$PHP_BIN artisan db:seed --class=PermissionsSeeder --force
$PHP_BIN artisan db:seed --class=PlansAndFeaturesSeeder --force
$PHP_BIN artisan tenants:seed --class=TenThousandItemsSeeder --force

echo "7. Clearing and refreshing all Laravel caches..."
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan cache:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan config:clear
$PHP_BIN artisan event:clear

$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

echo "8. Publishing assets to public_html with fresh build wipe..."
cd "$PUBLIC_DIR"
if [ -f "default.php" ]; then
    mv default.php default.php.bak || true
fi

# Clean and copy built frontend assets
rm -rf "$PUBLIC_DIR/build"
mkdir -p "$PUBLIC_DIR/build"
cp -rf "$BACKEND_DIR/public/build/"* "$PUBLIC_DIR/build/"
cp -f "$BACKEND_DIR/public/logo.png" "$PUBLIC_DIR/" 2>/dev/null || true
cp -f "$BACKEND_DIR/public/favicon.ico" "$PUBLIC_DIR/" 2>/dev/null || true
cp -f "$BACKEND_DIR/public/sw.js" "$PUBLIC_DIR/" 2>/dev/null || true

# Write bridge index.php
cat << 'EOF' > "$PUBLIC_DIR/index.php"
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../erp_repo/backend/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../erp_repo/backend/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../erp_repo/backend/bootstrap/app.php')
    ->handleRequest(Request::capture());
EOF

# Write optimal .htaccess
cat << 'EOF' > "$PUBLIC_DIR/.htaccess"
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOF

chmod -R 775 "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache" "$BACKEND_DIR/database"
echo "========================================================"
echo "DEPLOYMENT TO baraa-solutions.com COMPLETED SUCCESSFULLY!"
echo "========================================================"
"""

status = run_ssh(ssh, deploy_script)
ssh.close()

if status == 0:
    print("\nAll deployment steps completed successfully (Exit code: 0)!")
else:
    print(f"\nDeployment failed with exit code: {status}")
