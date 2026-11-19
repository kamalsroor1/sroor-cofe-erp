# -*- coding: utf-8 -*-
import os, sys, html
sys.path.insert(0, os.path.dirname(__file__))
from data import AREAS

DOC = "/home/kamalsroor/projects/sroor/docs/launch-readiness"
IMG = "file://" + DOC
E = html.escape

def cnt(items, low):
    c = {"P0": 0, "P1": 0, "P2": 0, "P3": 0}
    for it in items + low:
        p = it[1].rstrip("*")
        c[p] += 1
    return c

def badge(p):
    k = p.rstrip("*")
    return f'<span class="b {k.lower()}">{E(p)}</span>'

def main_table(items):
    rows = "".join(
        f"<tr><td class='id'>{E(i)}</td><td>{badge(p)}</td><td>{E(t)}</td><td class='fix'>{E(f)}</td><td class='ef'>{E(e)}</td></tr>"
        for i, p, t, f, e in items)
    return f"<table class='t'><thead><tr><th>الكود</th><th>الأولوية</th><th>المشكلة</th><th>الحل المقترح</th><th>الجهد</th></tr></thead><tbody>{rows}</tbody></table>"

def low_table(low):
    rows = "".join(f"<tr><td class='id'>{E(i)}</td><td>{badge(p)}</td><td>{E(t)}</td></tr>" for i, p, t in low)
    return f"<table class='t small'><thead><tr><th>الكود</th><th>الأولوية</th><th>البند</th></tr></thead><tbody>{rows}</tbody></table>"

tot = {"P0": 0, "P1": 0, "P2": 0, "P3": 0}
area_rows = ""
for name, code, items, low in AREAS:
    c = cnt(items, low)
    for k in tot: tot[k] += c[k]
    area_rows += f"<tr><td>{E(name)}</td><td class='n p0c'>{c['P0']}</td><td class='n'>{c['P1']}</td><td class='n'>{c['P2']}</td><td class='n'>{c['P3']}</td><td class='n'><b>{sum(c.values())}</b></td></tr>"
area_rows += f"<tr class='tot'><td>الإجمالي</td><td class='n p0c'>{tot['P0']}</td><td class='n'>{tot['P1']}</td><td class='n'>{tot['P2']}</td><td class='n'>{tot['P3']}</td><td class='n'>{sum(tot.values())}</td></tr>"

INTRO_MAP = {
 "SEC": "طبقة المنصة قوية: الكونسول معزول، 2FA للمشغّلين، توكنات كل محل في قاعدته، ولا يوجد تسرّب بيانات بين المحلات. المشكلة في إنفاذ حالة الاشتراك، وفي أسرار مكشوفة، وفي إعدادات دخول المستخدمين.",
 "BE": "2,920 اختبار ناجح، الحسابات المالية بـ bcmath، والـ checkout ذرّي ولا يتكرر. أضعف نقطة هي المرتجعات (غير مربوطة بالفاتورة ولا تنعكس عند الحذف) ومخزون الفروع الجديدة. كمان السيرفر بيثق في السعر والخصم اللي جاي من العميل.",
 "POS": "الـ POS الحالي لا يستخدم أغلب الباك اند المبني له (/pos/bootstrap، الوزن، باركود الميزان، الاختصارات). مسح الباركود ممكن يضيف صنف غلط، والدفع والخصم بطيئين، وتخطيط الموبايل مكسور.",
 "FE": "الكود نظيف (ESLint بدون أخطاء، والبناء ناجح). المشاكل في إشعارات وهمية، وتجاهل الصلاحيات والباقات في الواجهة، والعملة والتاريخ. وتطبيق أندرويد غير جاهز لـ Google Play.",
 "OPS": "تصميم الإصدار على VPS ممتاز واختباراته ناجحة، لكنه لم يُجرّب على سيرفر حقيقي. الإنتاج الحالي مكشوف: أسرار في ريبو عام، webhook نشر عام، ولا توجد نسخة احتياطية مُختبرة.",
 "PRD": "مزايا المحل قوية لنسخة أولى. ما يمنع أول بيع هو طبقة الـ SaaS حولها: التحصيل، وإنفاذ الباقات، والاستيراد، والصفحات القانونية، والدعم. وفيه موعدين خارجيين: قانون حماية البيانات وإيصال ETA الإلكتروني.",
}
sections = ""
for idx, (name, code, items, low) in enumerate(AREAS, start=5):
    sections += f"""
<section class="page">
 <h2><span class="num">{idx}</span>{E(name)}</h2>
 <p class="lead">{E(INTRO_MAP[code])}</p>
 <h3>بنود الإطلاق (P0) وما قبل أول عميل (P1)</h3>
 {main_table(items)}
 <h3>بنود لاحقة (P2 / P3)</h3>
 {low_table(low)}
</section>"""

tpl = open(os.path.join(os.path.dirname(__file__), "template.html"), encoding="utf-8").read()
out = (tpl.replace("{{AREA_ROWS}}", area_rows)
          .replace("{{SECTIONS}}", sections)
          .replace("{{IMG}}", IMG)
          .replace("{{TOTAL}}", str(sum(tot.values())))
          .replace("{{P0}}", str(tot["P0"])).replace("{{P1}}", str(tot["P1"])))
open(os.path.join(os.path.dirname(__file__), "report.html"), "w", encoding="utf-8").write(out)
print("totals", tot)

rp = os.path.join(os.path.dirname(__file__), "research.html")
res = open(rp, encoding="utf-8").read() if os.path.exists(rp) else ""
s = open(os.path.join(os.path.dirname(__file__), "report.html"), encoding="utf-8").read().replace("{{RESEARCH}}", res)
open(os.path.join(os.path.dirname(__file__), "report.html"), "w", encoding="utf-8").write(s)
