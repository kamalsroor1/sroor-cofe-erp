import sys, paramiko
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HOST = "145.79.20.98"
PORT = 65002
USER = "u910151740"
PASS = "Ks@Rr12699"

TARGET_DIRS = [
    "/home/u910151740/domains/sroor.baraa-solutions.com/public_html",
    "/home/u910151740/domains/baraa-solutions.com/public_html/sroor",
    "/home/u910151740/domains/shipping.baraa-solutions.com/public_html",
]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASS, timeout=30)
print("Connected to SSH successfully!")

for t in TARGET_DIRS:
    print("\n" + "=" * 60)
    print(f"📦 جاري التحديث والتنظيف والتسريع في: {t}")
    print("=" * 60)

    cmd = f"""
    cd {t}
    git fetch origin main
    git reset --hard origin/main
    
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    rm -rf storage/framework/views/*
    rm -rf storage/framework/cache/data/*
    
    php artisan optimize:clear
    php artisan view:cache
    php artisan config:cache
    php artisan route:cache
    
    echo "Current commit in {t}: $(git log -n 1 --oneline)"
    grep -n "v1.2.5" resources/views/components/layouts/app.blade.php
    """
    
    stdin, stdout, stderr = ssh.exec_command(cmd, get_pty=True)
    while True:
        line = stdout.readline()
        if not line:
            break
        print("   " + line.rstrip())
    print("Exit code:", stdout.channel.recv_exit_status())

ssh.close()
print("\n🎉 تم تحديث ومزامنة كافة مجلدات وسيرفرات النظام بنجاح!")
