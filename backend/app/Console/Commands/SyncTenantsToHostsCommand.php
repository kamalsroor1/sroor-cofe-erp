<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class SyncTenantsToHostsCommand extends Command
{
    protected $signature = 'tenancy:sync-hosts {--ip=127.0.0.1 : The IP address to map domains to}';

    protected $description = 'مزامنة نطاقات المستأجرين مع ملف Windows Hosts تلقائياً';

    public function handle(): int
    {
        $ip = $this->option('ip');
        $hostsPath = 'C:\\Windows\\System32\\drivers\\etc\\hosts';

        $this->info('🔍 جاري فحص نطاقات المستأجرين في قاعدة البيانات...');

        $domains = Domain::pluck('domain')->unique()->filter()->values()->all();

        $defaultDomains = [
            'makhzani.test',
            'super.makhzani.test',
            'admin.makhzani.test',
        ];

        $allDomains = array_unique(array_merge($defaultDomains, $domains));

        if (! file_exists($hostsPath)) {
            $this->error("❌ لم يتم العثور على ملف hosts في المسار: {$hostsPath}");

            return self::FAILURE;
        }

        $hostsContent = file_get_contents($hostsPath);
        $missingDomains = [];

        foreach ($allDomains as $domain) {
            if (! str_contains($hostsContent, $domain)) {
                $missingDomains[] = $domain;
            }
        }

        if (empty($missingDomains)) {
            $this->info('✅ كافة النطاقات مسجلة بالفعل في ملف hosts!');

            return self::SUCCESS;
        }

        $this->warn('⚠️ تم العثور على '.count($missingDomains).' نطاق غير مسجل في ملف hosts:');
        foreach ($missingDomains as $missing) {
            $this->line("   - {$missing}");
        }

        // Try appending if we have write permissions
        $appendData = "\n# === MAKHZANI ERP AUTO-SYNCED DOMAINS ===\n";
        foreach ($missingDomains as $missing) {
            $appendData .= "{$ip}    {$missing}\n";
        }

        $result = @file_put_contents($hostsPath, $hostsContent.$appendData);

        if ($result !== false) {
            $this->info('✅ تم تحديث ملف hosts بنجاح!');
            @shell_exec('ipconfig /flushdns');

            return self::SUCCESS;
        }

        $this->warn("\n💡 يتطلب تعديل ملف hosts صلاحيات مسؤول (Administrator).");
        $this->info('يمكنك تشغيل السكربت الجاهز بضغطة زر واحدة:');
        $this->line('👉 backend\\scripts\\setup-hosts.bat');

        return self::SUCCESS;
    }
}
