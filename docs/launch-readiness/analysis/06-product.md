# 06 — Product commercial-readiness gap analysis (Sroor ERP)

> **Superseded 2026-11-15 (PRICE-2026-11, owner decision):** the prices quoted below are no longer current. Catalog now (EGP ex 14% VAT, yearly = 10 months): Basic 499 / 4,990, Pro 999 / 9,990, Enterprise 1,499 / 14,990. Founder pricing is a 3-step schedule from the first payment: months 1-12 = 349 / 699 / 1,099 (yearly 3,490 / 6,990 / 10,990), months 13-24 = 429 / 849 / 1,299 (yearly 4,290 / 8,490 / 12,990), then list price. Source of truth: `backend/database/seeders/Catalog/PlanCatalog.php`, `config/billing.php` (`founder`). The rest of this document is kept as history.

> Author: product-researcher. Date: 2026-11-09. Branch inspected: `feature/multi-tenant` (read-only).
> Input decisions: `docs/01-overview/product-overview.md` (CTO decisions of 2026-10-08 / 2026-10-09). This analysis does **not** override any of them. Where evidence argues for revisiting a decision, the item is tagged **[CTO-Q]** and listed in §7.
> Legend: **exists / partial / missing** = state in our code; **(unconfirmed)** = could not be confirmed from an official source; "unofficial" = third-party source.

---

## 0. Framing correction (read first)

The brief describes the product as "for coffee shops / roasters". The CTO decision of 2026-10-08 (§1 of product-overview) says the opposite: Sroor is a **generic** SaaS for **retail, wholesale/van distribution, and sell-by-weight** shops, and "it is not a coffee product". This analysis follows the decision. Coffee/F&B-only features (kitchen display, menu modifiers, delivery aggregators such as Talabat) are therefore assessed but ranked **P3 / out of current positioning**, and flagged as **[CTO-Q1]**: does the CTO want an F&B vertical at all?

Note the **publicly served brochure** still says "roasters and mills" (see PRD-07).

---

## 1. Executive summary

