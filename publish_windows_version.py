import paramiko

HOST = "145.79.20.98"
PORT = 65002
USER = "u910151740"
PASS = "Ks@Rr12699"
PHP_BIN = "/opt/alt/php83/usr/bin/php"
REPO_DIR = "/home/u910151740/domains/baraa-solutions.com/erp_repo/backend"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASS, timeout=30)

php_script = """<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Delete 2.0.0 if existed
\\App\\Models\\AppVersion::where('version_name', '2.0.0')->delete();

// Set 1.1.0 for Windows and Android
\\App\\Models\\AppVersion::updateOrCreate(
    ['platform' => 'windows', 'version_name' => '1.1.0'],
    [
        'version_code' => 110,
        'min_version_code' => 1,
        'is_active' => true,
        'is_force_update' => false,
        'release_notes_ar' => "• إضافة نظام مصاريف الشحن والخدمات الإضافية في الـ POS\\n• تقسيم السداد على أكثر من وسيلة (كاش، إنستاباي، فيزا)\\n• عرض سعري القطاعي والجملة في سلة المبيعات مع التبديل السريع\\n• تحسينات عامة في سرعة واستقرار النظام",
        'release_notes_en' => "• Added POS shipping and extra expenses modal\\n• Added split multi-payment support\\n• Added dual retail and wholesale cart pricing\\n• General stability improvements",
        'apk_filename' => 'Sroor-ERP-POS-Setup.exe',
        'apk_path' => 'Sroor-ERP-POS-Setup.exe',
        'apk_size_bytes' => 81966153,
        'download_count' => 0,
        'published_at' => now(),
    ]
);

\\App\\Models\\AppVersion::updateOrCreate(
    ['platform' => 'android', 'version_name' => '1.1.0'],
    [
        'version_code' => 110,
        'min_version_code' => 1,
        'is_active' => true,
        'is_force_update' => false,
        'release_notes_ar' => "• إضافة نظام مصاريف الشحن والخدمات الإضافية في الـ POS\\n• تقسيم السداد على أكثر من وسيلة (كاش، إنستاباي، فيزا)\\n• عرض سعري القطاعي والجملة في سلة المبيعات مع التبديل السريع\\n• تحسينات عامة في سرعة واستقرار النظام",
        'release_notes_en' => "• Added POS shipping and extra expenses modal\\n• Added split multi-payment support\\n• Added dual retail and wholesale cart pricing\\n• General stability improvements",
        'apk_filename' => 'sroor-cofe-erp-2m.apk',
        'apk_path' => 'sroor-cofe-erp-2m.apk',
        'apk_size_bytes' => 19400000,
        'download_count' => 0,
        'published_at' => now(),
    ]
);

echo "VERSIONS_SET_TO_1.1.0_SUCCESSFULLY\\n";
"""

sftp = ssh.open_sftp()
with sftp.file(f"{REPO_DIR}/set_version_110.php", "w") as f:
    f.write(php_script)
sftp.close()

stdin, stdout, stderr = ssh.exec_command(f"cd {REPO_DIR} && {PHP_BIN} set_version_110.php && rm set_version_110.php")
print(stdout.read().decode())
ssh.close()
