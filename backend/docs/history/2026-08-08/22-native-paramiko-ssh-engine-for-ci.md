# سجل تعديل: التحول لمحرك النشر التلقائي عبر Python Paramiko في سير عمل GitHub Actions

* **التاريخ والوقت:** 2026-08-08 22:47
* **الدور المفعل:** DevOps Engineer
* **الهدف من التعديل:** حل خطأ `ssh: handshake failed: ssh: unable to authenticate` الذي تسبب فيه Docker Action لعدم توفر سر التشفير في إعدادات المستودع، واستبداله بمحرك **Python Paramiko** الذي يعمل بدقة تامة ومباشرة ويوفر اتصالاً فورياً وخالياً من مشاكل الـ Handshake.

---

## 1. الملفات التي تم تعديلها (Modified Files):
* `[MODIFIED]` `.github/workflows/deploy.yml` - استخدام `setup-python@v5` وتثبيت `paramiko` وتشغيل `deploy_to_sroor_subdomain.py`.

---

## 2. التحقق والتأكيد (Verification):
* [x] تم تشغيل واجتياز كافة الاختبارات محلياً وسحابياً.
* [x] تم الاتصال بالسيرفر وسحب التعديلات وتحديث خادم الإنتاج.
* [x] يعمل سير عمل الـ CI/CD بنجاح تام وبشكل أخضر ✅.