1. **The core shop software is strong for a v1**: POS with scale barcodes, weighted items, quick keys, held invoices (planned), multi-store with van/warehouse types, transfers, purchases with WAC, returns, shifts with Z-report, treasury, customer/supplier statements, 7 report families, smart reorder, Mix Builder, roles matrix, audit logs, Android + Windows apps with update channel, and a full super-admin console with tenant lifecycle, 2FA, backups and archive/purge. Compared with Loyverse/Daftra on retail basics, it is competitive.
2. **What blocks the first paid sale is the "SaaS shell", not the shop features**: no way for a customer to pay (manual billing is schema-only), plan limits/features are computed but **not enforced on any route**, suspended tenants can still write (SEC-1 M2), no legal documents, no Excel import, no help center, and a public brochure that advertises things we do not have (e-invoice, 24/7 VIP support, real-time backup).
3. **Two external clocks are already ticking and are not in the current plan as P0/P1:**
   - **Egypt PDPL (Law 151/2020)**: executive regulations issued 1 Nov 2025 with a one-year grace period, so enforcement is effectively **from ~1 Nov 2026 (now)**. SaaS processors need a PDPC licence, a DPO, Arabic privacy notices, 72-hour breach notification, and a **transfer licence for data hosted outside Egypt** (our planned Hetzner VPS and Google Drive backups are both outside Egypt). Source: [CMS](https://cms.law/en/are/legal-updates/egypt-s-pdpl-executive-regulations-issued-one-year-compliance-countdown-begins), [Al Tamimi](https://www.tamimi.com/news/from-policy-to-practice-egypt-issues-executive-regulations-of-the-personal-data-protection-law). **[CTO-Q2]**
   - **ETA e-receipt**: the mandate keeps widening (Decision 281/2025, effective 15 Sep 2025) and, more importantly for our segment, ETA's chairperson stated that SMEs under EGP 20M turnover can only stay in the new **simplified tax regime (Law 6/2025)** if they fully comply with e-invoice **and** e-receipt. Our exact target customer is now pushed to buy e-receipt-capable software. The CTO decision places ETA in Phase 3. **[CTO-Q3]** Sources: [KPMG](https://kpmg.com/us/en/taxnewsflash/news/2025/07/tnf-egypt-new-taxpayers-required-to-comply-with-mandate-to-issue-electronic-receipts-for-b2c-transactions.html), [Zawya/ETA head](https://www.zawya.com/en/economy/north-africa/e-invoice-e-receipt-compliance-required-to-benefit-from-simplified-tax-system-egypts-eta-head-b4r659j9).
4. **Pricing is already decided and is well positioned** (449 / 899 / 1,799 EGP ex-VAT, founder 299 / 599 / 999). It sits under Daftra Egypt (733 / 1,225 / 2,448) and far under Foodics Egypt (~2,849+). No change to the ladder is proposed; §6 only proposes prices for add-ons the decision left open (ETA, loyalty, messaging credits, data export) and packaging tweaks, all as questions.
5. **Count:** 36 gaps — **P0: 12**, **P1: 10**, **P2: 8**, **P3: 6**.

---

## 2. What the product already has (inventory from code)

| Area | What exists | Evidence (paths, all under `/home/kamalsroor/projects/sroor/`) | State |
|---|---|---|---|
| POS | Cart, cash/credit/partial, `client_uuid` on invoices, scale label parser per branch, `is_weighted`, quick keys per branch, POS bootstrap, per-store POS settings | `backend/app/Actions/POS/*`, `backend/app/Services/Pos/ScaleBarcodeParser.php`, `backend/database/migrations/tenant/*is_weighted*`, `*create_pos_quick_keys_table.php`, `*create_store_pos_settings_table.php`, `backend/resources/js/views/POS/PosView.vue` | exists (new POS UI is Phase 2) |
| Held invoices, manager PIN, cash in/out, customer display | Designed (POSB-4/5/7/8) | `docs/05-planning/phase-1-plan.md` §4.17 | missing (planned 1a) |
| Payment methods | cash, instapay, e_wallet, visa, bank_transfer, check, other | `backend/app/Enums/PaymentMethod.php` | exists (recording only, no card terminal integration) |
| Sales / invoices | Create, confirm, cancel, print thermal + A4, WhatsApp share (manual link) | `backend/app/Actions/Invoices/*`, `backend/resources/js/Components/Invoices/Show/InvoiceShowActionsBar.vue` | exists |
| Returns | Create/delete | `backend/app/Actions/Returns/*` | exists (POS returns hardening planned POSB-9) |
| Inventory | Items, categories, movements, adjust, store stocks, per-store custom price, min stock, transfers between stores, trash/restore | `backend/app/Actions/Items/*`, `Transfers/*`, `Stores/*`, `Trash/*` | exists. **No variants, no multi-unit conversion, no dedicated barcode column** (only `items.code`) |
| Purchasing | Purchases, cancel, WAC, smart reorder | `backend/app/Actions/Purchases/*`, `backend/resources/js/views/Purchases/SmartReorderView.vue` | exists |
| Customers / suppliers | Statements, collect/pay, price tier (retail/wholesale), tax number | `backend/app/Actions/Customers/*`, `Suppliers/*`, tenant `create_customers_table` | exists. Credit limit planned (POSB-1). No loyalty |
| Treasury / shifts | Shifts open/close, Z-report, daily journal, treasury summary, expenses + cost centres | `backend/app/Actions/Shifts/*`, `Treasury/*`, `Expenses/*` | exists |
| Reports | P&L, item profitability, inventory valuation, treasury, expenses breakdown, customer sales, store comparison; CSV exports | `backend/app/Actions/Reports/*`, `backend/app/Services/ExportService.php` | exists (CSV only, no XLSX/PDF) |
| Mix Builder | Builds an ad-hoc mix and sells components as invoice lines; cost calc | `backend/app/Actions/Blends/*`, `backend/resources/js/views/CoffeeBlender/CoffeeBlenderView.vue` | partial (no saved recipe/BOM; still coffee wording and hardcoded Arabic in `CreateBlenderInvoiceAction`; rename ENTI-4 planned) |
| Users / roles | Users, roles matrix, permissions tree, store assignment | `backend/app/Actions/Users/*`, `Roles/*`, `Permissions/*` | exists |
| Settings | currency, timezone, locale, digits, business-day cutoff, units, low-stock threshold, commercial register, tax registration no., branding, Telegram | `backend/app/Services/Settings/TenantSettings.php`, `backend/resources/js/views/Settings/SettingsView.vue` | partial (sales-rules / VAT tabs not done) |
| VAT on documents | Only the tenant `tax_registration_no` key | no tax columns in tenant `create_items_table` / `create_invoices_and_items_tables` | missing (SETG planned) |
| Plans / features / add-ons | 4 plans, 26 feature keys, add-on catalog, founder pricing counter | `backend/database/seeders/Catalog/{FeatureCatalog,PlanCatalog,AddonCatalog}.php`, `backend/app/Services/Billing/FounderPricingService.php` | exists (catalog) |
| Entitlements | Engine computes features/limits per tenant | `backend/app/Services/Entitlements/TenantEntitlementService.php`, `backend/resources/js/Components/FeatureGate.vue` | partial: **no route middleware or Action calls it** (grep finds it only in the service, DTO and `Tenant` model) |
| Subscription billing | Models + tables: `Subscription`, `SubscriptionAddon`, `Addon`, `BillingInvoice`, `BillingPayment`, `BillingSequence`, credit ledger; gateway enum | `backend/app/Models/Billing*.php`, `backend/app/Enums/Billing/*`, central migrations `create_billing_*` | partial: **no Actions, routes or UI** (ENTI-3.1–3.10 planned) |
| Tenant lifecycle | trial / active / past_due / read_only / suspended / cancelled / archived, transitions, provisioning with retry, archive, purge, backups to Drive | `backend/app/Enums/TenantStatus.php`, `backend/app/Actions/Tenants/*` | exists (enforcement on tenant writes open: SEC-1 M2 / IDEN-3.5) |
| Super-admin console | Dashboard, tenants, tenant detail, plans, platform settings, units, app versions, impersonation, 2FA + step-up, password reset, rate limits | `backend/routes/central.php`, `backend/resources/js/views/SuperAdmin/*` | exists |
| Signup | Only super-admin `POST /api/v1/super-admin/tenants` → `ProvisionTenantAction` | `backend/app/Actions/Tenants/ProvisionTenantAction.php` | missing (self-serve) |
| Apps | Android (Capacitor 8: network, camera, biometric, app update), Windows (Electron with ESC/POS printing + cash drawer), `/download` page, forced-update channel | `backend/package.json`, `desktop/src/hardware/{escpos,cashDrawer,rawPrinter}.js`, `backend/app/Actions/AppVersions/*` | exists. **Android has no printer plugin** (Bluetooth/Sunmi) |
| Offline | Offline banner + block selling | `backend/resources/js/Composables/useConnectivity.js`, `backend/resources/js/Components/Layout/OfflineBanner.vue` | partial (selling offline = end of Phase 2) |
| Marketing | Public `/brochure` page | `backend/routes/web.php` line 29, `backend/resources/views/marketing-brochure.blade.php` | partial and **inaccurate** (PRD-07) |
| Import from Excel | none; no spreadsheet package in `composer.json` | `backend/composer.json` | missing |
| Legal / help | none (no terms, privacy, help pages) | grep over `backend/resources` | missing |

---

## 3. Competitor snapshot (Egypt / MENA first, then global)

| Competitor | Market / price (as published) | Features relevant to our gaps | Source |
|---|---|---|---|
| **Daftra** | Egypt, EGP: 733 / 1,225 / 2,448 per month on monthly billing; yearly ≈ 489.5 / 977.6 / 1,960 per month. Extra user 392, branch 1,293.6, warehouse 637, **offline POS terminal 637 (Premium only)**. 14-day trial, no card, no setup fee. | Loyalty points module; Egyptian e-invoice integration (registers as ERP on ETA portal); **Excel product import**; offline POS stores locally and syncs. E-receipt support: (unconfirmed) | [daftra.com/en/plans](https://www.daftra.com/en/plans), [ETA integration doc](https://docs.daftra.com/en/?p=8766), [Excel import doc](https://docs.daftra.com/en/?p=8179) |
| **Foodics** | Egypt, EGP: Basic bundle ≈ 2,849–3,020 / month, Advanced ≈ 3,734–3,958 / month, billed annually/quarterly (labels ambiguous on the page). Hardware quoted after demo. | Basic: inventory, coupons, gift cards, promotions, tables. Loyalty, API, delivery aggregators = add-on in Basic, included in Advanced. KDS, customer display, kiosk sold as separate products. Talabat orders reach the POS through partners (Deliverect, Blend). | [foodics.com/foodics-pricing-egypt](https://www.foodics.com/foodics-pricing-egypt/), [Foodics × Deliverect](https://www.foodics.com/portfolio/deliverect), [Blend help](https://help.foodics.com/hc/en-us/articles/20405475656348-Blend) |
| **Wafeq** | Egypt, EGP 966 / 1,386 / 2,490 (from the pricing doc already in repo) | Publishes an Egyptian e-receipt compliance guide (third-party guide, not legal source) | [wafeq e-receipt guide](https://www.wafeq.com/en-eg/tax-and-reporting/e-receipt-system), `docs/03-architecture/pricing-model-recommendation-2026-10.md` |
| **Rewaa** | KSA, SAR ex-VAT, annual only: 275 / 367 / 708 per month; 2-year plans cheaper | Loyalty (Bonat) included from Growth; e-commerce integration; ZATCA phase 2 in all plans; Android cashier app; device-set discounts. Offline exists but Rewaa itself calls it a stopgap (stock and price may be stale). | [rewaa.com/pricing](https://rewaa.com/pricing), [Rewaa offline help](https://intercom.help/rewaa/en/articles/8633718-offline-selling), [offline + ZATCA](https://intercom.help/rewaa/en/articles/11426915-how-to-sell-offline-in-compliance-with-zatca) |
| **Qoyod** | KSA; 14-day trial without card; removed the monthly option in its April 2026 app release (annual only). Exact 2026 prices: (unconfirmed, third-party only) | Full offline mode incl. scale barcodes, split payment, signed QR invoices synced to ZATCA later; LAN sync between devices without internet (Apr 2026); Jordan JoFotara support (Oct 2026). | [Qoyod offline](https://www.qoyod.com/en/point-of-sale/offline-mode/), [Apr 2026 release](https://academy.qoyod.com/en/releases/april-2026-mobile-app-update/), [POS 2.8.3](https://www.qoyod.com/en/releases/pos-app-v2-8-3/) |
| **Dexef / local Egyptian desktop POS** | Price not published (quote-based) | Markets itself as "fastest cashier screen in Egypt". Details: (unconfirmed, page fetch failed) | [dexef.com](https://dexef.com/apps/fastest-cashier-program) |
| **Loyverse** | Global: POS free; add-ons $5/store sales history, $5/employee, **$25/store advanced inventory**; annual = 10 months | **Free**: loyalty, customer display, kitchen display, multi-store, integrations. Self-signup without card; CSV item import/export. | [loyverse.com/pricing](https://loyverse.com/pricing), [import help](https://help.loyverse.com/help/importing-and-exporting), [getting started](https://help.loyverse.com/help/gettings-starteds) |
| **Odoo POS** | Global per-user (see repo pricing doc) | Offline sessions sync later; preparation display; loyalty, gift cards, eWallet; variants; self-ordering; scale integration; split payments | [Odoo POS features](https://www.odoo.com/app/point-of-sale-features), [Preparation display docs](https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/preparation.html) |
| **Square** | US: Free / Plus / Premium; KDS add-on $20–30 per device per month | Offline card payments up to 24 h, seller carries the risk; loyalty not supported offline | [Square restaurants pricing](https://squareup.com/us/en/point-of-sale/restaurants/pricing), [offline FAQ](https://square.com/help/us/en/article/7775-process-offline-payments-faq) |

**What customers in this market now treat as table stakes** (≥ 3 of the competitors above): no-card 14-day trial with self-signup; Excel/CSV import; tax compliance (ETA in Egypt, ZATCA in KSA) inside the POS; offline selling on at least one device type; loyalty (free or low-cost); Android POS app; annual billing at "10 months".

---

## 4. Gap list

Priority: **P0** = must exist before the first paid customer; **P1** = first 3 months after launch; **P2** = 3–9 months; **P3** = later / needs a positioning decision. Effort: **S** ≤ 1 week, **M** 1–4 weeks, **L** > 4 weeks (agent-assisted calendar estimate, not measured).

### P0 — before selling

**PRD-01 — Enforce plan features and limits on every request**
- Controls: which modules and how many users/stores/items/invoices a tenant may use. Scope: tenant. 
- State: **partial**. Engine exists (`backend/app/Services/Entitlements/TenantEntitlementService.php`, `backend/app/Enums/LimitResource.php`) but no middleware or Action calls it; `FeatureGate.vue` exists on the frontend only.
- Why: without it every plan is Enterprise and add-ons cannot be sold. Already ENTI-2.x in Phase 1.
- Effort: M. Phase: 1a (planned). Risk: tenancy, revenue leakage; must fail closed with a translated 403 + upgrade CTA, never block reads of own data.

**PRD-02 — Enforce tenant status (read-only / suspended) on tenant writes**
- State: **partial**; statuses exist (`backend/app/Enums/TenantStatus.php`), SEC-1 finding M2 says suspended/cancelled/archived tenants still log in and write (IDEN-3.5, W3).
- Why: the trial → read-only → suspended policy (§7 of decisions) is the only lever to collect money. Effort: S–M. Risk: security + money.

**PRD-03 — Manual subscription billing end-to-end (issue invoice → InstaPay/Vodafone Cash transfer → receipt upload → super-admin verify → activate)**
- State: **partial**: models/tables/enums/sequence exist (`backend/app/Models/BillingInvoice.php`, `BillingPayment.php`, `Subscription.php`, `backend/app/Services/Billing/BillingSequenceService.php`); no Actions, routes, or screens (ENTI-3.1–3.10).
- Why: decided as the first payment path (decision §8). InstaPay has no public merchant API today, so manual reconciliation is the realistic first step ([Orchestrapay notes merchant InstaPay is "upcoming"](https://www.orchestrapay.com/coverage/africa/egypt), unofficial).
- Effort: M. Packaging: all plans. Risk: money (bcmath, VAT 14% on SaaS invoice), upload security (private central disk, signed URLs).

**PRD-04 — Tenant "Subscription & usage" page (plan, limits used, trial countdown, add-ons, invoices, pay instructions)**
- State: **missing** (`views/Billing/*` planned as ENTI-3.7).
- Why: Daftra, Zoho, Loyverse all expose plan/usage self-service. Without it every upgrade is a phone call. Effort: M. Phase: 1a.

**PRD-05 — Assisted signup now, self-signup next [CTO-Q4]**
- Controls: how a prospect gets a tenant. Scope: platform.
- State: **partial**: provisioning is automated (`backend/app/Actions/Tenants/ProvisionTenantAction.php`, retry, async) but only callable by a super-admin (`backend/routes/central.php` `POST /tenants`). Decision §18 puts self-signup in Phase 3.
- Evidence: 14-day no-card self-signup is the norm: [Daftra](https://www.daftra.com/en/plans), [Loyverse](https://loyverse.com/pricing), [Qoyod](https://www.qoyod.com/en/pos) (trial claim on page header), Zoho (repo pricing doc).
- Recommendation: P0 = public "start trial" form (name, shop name, phone with OTP, business template) that queues a provisioning request the super-admin approves in one click; P1 = fully automatic with phone OTP + rate limiting + abuse checks. Effort: S (assisted) / M (self). Risk: abuse (DB-per-tenant cost: each signup creates a database), spam, phone verification cost.

**PRD-06 — Excel/CSV import: items (with opening stock per store), customers and suppliers (with opening balances), categories**
- State: **missing**; no import code, no spreadsheet package in `backend/composer.json`. Export is CSV only (`backend/app/Services/ExportService.php`).
- Evidence: [Daftra Excel import](https://docs.daftra.com/en/?p=8179), [Loyverse CSV import](https://help.loyverse.com/help/importing-and-exporting). The approved 1,500 EGP "setup & data migration" service (decision §6) assumes a tool exists.
- Effort: M (dry-run preview, row-level errors in Arabic, idempotent re-run, opening balances posted as documented movements). Packaging: all plans (self-service); assisted import = paid service. Risk: **stock and money**: opening stock/balances must create proper `stock_movements` and ledger entries inside `DB::transaction()`, never direct column writes; limit checks (PRD-01) apply.

**PRD-07 — Public website: landing page, pricing page from the live catalog, and fix the current brochure**
- State: **partial and risky**. `/brochure` (`backend/resources/views/marketing-brochure.blade.php`) is publicly routed and advertises "roasters and mills", "e-invoice", "VIP 24/7 support", "real-time encrypted backup", and an "early e-invoice 200 EGP/month" offer. None of these exist; backups are daily, not real-time.
- Why: selling unbuilt features is a refund/consumer-complaint risk and contradicts decision §1 (generic positioning) and §11 (ETA = Phase 3).
- Recommendation: unpublish or rewrite the brochure now; build a pricing page that reads `PlanCatalog`/`AddonCatalog` (one source of truth, prices "+14% VAT"). Effort: S (fix) + M (site). Commercial name still undecided (decision W0) → **[CTO-Q5]**.

**PRD-08 — Legal pack: Terms of Service, Privacy Notice (Arabic), Data Processing Agreement, refund/cancellation policy, SLA summary; recorded acceptance**
- State: **missing**.
- Evidence: Egypt PDPL executive regulations (Decree 816/2025) issued 1 Nov 2025 with a one-year grace period → enforcement from about 1 Nov 2026. Requires licence for controllers **and processors**, DPO, Arabic privacy notices, 72-hour breach notice to PDPC, and a licence for transfers outside Egypt including cloud ([CMS](https://cms.law/en/are/legal-updates/egypt-s-pdpl-executive-regulations-issued-one-year-compliance-countdown-begins), [Chambers](https://chambers.com/articles/a-first-look-at-egypt-s-personal-data-protection-executive-regulations), [Clyde & Co](https://www.clydeco.com/en/insights/2026/01/egypt-regulatory-update-on-data-privacy)).
- Product work: acceptance checkbox with version + timestamp stored centrally; privacy page; in-app link; breach-notification runbook. Effort: S (code) + legal counsel (external). Risk: **legal**: production VPS on Hetzner and backups on Google Drive are cross-border transfers → **[CTO-Q2]** (license, or Egyptian hosting). This analysis is not legal advice.

**PRD-09 — VAT on sales and purchase documents (tenant toggle, per-item rate 14% / exempt, tax-inclusive prices, VAT on receipt and reports)**
- State: **missing** (only `tax_registration_no` setting in `backend/app/Services/Settings/TenantSettings.php`; no tax columns on items/invoices). Decided for Phase 1 (decision §11), off by default.
- Why: any VAT-registered shop cannot issue a legal tax invoice without it; also the base for ETA (PRD-16). Effort: M. Risk: money (bcmath, rounding half-up already unified in SETG-13), reports must split net / VAT.

**PRD-10 — Remove or hide sold-but-unbuilt features from the public catalog**
- State: `quotations.manage` is a **core, public** feature in `backend/database/seeders/Catalog/FeatureCatalog.php` while the file itself notes it "has no routes in Phase 1". Same pattern for anything the plan page will list.
- Why: the plan page will promise "Quotations" in every plan. Effort: S (set `is_public=false` until built, or build quotations). **[CTO-Q6]** (this changes the decided "18 core features" count).

**PRD-11 — Arabic help center + support channel + onboarding checklist**
- State: **missing** (no help content or in-app help link). Support impersonation with consent is decided and partly built (`backend/app/Actions/SuperAdmin/ImpersonateTenantAction.php`).
- Evidence: every competitor runs a public help center ([Loyverse](https://help.loyverse.com/help/gettings-starteds), [Daftra docs](https://docs.daftra.com/en/user_manual/point-of-sales-guide-pos/), [Rewaa intercom](https://intercom.help/rewaa/en/articles/8633718-offline-selling), [Foodics](https://help.foodics.com/)).
- Minimum for P0: 15–20 Arabic articles (first sale, add item, scale setup, shift close, returns, printer setup, Android install), a WhatsApp support number from `config`/`env` (not hardcoded), support hours stated honestly, and a first-run checklist in the dashboard (add store → import items → add user → first sale → close shift). Effort: M (mostly content). Packaging: all plans; "premium support 299/month" already decided.

**PRD-12 — Production go-live prerequisites (cross-reference)**
- Secrets rotation (SEC-1 C1, CTO-owned and a blocker for any cut-over), VPS deploy actually executed, first restore drill, real Google Drive backup, Android/Windows update tested on a device. All tracked in `docs/05-planning/phase-1-plan.md` §8.1 as "done with condition". Listed here only because a paid customer cannot be onboarded until they are closed. Effort: M. Owner: devops / CTO.

### P1 — first 3 months

**PRD-13 — Android receipt printing (Bluetooth ESC/POS and built-in printers on Android POS terminals such as Sunmi-class devices)**
- State: **partial**: Windows has ESC/POS + drawer (`desktop/src/hardware/escpos.js`, `cashDrawer.js`); Android has no printer plugin in `backend/package.json` (only network, camera, biometric, etc.).
- Why: decision says Android is in every plan; Android all-in-one terminals are the cheapest POS hardware path in Egypt (Foodics and Paymob ship POS devices to Egyptian restaurants: [Foodics × Paymob](https://yourstory.com/ys-gulf/foodics-paymob-partner-digital-transformation-egypt-f-and-b)). Our approved hardware bundles (4,900 / 6,800 EGP) must print. Effort: M. If bundles are sold before this lands, it becomes P0. **[CTO-Q7]**

**PRD-14 — Certified hardware list and bundle definition** (printer models, scanners, drawers, label scales tested with `ScaleBarcodeParser`)
- State: **partial** (parser `backend/app/Services/Pos/ScaleBarcodeParser.php`; checklist `docs/07-operations/desktop-print-drawer-checklist.md`; APP-5 says no real printer/drawer test yet). Evidence: Square, Zoho, Qoyod publish hardware lists ([Zoho POS hardware](https://www.zoho.com/en-sa/pos/resources/help/hardware.html), [Qoyod printers release](https://www.qoyod.com/releases/pos-app-ux-printers/)). Effort: S.

**PRD-15 — Paymob card / wallet payment for subscriptions [CTO-Q8]**
- State: **missing** (gateway enum exists in `backend/app/Enums/Billing/BillingGateway.php`; Strategy abstraction planned ENTI-3.1). Decision: Paymob after manual, in Phase 3.
- Evidence: Paymob offers tokenised recurring subscriptions with retries ([Paymob developer docs](https://developers.paymob.com/paymob-docs/payments-and-features/core-features/subscriptions)); card fee ~2.75% (+ fixed) per unofficial comparisons ([paymentproviders.io](https://paymentproviders.io/country/eg)). Manual verification will not scale beyond ~50–100 tenants of super-admin time.
- Recommendation: pull Paymob one-off checkout (card + wallets) into P1; recurring auto-charge P2. Effort: M. Risk: webhook signature verification, idempotency on `gateway_reference` (already unique per gateway).

**PRD-16 — ETA e-receipt (B2C) integration as a paid add-on [CTO-Q3]**
- State: **missing**; ETA-ready data partly planned (decision §11: tax registration, activity code, per-item EGS/GS1 code, immutable receipts).
- Evidence: mandate widened by Decision 281/2025 from 15 Sep 2025 ([KPMG](https://kpmg.com/us/en/taxnewsflash/news/2025/07/tnf-egypt-new-taxpayers-required-to-comply-with-mandate-to-issue-electronic-receipts-for-b2c-transactions.html), [VATupdate](https://www.vatupdate.com/2025/08/09/egypt-expands-e-receipt-mandate-for-b2c-transactions-starting-september-2025)); SMEs under EGP 20M must comply to keep the simplified regime ([Zawya](https://www.zawya.com/en/economy/north-africa/e-invoice-e-receipt-compliance-required-to-benefit-from-simplified-tax-system-egypts-eta-head-b4r659j9), [Daily News Egypt](https://www.dailynewsegypt.com/?p=845942)); ETA publishes an official e-receipt API (authenticate POS, submit signed receipts, retrieve, packages) ([ETA SDK](https://sdk.invoicing.eta.gov.eg/ereceiptapi/)); an integrator summary says ETA's POS FAQ lets a single-branch accounting system register as a "software POS" ([SNI Technology](https://snitechnology.net/five-key-questions-about-e-receipt-in-egypt/), unofficial; (unconfirmed) against the ETA FAQ itself). Third-party guides cite fines up to EGP 50,000 per violation ([Wafeq guide](https://www.wafeq.com/en-eg/tax-and-reporting/e-receipt-system), unofficial).
- Recommendation: keep Phase 3 for full delivery if capacity forces it, but move **(a)** the ETA-ready data model (PRD-09 + item codes + POS serial per device + immutable receipt UUID) into Phase 1/2 and **(b)** a pilot integration with one tenant into the first 3 months, because it is now the strongest purchase trigger in our segment. Effort: L. Packaging: add-on (see §6). Risk: per-device registration (needs a device registry, which per-device pricing also needs), signing, offline queue interaction (PRD-18).

**PRD-17 — Tenant data export / portability on demand** (full XLSX/CSV export of items, customers, suppliers, invoices, movements; and a final export when cancelling)
- State: **partial**: per-screen CSV exports exist (`ExportService.php`), backups are super-admin only (`backend/app/Actions/Tenants/TakeTenantBackupAction.php`).
- Why: buyer trust ("can I leave with my data?"), PDPL data-subject requests, and the decided cancellation/archive flow. Effort: S–M.

**PRD-18 — Offline POS selling** (decided: end of Phase 2; Pro/Enterprise included, 149 add-on for Basic)
- State: **partial** (banner + block).
- Evidence: Daftra (Premium only, per terminal), Qoyod (full, with deferred tax signing), Rewaa (limited), Odoo, Loyverse ("offline sales" in its feature overview), Square (24 h). Egyptian shops face power/Internet cuts; this is a frequent objection. Effort: L. Aligned with decision; no change proposed. Risk: stock goes negative on sync (decided: alert, not reject), duplicate sales (decided: `client_uuid` idempotent queue).

**PRD-19 — Units of measure with conversion + multiple barcodes per item** (piece / box / carton with factor; wholesale sells cartons, retail sells pieces)
- State: **missing** (single free-text `unit` and `code` per item; units list is a setting). Flagged in the 2026-10-07 review finding 19.
- Why: decision §1 targets wholesale/van distribution; carton↔piece is the daily workflow there. Odoo/Daftra support UoM (Odoo variants confirmed on [features page](https://www.odoo.com/app/point-of-sale-features); Daftra UoM: (unconfirmed)). Effort: L. Risk: **stock**: all quantities must normalise to the base unit inside the stock engine.

**PRD-20 — Trial-to-paid funnel instrumentation in the console** (signups, activation events such as first sale/first import, trial expiry list, conversion, MRR, churn)
- State: **partial** (`backend/app/Services/SuperAdminAnalyticsService.php`, super-admin dashboard). Effort: S. Why: the founder-pricing window (first 50 customers) needs weekly measurement.

**PRD-21 — Item variants (size/colour) for retail** (generic retail: clothes, phones, accessories templates decided for Phase 3)
- State: **missing**. Evidence: [Odoo variants](https://www.odoo.com/app/point-of-sale-features); Loyverse variants: (unconfirmed). Effort: L. Can follow PRD-19. Priority P1 only if the first customers are in apparel; otherwise P2.

**PRD-22 — Customer receipt by WhatsApp: one-tap share with a signed public receipt link**
- State: **partial**: manual share exists (`whatsapp.share` core feature, `InvoiceShowActionsBar.vue`); print routes still need signed URLs (open follow-up in phase-1-plan §8.1). Effort: S. Packaging: all plans (decided free). Paid automated sending = PRD-27.

### P2 — 3 to 9 months

**PRD-23 — Loyalty points (earn rate, point value, min invoice, expiry, redemption at POS, print on receipt)**
- State: **missing**; already catalogued as `loyalty.*` (8 keys) "Could / P3" in `docs/02-requirements/tenant-settings-catalog.md`.
- Evidence: free in [Loyverse](https://loyverse.com/pricing); included from Rewaa Growth ([rewaa.com/pricing](https://rewaa.com/pricing)); Foodics add-on in Basic, included in Advanced ([Foodics EG](https://www.foodics.com/foodics-pricing-egypt/)); Daftra has a loyalty module ([plans](https://www.daftra.com/en/plans)). Recommend moving from P3 to P2 because it is cheap to build relative to its sales weight. Effort: M. Risk: money (points are a liability; redemption must post as a payment method / discount consistently in reports).

**PRD-24 — Promotions and coupons** (buy-X-get-Y, time-boxed % off, coupon codes) beyond the existing retail/wholesale tier and per-store price
- State: **partial** (`customers.price_tier`, `store_stocks.custom_selling_price`, invoice-level discount). Evidence: Foodics Basic includes coupons, promotions, timed events ([Foodics EG](https://www.foodics.com/foodics-pricing-egypt/)); Rewaa lists promotions in all plans. Effort: M. Risk: money (margin floor, `min_selling_price` not enforced server-side per review finding 18).

**PRD-25 — Fawry reference-code payment for subscriptions** (decided after Paymob)
- Evidence: FawryPay "Pay at Fawry" reference flow ([unofficial API mirror](https://apis.io/apis/fawry/fawry-payments-api/), [developer guide](https://dev.to/_moehab/a-comprehensive-guide-to-integrate-fawrypay-api-into-your-own-restful-api-server-478d), both unofficial). Recurring/tokenised Fawry: (unconfirmed). Effort: M.

**PRD-26 — Accounting export** (daily sales/VAT/treasury journal as XLSX in a chart-of-accounts layout; optional push to an accountant's system)
- State: **missing** (no GL / chart of accounts in tenant schema). Why: small shops keep an external accountant; Daftra/Qoyod/Wafeq bundle full accounting, we do not. Effort: M (export) / L (full GL — not recommended now).

**PRD-27 — Paid messaging: automated WhatsApp/SMS receipts, payment reminders, low-stock alerts** (decided: Phase 3 credits add-on; schema supports `credits`)
- Evidence: Meta bills per delivered template message by recipient country; Egypt utility ≈ $0.0036 and marketing ≈ $0.0644 per message, and from 1 Oct 2026 utility messages are billed even inside the service window (reseller figures, unofficial: [ChatMaxima](https://chatmaxima.com/whatsapp-api-pricing/egypt/); official structure: [Meta pricing](https://developers.facebook.com/docs/whatsapp/pricing)). Effort: M. Packaging: credits packs (§6).

**PRD-28 — Customer-facing display** (POSB-7 contract, local second screen)
- State: **partial** (contract planned). Evidence: Loyverse free customer display ([pricing](https://loyverse.com/pricing)). Effort: S–M.

**PRD-29 — Saved mixes / recipes (BOM) generalised from Mix Builder** (save a formula, produce a mixed item into stock with waste %, deduct components; sell the mix as one SKU)
- State: **partial**: today the mix is sold as component lines and is not saved (`backend/app/Actions/Blends/CreateBlenderInvoiceAction.php`, which also still contains hardcoded Arabic and roast/grind wording that ENTI-4.1 removes). Effort: M. Packaging: inside `mixes.manage` add-on (199). Risk: stock (component deduction + WAC of the produced item).

**PRD-30 — Business templates on signup** (retail / wholesale & van / weight — decided for 1a: preset units, settings, roles, demo items)
- State: **partial** (seeders exist: `backend/database/seeders/CoffeeItemsSeeder.php`, `PhoneAndAccessoriesSeeder.php`, `TenantSampleSeeder.php`, but they are coffee/phones demo data, not templates). Effort: S–M. P1 if self-signup (PRD-05) ships.

### P3 — later or needs a positioning decision

**PRD-31 — Kitchen / order preparation display (KDS)** — F&B only. Free in [Loyverse](https://loyverse.com/pricing); [Odoo preparation display](https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/preparation.html); [Square KDS $20–30/device/month](https://squareup.com/us/en/point-of-sale/restaurants/pricing); Foodics sells KDS separately. State: **missing**. Effort: L (needs order states, tables/tickets, real-time push; Reverb is Phase 3). **Out of current positioning → [CTO-Q1].**

**PRD-32 — Menu modifiers / add-ons / combos** (size, sugar, extra shot) — F&B. [Loyverse modifiers](https://help.loyverse.com/help/how-set-and-apply-modifiers). State: **missing**. Effort: L. [CTO-Q1].

**PRD-33 — Delivery aggregator integration (Talabat, etc.)** — Foodics does it via partners ([Deliverect](https://www.foodics.com/portfolio/deliverect), [Blend](https://help.foodics.com/hc/en-us/articles/20405475656348-Blend)); direct Talabat Egypt POS API availability: (unconfirmed). State: **missing**. Effort: L. Recommend: integrate through an aggregator only if F&B is chosen. [CTO-Q1].

**PRD-34 — Public API + webhooks (`api.access`, Pro add-on 299 / Enterprise)** — decided. State: **missing** (feature key only). Effort: L.

**PRD-35 — English UI** — decided Phase 3; keys already bilingual. State: **partial**. Effort: M.

**PRD-36 — ETA e-invoice (B2B) for wholesale tenants** — needed by wholesale/distribution tenants selling to companies. Daftra integrates with the ETA e-invoice portal ([Daftra ETA doc](https://docs.daftra.com/en/?p=8766)). State: **missing**. Effort: L. Can reuse PRD-16 signing work; move to P2 if wholesale is the lead segment.

---

## 5. Compact gap table

| ID | Pri | Title | Effort | State |
|---|---|---|---|---|
| PRD-01 | P0 | Enforce plan features & limits on routes | M | partial |
| PRD-02 | P0 | Enforce tenant status (read-only/suspended) on writes | S–M | partial |
| PRD-03 | P0 | Manual subscription billing end-to-end | M | partial |
| PRD-04 | P0 | Tenant subscription & usage page | M | missing |
| PRD-05 | P0 | Assisted signup now (self-signup P1) [CTO-Q4] | S / M | partial |
| PRD-06 | P0 | Excel/CSV import with opening stock & balances | M | missing |
| PRD-07 | P0 | Landing + pricing page; fix inaccurate public brochure | S + M | partial |
| PRD-08 | P0 | Legal pack + PDPL compliance (ToS, privacy, DPA, acceptance) [CTO-Q2] | S + legal | missing |
| PRD-09 | P0 | VAT on sales/purchase documents | M | missing |
| PRD-10 | P0 | Hide unbuilt features (e.g. `quotations.manage`) from catalog [CTO-Q6] | S | partial |
| PRD-11 | P0 | Arabic help center, support channel, first-run checklist | M | missing |
| PRD-12 | P0 | Go-live prerequisites (secrets rotation, VPS, restore drill, device tests) | M | partial |
| PRD-13 | P1 | Android receipt printing (Bluetooth / built-in) [CTO-Q7] | M | partial |
| PRD-14 | P1 | Certified hardware list & bundles | S | partial |
| PRD-15 | P1 | Paymob checkout for subscriptions [CTO-Q8] | M | missing |
| PRD-16 | P1 | ETA e-receipt add-on (data model now, pilot in 3 months) [CTO-Q3] | L | missing |
| PRD-17 | P1 | Tenant self-service full data export | S–M | partial |
| PRD-18 | P1 | Offline POS selling (as decided) | L | partial |
| PRD-19 | P1 | Units of measure with conversion + multi-barcode | L | missing |
| PRD-20 | P1 | Trial-to-paid funnel & MRR metrics in console | S | partial |
| PRD-21 | P1 | Item variants (size/colour) | L | missing |
| PRD-22 | P1 | WhatsApp receipt with signed public link | S | partial |
| PRD-23 | P2 | Loyalty points (move from P3) | M | missing |
| PRD-24 | P2 | Promotions & coupons | M | partial |
| PRD-25 | P2 | Fawry reference-code payments | M | missing |
| PRD-26 | P2 | Accounting journal export (XLSX) | M | missing |
| PRD-27 | P2 | Paid WhatsApp/SMS messaging credits | M | missing |
| PRD-28 | P2 | Customer-facing display | S–M | partial |
| PRD-29 | P2 | Saved mixes / recipes (BOM) from Mix Builder | M | partial |
| PRD-30 | P2 | Business templates on signup | S–M | partial |
| PRD-31 | P3 | Kitchen / preparation display [CTO-Q1] | L | missing |
| PRD-32 | P3 | Menu modifiers / combos [CTO-Q1] | L | missing |
| PRD-33 | P3 | Delivery aggregators (Talabat) [CTO-Q1] | L | missing |
| PRD-34 | P3 | Public API + webhooks | L | missing |
| PRD-35 | P3 | English UI | M | partial |
| PRD-36 | P3 | ETA e-invoice (B2B) | L | missing |

Totals: P0 12 · P1 10 · P2 8 · P3 6 = 36.

---

## 6. Pricing & packaging proposal (EGP, ex-VAT 14%)

The ladder, limits, add-ons, founder pricing and trial policy are **CTO decisions (2026-10-08) and are kept unchanged**. Below: the decided structure for reference, then proposals only for what is still open. Everything in 6.2–6.4 is a proposal for the CTO.

### 6.1 Decided ladder (unchanged)

| | Trial (14 days) | Basic | Pro (popular) | Enterprise |
|---|---|---|---|---|
| Monthly | 0 | 449 | 899 | 1,799 |
| Yearly (= 10 months) | 0 | 4,490 | 8,990 | 17,990 |
| Founder (first 50, 12 months) | — | 299 | 599 | 999 |
| Retail branches / warehouses / vans | 1 / 0 / 0 | 1 / 1 / 0 | 3 / 2 / 1 | 10 / 5 / 5 |
| Users | 3 | 3 | 8 | 25 |
| Items / invoices per month | 200 / 300 | 3,000 / 6,000 | 20,000 / 30,000 | unlimited |

Market position: Basic is ~39% under Daftra Basic monthly (733) and Pro is ~27% under Daftra Advanced (1,225); Foodics Egypt starts around 2,849/month ([Daftra](https://www.daftra.com/en/plans), [Foodics EG](https://www.foodics.com/foodics-pricing-egypt/)). The extra branch at 249 is far below Daftra's 1,293.6. This is a sound "value challenger" position.

### 6.2 Proposed prices for add-ons the decision left open

| Add-on | Proposal | Basic | Pro | Enterprise | Rationale |
|---|---|---|---|---|---|
| ETA e-receipt (B2C) | **249 / month per tenant, includes 1 registered POS device; +79 per extra device** | add-on | add-on | included | Decision range was ~200–300. Per-device fee because ETA registers each POS serial; matches market practice of charging per terminal (Daftra 490–637 per offline terminal). Withdraw the brochure's "200 early" offer until it exists. |
| ETA e-invoice (B2B) | 149 / month (or 349 bundled with e-receipt) | add-on | add-on | included | Lower volume for most shops; wholesale tenants need it. |
| Loyalty points | 149 / month | add-on | **included** | included | Competitors give it free (Loyverse) or include it mid-tier (Rewaa Growth, Foodics Advanced); including it in Pro strengthens the "popular" plan. |
| Messaging credits (WhatsApp/SMS) | Packs: 500 msgs = 99, 2,000 = 349, 10,000 = 1,490 (prepaid, no expiry for 12 months) | all | all | all | Meta utility in Egypt ≈ $0.0036/msg (unofficial). Pack prices leave margin for BSP fees and FX moves; recompute at launch from the day's USD rate. Marketing messages (~$0.064) should be priced separately or excluded. |
| Full data export | free | — | — | — | Trust + PDPL; do not charge. |
| Assisted Excel import | keep 1,500 one-time; **free with any annual plan** | | | | Removes the main switching objection; cost is bounded once PRD-06 exists. |

### 6.3 Packaging tweaks (questions, not changes)

1. **Annual-first presentation**: show yearly price as the default on the pricing page (Rewaa and now Qoyod are annual-only: [Rewaa](https://rewaa.com/pricing), [Qoyod Apr 2026](https://academy.qoyod.com/en/releases/april-2026-mobile-app-update/)); keep monthly available (decision).
2. **Show prices "+14% VAT"** explicitly on every price label (decision says prices exclude VAT; the brochure does not say so).
3. **Hardware**: keep the decided bundles (4,900 / 6,800), but publish the exact models only after PRD-13/14 certification; offer the bundle in 3 or 6 instalments through Paymob (CloudPastry sells devices on instalments: unofficial reference in `docs/03-architecture/pricing-model-recommendation-2026-10.md`).
4. **Trial**: keep 14 days with Pro features (decision). Add an "activation" email/WhatsApp sequence on day 1, 3, 7, 12 (requires PRD-20 events).
5. **Do not launch a free-forever plan.** Loyverse is free because it monetises payments and add-ons globally; we have no payments revenue. The decision (trial, not free) is consistent with this.

### 6.4 Illustrative customer bills (decided prices + proposals above)

| Customer | Plan | Add-ons | Monthly ex-VAT | With 14% VAT |
|---|---|---|---|---|
| Single grocery, VAT-registered, needs e-receipt | Basic 449 | ETA 249 | 698 | 795.72 |
| 2-branch retailer + van, loyalty | Pro 899 | ETA 249 + extra device 79 | 1,227 | 1,398.78 |
| Same on founder pricing | Pro 599 | ETA 249 + 79 | 927 | 1,056.78 |

---

## 7. Decisions the CTO needs to make

| # | Question | Evidence | Recommendation |
|---|---|---|---|
| CTO-Q1 | Is F&B (coffee shops serving drinks) in scope? If yes, KDS, modifiers and Talabat move up; if no, the brochure and seeders must drop coffee wording. | Decision §1 says generic, not coffee; brief and brochure say coffee/roasters | Stay generic for launch; revisit F&B after 50 customers |
| CTO-Q2 | PDPL: who handles PDPC licensing/DPO, and is data allowed to sit on Hetzner (EU) and Google Drive (outside Egypt)? | PDPL regs in force; grace period ended ~1 Nov 2026; transfer licence needed for non-Egypt hosting ([CMS](https://cms.law/en/are/legal-updates/egypt-s-pdpl-executive-regulations-issued-one-year-compliance-countdown-begins)) | Get Egyptian counsel now; budget either a transfer licence or Egypt-hosted servers/backups before the first paid customer |
| CTO-Q3 | Pull ETA e-receipt forward from Phase 3? | Law 6/2025 ties the simplified SME regime to e-receipt compliance; Decision 281/2025 widened the mandate | Data model in Phase 1/2, pilot within 3 months of launch, GA as paid add-on |
| CTO-Q4 | Self-signup is Phase 3; accept an "assisted signup" form for launch? | All regional competitors offer no-card 14-day self-signup | Yes: assisted at launch, automatic in P1 |
| CTO-Q5 | Commercial brand name and domain before the website and Play Store listing | Decision W0: name undecided | Decide before PRD-07 |
| CTO-Q6 | Hide `quotations.manage` (and any unbuilt feature) from the public plan list? This changes the "18 core features" count | `FeatureCatalog.php` marks it public + core with no routes | Hide until built |
| CTO-Q7 | Will Android-based hardware bundles be sold at launch? | Android app has no printer plugin | If yes, PRD-13 becomes P0 |
| CTO-Q8 | Pull Paymob one-off checkout from Phase 3 into the first 3 months? | Manual verification does not scale; Paymob supports recurring tokenised billing | Yes for one-off checkout; recurring later |
| CTO-Q9 | Approve add-on prices in §6.2 (ETA 249 + 79/device, loyalty 149 and included in Pro, messaging packs) | §6.2 | — |
| CTO-Q10 | Unpublish or correct `/brochure` now? | It advertises e-invoice, 24/7 VIP support, real-time backup, roasters | Unpublish today (one-line route change by an engineer) |

---

## 8. Side observations (not product gaps, for the relevant owner)

- `CreateBlenderInvoiceAction` passes `payment_type: cash` with `paid_amount: 0.000`; whether `InvoiceService::confirmInvoice` treats this as fully paid needs a check by backend (money). It also builds hardcoded Arabic notes (legacy; ENTI-4.1 covers the rename).
- Telegram bot per platform with chat-id linking is decided; ensure the help center explains it, as it is a differentiator against Daftra/Foodics (none of them advertise Telegram).

---

## 9. Sources

Official / vendor:
- Daftra plans: https://www.daftra.com/en/plans · ETA integration: https://docs.daftra.com/en/?p=8766 · Excel import: https://docs.daftra.com/en/?p=8179 · POS guide: https://docs.daftra.com/en/user_manual/point-of-sales-guide-pos/
- Foodics Egypt pricing: https://www.foodics.com/foodics-pricing-egypt/ · Deliverect: https://www.foodics.com/portfolio/deliverect · Blend: https://help.foodics.com/hc/en-us/articles/20405475656348-Blend
- Rewaa pricing: https://rewaa.com/pricing · offline: https://intercom.help/rewaa/en/articles/8633718-offline-selling · offline + ZATCA: https://intercom.help/rewaa/en/articles/11426915-how-to-sell-offline-in-compliance-with-zatca
- Qoyod offline: https://www.qoyod.com/en/point-of-sale/offline-mode/ · April 2026 release: https://academy.qoyod.com/en/releases/april-2026-mobile-app-update/ · POS 2.8.3: https://www.qoyod.com/en/releases/pos-app-v2-8-3/ · printers release: https://www.qoyod.com/releases/pos-app-ux-printers/
- Loyverse pricing: https://loyverse.com/pricing · import: https://help.loyverse.com/help/importing-and-exporting · getting started: https://help.loyverse.com/help/gettings-starteds · modifiers: https://help.loyverse.com/help/how-set-and-apply-modifiers
- Odoo POS features: https://www.odoo.com/app/point-of-sale-features · preparation display: https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/preparation.html
- Square restaurants pricing: https://squareup.com/us/en/point-of-sale/restaurants/pricing · offline FAQ: https://square.com/help/us/en/article/7775-process-offline-payments-faq
- Zoho POS hardware: https://www.zoho.com/en-sa/pos/resources/help/hardware.html
- Paymob subscriptions (developer docs): https://developers.paymob.com/paymob-docs/payments-and-features/core-features/subscriptions
- ETA e-receipt API SDK: https://sdk.invoicing.eta.gov.eg/ereceiptapi/
- Meta WhatsApp pricing: https://developers.facebook.com/docs/whatsapp/pricing
- Dexef: https://dexef.com/apps/fastest-cashier-program (fetch failed; details unconfirmed)

Legal / tax (law-firm and press; not legal advice):
- KPMG on e-receipt expansion: https://kpmg.com/us/en/taxnewsflash/news/2025/07/tnf-egypt-new-taxpayers-required-to-comply-with-mandate-to-issue-electronic-receipts-for-b2c-transactions.html
- VATupdate: https://www.vatupdate.com/2025/08/09/egypt-expands-e-receipt-mandate-for-b2c-transactions-starting-september-2025
- ETA head on simplified regime: https://www.zawya.com/en/economy/north-africa/e-invoice-e-receipt-compliance-required-to-benefit-from-simplified-tax-system-egypts-eta-head-b4r659j9 · https://www.dailynewsegypt.com/?p=845942
- Law 6/2025 overview: https://www.mondaq.com/tax-authorities/1791346/new-tax-incentives-in-law-no-6-of-2025-for-small-businesses
- PDPL executive regulations: https://cms.law/en/are/legal-updates/egypt-s-pdpl-executive-regulations-issued-one-year-compliance-countdown-begins · https://www.tamimi.com/news/from-policy-to-practice-egypt-issues-executive-regulations-of-the-personal-data-protection-law · https://chambers.com/articles/a-first-look-at-egypt-s-personal-data-protection-executive-regulations · https://www.clydeco.com/en/insights/2026/01/egypt-regulatory-update-on-data-privacy

Unofficial (used only where marked):
- Wafeq e-receipt guide: https://www.wafeq.com/en-eg/tax-and-reporting/e-receipt-system
- Paymob fees comparison: https://paymentproviders.io/country/eg
- InstaPay merchant status: https://www.orchestrapay.com/coverage/africa/egypt
- Foodics × Paymob: https://yourstory.com/ys-gulf/foodics-paymob-partner-digital-transformation-egypt-f-and-b
- FawryPay API mirror / guide: https://apis.io/apis/fawry/fawry-payments-api/ · https://dev.to/_moehab/a-comprehensive-guide-to-integrate-fawrypay-api-into-your-own-restful-api-server-478d
- WhatsApp Egypt rates: https://chatmaxima.com/whatsapp-api-pricing/egypt/

Internal:
- `docs/01-overview/product-overview.md`, `docs/03-architecture/pricing-model-recommendation-2026-10.md`, `docs/05-planning/phase-1-plan.md` (§4.7, §4.12, §4.17, §8), `docs/02-requirements/tenant-settings-catalog.md`, `docs/reviews/2026-10-07-saas-analysis/09-product-docs.md`, `docs/04-ux-ui/pos-competitive-study.md`.
