# Internal product facts (Ibraa / إبراء), Nov 2026

Source: the business guide written from the current code — docs/business/ (start with 00-index.md; feature list in 10-feature-catalog.md; plans in 08-plans-signup-billing.md; security/ops in 09-security-operations.md). Read those files for detail; do not invent features.

## Product
- Cloud POS + inventory + customer/supplier debts + treasury + shifts + reports for Egyptian retail shops (any retail; coffee/spices by weight; wholesalers with delivery vans). Web, Android app, Windows app.
- Database per shop (strong isolation); branches, warehouses, vans; roles and per-branch access.
- Self-signup with email code and automatic shop creation; permanent free plan; per-shop limit overrides; dormant free-shop archiving (60/90 days, no deletion).
- Engineering: ~188k lines of application code, ~4,800 automated tests, 1,128 commits (Aug–Nov 2026), Laravel + Vue.

## Plans (names shown to customers)
- «التجربة» = permanent free plan: 0 EGP, 2 users, 1 branch, 100 items, 150 invoices/month, 50 customers, 20 suppliers.
- «البداية» 499 / «النمو» 999 / «الأعمال» 1,499 EGP per month; yearly = 10 months; prices exclude 14% VAT.
- Founder price schedule: 349/699/1,099 → 429/849/1,299 → list price.
- Add-ons for paid plans (extra user/warehouse/van, mixes, control bundle 199/month).
- Issue: the free plan is named «التجربة» (sounds temporary).

## Status / gaps relevant to feasibility (from the guide's index)
- No real email provider yet (self-signup can't complete in production until configured).
- Payments are manual (InstaPay, Vodafone Cash, transfer + receipt reviewed by staff); no online gateway.
- No VAT on invoices, no ETA e-receipt integration yet (mandatory for the target market — see regulation notes).
- No offline selling; no invoice edit; credit limit not enforced; export not implemented.
- Off-site backups and restore drill not done; production VPS not provisioned; legacy scripts with credentials must be cleaned.
- No paying customers yet (fresh start decided by the owner, Nov 2026).
