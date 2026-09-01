# سجل تعديل: رفع المشروع على GitHub وهندسة الدبلوي الآلي على خادم Hostinger

* **التاريخ والوقت:** 2026-08-08 22:22
* **الدور المفعل:** DevOps & Backend Architect
* **الهدف من التعديل:** رفع كامل الكود المصدري على مستودع GitHub المخصص عبر 6 كوميتات دلالية مقسمة ومعمارية، وإنشاء Workflow للـ CI/CD للتحقق من الاختبارات والدبلوي التلقائي عبر SSH على خادم Hostinger.

---

## 1. الكوميتات المرفوعة على GitHub ([kamalsroor1/sroor-cofe-erp](https://github.com/kamalsroor1/sroor-cofe-erp)):

1. `5848806` - **`feat(docs):`** initialize architecture, agents playbook, and ai collaboration rules.
2. `de32363` - **`feat(database):`** configure schema with DECIMAL(12,3) precision, migrations, and seeders.
3. `aa9aca1` - **`feat(models-and-services):`** implement core models, services, stock locks, and reports logic.
4. `1d45e79` - **`feat(livewire-ui):`** add responsive Arabic RTL Livewire 4 components and views.
5. `615e19c` - **`feat(pwa-and-ci):`** add PWA support, SweetAlert2 notifications, and Hostinger deployment workflow.
6. `0b9e7aa` - **`test(qa):`** add comprehensive feature test suites for livewire pages, rollbacks, and fractional scale sales.

---

## 2. ملفات الـ CI/CD والنشر السحابي (Deployment Files)
* `[NEW]` `.github/workflows/deploy.yml` - سير عمل GitHub Actions مؤتمت لتشغيل الاختبارات عبر PHPUnit ثم الاتصال بالسيرفر عبر SSH وتشغيل أوامر التحديث والميجريشن.
* `[NEW]` `deploy.sh` - سكربت شل سريع لتحديث الاعتماديات، وتشغيل الـ `migrate --force --seed`، وتخزين الكاش وضبط صلاحيات `storage`.

---

## 3. إعدادات خادم Hostinger:
* **IP:** `145.79.20.98` | **Port:** `65002` | **User:** `u910151740`
* **المسار المستهدف:** `/home/u910151740/domains/baraa-solutions.com/public_html/shipping`
