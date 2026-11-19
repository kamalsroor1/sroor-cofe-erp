# SaaS Unit-Economics Benchmarks and Egypt Operating-Cost Inputs (for the Ibraa 3-year model)

Research date: 2026-11-19. Notes in English; final report will be Arabic.

**FX used throughout:** USD 1 = EGP 53.7 (midpoint of Central Bank of Egypt quote on 17 Nov 2026: buy 53.6753 / sell 53.7753) — [CBE exchange rates](https://www.cbe.org.eg/en/economic-research/statistics/exchange-rates). For context the USD traded at EGP 46.96 at end-Jan 2026 per [Arab Finance](https://www.facebook.com/ArabFinanceEn/posts/infographic-the-us-dollar-traded-at-egp-4696-at-the-end-of-january-2026-before-r/1522021843279425/), so USD-priced inputs moved ~14% in EGP terms within 2026; the model should carry an FX sensitivity.

**Reliability legend:** [P] = primary source (vendor price page, central bank, original benchmark report); [A] = aggregator/blog citing a primary report; [W] = weak (agency marketing, unattributed blog, or search-snippet only). Many figures below were read from search-result excerpts, not full pages; this is flagged where relevant.

**Context for interpretation:** Ibraa list prices (owner decision 2026-11-15) are 499 / 999 / 1,499 EGP per month ≈ USD 9.3 / 18.6 / 27.9. That puts most customers in the "ARPA under USD 25/month" bucket of global benchmarks, which is the highest-churn bucket.

---

## 1. SMB SaaS benchmarks (churn, conversion, NRR, CAC, LTV/CAC, gross margin, billing mix)

### Takeaway
For very small customers paying under ~USD 25/month, global data puts median monthly logo churn around 4–6% (ChartMogul: 6.1% for ARPA < USD 25), self-serve freemium converts 2–5% of free signups to paid, and no-card trials convert roughly 4–15%. Annual plans churn several times less than monthly ones and are usually bought with a ~15–20% discount. CAC payback for small-ACV SaaS is ~8–12 months median; a 2025 all-segment median is 16 months.

### Cited Findings

**Churn (logo, monthly)**
- ChartMogul data: median monthly customer churn 6.1% for businesses with ARPA under USD 25/month vs 2.2% for ARPA above USD 500/month — [Churnbuster citing ChartMogul 2022](https://churnbuster.io/articles/b2b-saas-churn-rate/) [A]
- Recurly: SaaS average monthly churn 3.22% total = 2.16% voluntary + 1.06% involuntary (failed payments) — [Recurly churn benchmarks](https://recurly.com/research/churn-rate-benchmarks/) [P, snippet]
- SMB monthly logo churn in the USD 500–5,000 ACV band ≈ 4.1% (≈39%/yr); median SMB gross revenue retention 70–82% — [CRV, 2026](https://www.crv.com/content/saas-churn-rate) [A]
- Seed-stage (<USD 1M ARR) companies: 3–5% monthly logo churn typical (30–45%/yr) — [CRV](https://www.crv.com/content/saas-churn-rate) [A]
- SMB (<50 employees) SaaS: 3–7% monthly; <2% is top-quartile — [Churnguard](https://blog.churnguard.fr/en/average-churn-rate/) [W]; same 3–7% range cited from Vitally by [Duda](https://blog.duda.co/top-smb-saas-kpis-and-their-unique-differences-from-enterprise-saas) [A]
- SMB median logo churn 4.2% monthly (~40% annual) — [Jumpstart Partners](https://jumpstartpartners.finance/blog/saas-churn-analysis-reduce-customer-churn-rate) [W]
- Optifai (939 companies): SMB 3–5% monthly, mid-market 1.5–3%, enterprise 1–2% — [Optifai](https://optif.ai/learn/questions/b2b-saas-churn-rate-benchmark/) [W]
- Conversion table: 3% monthly = 30.6% annual loss; 5% = 46.0%; 7% = 58.1% (formula 1−(1−m)^12) — [Customerscore](https://www.customerscore.io/blog/8-retention-metrics-plg-saas-should-look-at) [A]

**POS/retail-specific churn:** no published POS-specific SMB churn figure found (see Gaps).

**Freemium-to-paid and trial-to-paid conversion**
- ChartMogul Conversion Report (200 responses, conversion within 6 months): freemium with signup 3–5% good (P50) / 8–12% great (P75); free trial without card 4–6% / 10–15%; reverse trial 4–6% / 8–12%; free trial requiring card 25–35% / 50–60%; B2B overall 6–10% / 15–20%. Median across all products 8% — [ChartMogul SaaS Conversion Report](https://chartmogul.com/reports/saas-conversion-report/) [P]
- Lenny's Newsletter + OpenView study of 1,000+ products: self-serve freemium 3–5% good, 6–8% great — cited by [Fiscallion](https://www.fiscallion.io/blog/freemium-conversion-rate-saas) [A]
- First Page Sage 2025: freemium-to-paid 2.6% across all categories — cited by [Fiscallion](https://www.fiscallion.io/blog/freemium-conversion-rate-saas) [A]
- Freemium 2–5%; self-serve trials 10–25%; opt-out (card-required) trials 50–75% — [Appcues](https://www.appcues.com/blog/free-to-paid-conversion-strategies) [A]
- Opt-in trials 8–15%, card-required 25–40% (2026 re-cut) — [IdeaProof](https://ideaproof.io/versus/freemium-vs-paid-trial) [W]

**Net revenue retention / gross revenue retention**
- SaaS Capital 2025 (1,000+ private B2B SaaS > USD 1M ARR): median GRR 91%, median NRR 101% — [SaaS Capital retention benchmarks PDF](https://www.saas-capital.com/wp-content/uploads/2025/09/RB32WS1-2025-B2B-SaaS-Retention-Benchmarks.pdf) via [Churnbuster](https://churnbuster.io/articles/b2b-saas-churn-rate/) [A→P]
- ChartMogul (~2,700 B2B SaaS): median NRR 2025 = 82%, upper quartile 97% — [ChartMogul retention report](https://chartmogul.com/reports/saas-retention-the-ai-churn-wave/) via [Churnbuster](https://churnbuster.io/articles/b2b-saas-churn-rate/) [A]; note this conflicts with SaaS Capital's 101% (different samples: ChartMogul includes many small self-serve companies, closer to Ibraa's profile)
- SaaS Capital: NRR 100% month-to-month contracts, 101% annual, 103% multi-year — [Customerscore](https://www.customerscore.io/blog/8-retention-metrics-plg-saas-should-look-at) [A]
- KeyBanc/Sapphire survey: gross retention fell to 86% in 2023, expected to approach 90%, NRR above 100% — [KeyBanc press release 2025](https://investor.key.com/press-releases/news-details/2025/PRIVATE-SAAS-COMPANY-SURVEY-REVEALS-AI-DRIVEN-TRANSFORMATION-AND-SUSTAINED-OPERATIONAL-EXCELLENCE/default.aspx) via Churnbuster [A]
- Lighter Capital 2025: median annual revenue churn 12.5% for private B2B SaaS; top quartile < 5.48% — [Lighter Capital via CRV](https://www.lightercapital.com/blog/2025-b2b-saas-startup-benchmarks) [A]

**CAC payback, LTV/CAC**
- Benchmarkit (via Aleph): 2025 median CAC payback 16 months across 342 companies (18 months in 2024); top quartile ≤ 6 months — [Aleph](https://www.getaleph.com/answers/cac-payback-period-saas-2026) [A]
- By ACV (Benchmarkit): 11 months for < USD 5K ACV; 12 months USD 5–10K; 18 months USD 10–25K — [SaaSHero](https://www.saashero.net/strategy/enterprise-saas-cac-payback-standards/) [A]
- Horizontal SaaS 14 months vs vertical SaaS 18 months median payback — [ScaleXP](https://www.scalexp.com/blog/saas-benchmarks-cac-payback/) [A]
- SMB (< USD 15K ACV) payback 8–12 months — [Optifai](https://optif.ai/learn/questions/cac-payback-period-benchmark/) [W]
- Payback must be gross-margin adjusted; skipping this overstates efficiency by 20–40% — [SaaSHero](https://www.saashero.net/strategy/enterprise-saas-cac-payback-standards/) [A]
- Worked example: doubling monthly churn from 1.5% to 3.0% halves LTV — [CRV citing CloudZero](https://www.crv.com/content/saas-churn-rate) [A]

**Gross margin**
- Illustrative SaaS dashboards and payback examples use 75–80% gross margin (e.g. 78%) — [SaaSHero](https://www.saashero.net/strategy/enterprise-saas-cac-payback-standards/), [Optifai "80% typical"](https://optif.ai/learn/questions/cac-payback-period-benchmark/) [W — illustrative, not a survey median]

**Annual vs monthly billing**
- Monthly plans churned a median 4.7x faster than annual plans in the same product (range 2.5–60x), study of 44,000 SaaS users — [Customerscore](https://www.customerscore.io/blog/8-retention-metrics-plg-saas-should-look-at) [A]
- Common practice: 15–20% discount for annual vs monthly — [Optifai](https://optif.ai/learn/questions/b2b-saas-churn-rate-benchmark/) [W]

### Inferences
- Because Ibraa's ARPA is USD 9–28/month, the model's base monthly logo churn for monthly-billed paid shops should sit near the ChartMogul < USD 25 bucket: **base 5%, low (good) 3%, high 7%**. Annual-billed customers: using the 4.7x ratio, roughly 1–1.5%/month equivalent.
- Involuntary churn (failed card/wallet renewals) adds ~1 pt/month globally (Recurly); in Egypt, where many shops will pay by wallet/Fawry/cash without auto-debit, renewal friction is likely higher, so treat it explicitly.
- For a permanent free plan (owner decision 2026-11-18), use freemium conversion **2% low / 4% base / 8% high** of free signups converting within 6 months. Do not mix with trial benchmarks.
- NRR for a tiered SMB product with few expansion levers (branch/user add-ons): **85% low / 95% base / 105% high**. ChartMogul's 82% median is the closer analogue for small self-serve companies.
- CAC payback target for the model: ≤ 12 months base (sub-USD 5K ACV median ~11 months); LTV/CAC ≥ 3 is the conventional target (not sourced in this pass; see Gaps).

### Gaps
- No POS/retail-specific SMB churn number found (Square/Lightspeed/Loyverse do not publish logo churn for micro merchants).
- KeyBanc/KBCM full survey gross-margin median, OpenView 2023 product benchmarks and Bessemer figures were not fetched in full; gross margin range above is illustrative, not a survey median.
- No published source for the LTV/CAC ≥ 3 rule found in this pass (widely used rule of thumb).
- No MENA- or Egypt-specific churn/conversion data published by Foodics, Rewaa, Zid, Salla or Daftra found.

---

## 2. Go-to-market costs in Egypt (ads, resellers, salaries)

### Takeaway
Egypt is among the cheapest Meta ad markets in the world (CPM roughly USD 1–3, ≈ EGP 54–150), but B2B lead costs are much higher than CPM suggests. Local salaries are low in USD terms: an Arabic-only customer-service agent median ≈ EGP 5,550/month (2025), and a senior local full-stack developer ≈ EGP 27,000–72,000/month (median ~EGP 43,000). No sourced figure was found for software reseller commissions or field sales reps.

### Cited Findings

**Meta (Facebook/Instagram) ad costs in Egypt**
- Egypt CPM USD 1.00–2.80, +12% YoY (2026) — [Stackmatix, Facebook CPM by country 2026](https://www.stackmatix.com/blog/facebook-ads-cpm-by-country-2026) [A] → EGP 54–150 per 1,000 impressions
- Egypt Facebook CPM USD 1.81 (index 11 vs US) — [PaceAds Meta ads statistics 2026](https://paceads.com/research/meta-ads-statistics-2026) [A] → EGP 97
- Egypt ≈ USD 2.90 CPM and USD 0.25 CPC average — [Momen Hajaj, Meta ads cost Egypt 2026](https://momenhajaj.com/blog/meta-ads-cost-egypt/) [W, snippet] → CPC ≈ EGP 13.4
- Older platform-level data: Instagram CPM USD 0.69, CPC USD 0.08 in Egypt — [AdCostly](https://adcostly.com/facebook-ads-cost-in-egypt) [W, date unclear]
- Egyptian agency case claims: "B2B SaaS USD 6.25/lead"; "local services USD 1,500/month drove 240 leads/month" (≈ USD 6.25/lead) — [Eko Innovations](https://ekoinnovations.com/how-much-does-meta-ads-cost-egypt) [W — agency marketing] → EGP ≈ 336/lead
- Global reference: Facebook lead-campaign CPC USD 1.92, cost per lead USD 27.66 (LocaliQ/WordStream 2025), USD 27.39 in 2026 — [dfriends](https://www.dfriends.cz/en/blog/facebook-ads-cost), [Adwave](https://adwave.com/resources/facebook-ad-costs) [A]

**Reseller / agent commission (POS hardware dealers, accountants)**
- No sourced figure found.

**Salaries (Egypt, EGP/month)**
- Customer service, Cairo/Giza, no or basic English: median EGP 5,550/month in 2025 — [Wuzzuf](https://wuzzuf.net/careers/how-language-decides-customer-service-salary/) [P]
- Foreign-language (Spanish) CS roles: EGP 9,000–17,000/month — [TTEC jobs](https://www.ttecjobs.com/en/spanish-speaking-customer-service-jobs-in-egypt-requirements-and-salary) [A — not representative of Arabic support]
- Glassdoor Egypt CS figure (EGP 1,193/month) and Glassdoor Cairo Laravel developer figure (EGP 2,719/month) are implausibly low and look like data errors — [Glassdoor CS](https://www.glassdoor.com/Salaries/egypt-customer-service-representative-salary-SRCH_IL.0,5_IN69_KO6,37.htm), [Glassdoor Laravel](https://www.glassdoor.com/Salaries/cairo-egypt-php-laravel-developer-salary-SRCH_IL.0,11_IM1175_KO12,33.htm) — **do not use**
- Senior full-stack (5+ yrs): EGP 27,000–72,000 at local companies; EGP 55,000–107,000 at multinationals/funded startups; USD 2,000–4,500 remote; survey median EGP 43,000 (69 responses), P90 EGP 106,700 — [CVProMaker 2026](https://www.cvpromaker.com/salaries/egypt/full-stack-developer) [A]
- Software engineer median total comp EGP 45,134/month (400+ salaries) — [Levels.fyi Egypt](https://www.levels.fyi/t/software-engineer/locations/egypt) [P — skewed to larger tech employers]
- Senior software engineer median ≈ EGP 937,700/year (≈ EGP 78,000/month, USD 17,758); mid-level ≈ USD 9,809/yr (≈ EGP 43,900/month at 53.7) — [OctogleHire Q4 2026](https://www.octoglehire.com/rates/egypt) [A]

### Inferences
- Ad-driven lead cost for an SMB SaaS sign-up in Egypt: **low EGP 100, base EGP 300, high EGP 800** per lead (base anchored to the weak USD 6.25 agency claim; high reflects that B2B lead CPC is 2–3x traffic CPC globally). Should be validated with a small test campaign.
- Support agent (Arabic, Cairo): **EGP 6,000 / 9,000 / 13,000** per month gross (base above Wuzzuf median to allow for POS/technical skills); add ~20–25% employer cost (social insurance etc. — not sourced here).
- Mid/senior Laravel/Vue developer: **mid EGP 35,000–45,000; senior EGP 45,000 / 60,000 / 80,000** (low/base/high).
- Field sales rep: no source; a working assumption would be a base similar to support plus commission, but this must be marked as an unsourced assumption.

### Gaps
- No published reseller/dealer/accountant commission rate for software in Egypt (Foodics/Daftra partner programs do not publish rates in what was found).
- No sourced salary for a field sales rep in Egypt; Wuzzuf/Bayt/Forasna pages for sales were not retrieved.
- Egypt-specific B2B cost-per-lead from a neutral source not found.
- Employer social-insurance load not researched.

---

## 3. Infrastructure costs (hosting, backups, email, messaging)

### Takeaway
Infrastructure is a small cost line. Hetzner cloud servers run from ~EUR 5.49 to ~EUR 238/month (excl. VAT) but Hetzner raised prices sharply in 2026 for some lines. Transactional email costs about USD 0.10 per 1,000 (Amazon SES); WhatsApp utility/OTP messages to Egyptian numbers cost about USD 0.0036 (EGP 0.19) and marketing messages USD 0.0644 (EGP 3.46).

### Cited Findings
- Hetzner Cloud "regular performance" plans listed from EUR 5.49 to EUR 238.49 per month excl. VAT — [Hetzner](https://www.hetzner.com/cloud/regular-performance/) [P, snippet]
- 2026 Hetzner price increase: CPX line 2.4–2.75x in Germany/Finland (CPX32 EUR 13.99 → 35.49); CX/CAX 1.3–1.4x (CAX31 EUR 15.99 → 20.99) — [Northflank](https://northflank.com/blog/hetzner-cloud-server-price-increases) [A]; user report "CPX31 from USD 25/mo to USD 74/mo" — [Reddit r/hetzner](https://www.reddit.com/r/hetzner/comments/1u6n5gf/from_25mo_to_74mo_for_a_cpx31/) [W]
- Hetzner announced a 15% price reduction at Helsinki (HEL1) from 10 Nov 2026 — [Hetzner pressroom](https://www.hetzner.com/pressroom/) [P, snippet]
- Amazon SES: USD 0.10 per 1,000 outbound emails (à la carte), USD 0.12/GB attachments; plan tiers USD 0.16–0.23 per 1,000 — [AWS SES pricing](https://aws.amazon.com/ses/pricing/) [P]
- Brevo: free plan 300 emails/day; paid from USD 9/month for 5,000 emails; Business USD 18/month (5,000) or USD 65/month (20,000) — [Mailblast](https://www.mailblast.io/blog/comparisons/brevo-pricing-explained) [A]; [Brevo blog](https://www.brevo.com/blog/amazon-ses-alternatives/) [P]
- WhatsApp Business Platform Egypt rates from 1 Oct 2026: marketing USD 0.0644/message; utility, authentication and service USD 0.0036; 1,000 free service messages/month per number — [GrowAxis](https://www.growaxis.net/insights/whatsapp-business-api-providers-egypt-2026) [A]; consistent with EGP 3.36 marketing / EGP 0.19 utility per [Modeer360](https://modeer360.com/en/blog/whatsapp-business-egypt) [A]; Meta lowered Egypt utility/auth rates on 1 Oct 2025 and Feb 2025 — [Meta for Developers](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing) [P]
- BSP platform fees on top of Meta's rate: e.g. LetsBot base USD 6.35/month; Unifonic USD 499–999/month — [GrowAxis](https://www.growaxis.net/insights/whatsapp-business-api-providers-egypt-2026) [A]

### Inferences
- Tenants per server (DB-per-tenant MySQL, small shops): no benchmark found. Engineering estimate only: a 4–8 vCPU / 16–32 GB server could host roughly 100–300 low-traffic tenant DBs; model **infra cost per paying tenant EGP 15 / 30 / 60 per month** including backups, email and OTP — to be validated by load testing.
- OTP for OTP self-signup via WhatsApp: ≈ EGP 0.19 per OTP; 1,000 signups/month ≈ EGP 200 — negligible.
- Transactional email at 50,000/month on SES ≈ USD 5 ≈ EGP 270/month.

### Gaps
- Backblaze B2 / S3 / Google Drive backup pricing not retrieved in this pass.
- DigitalOcean/AWS EC2/RDS and Egypt-local hosting providers' pricing not retrieved.
- SMS price per message in Egypt not found.
- No published tenants-per-server benchmark for DB-per-tenant Laravel.

---

## 4. Payment collection costs in Egypt

### Takeaway
Egyptian PSPs charge roughly 2.5–3.5% per card payment; Paymob is commonly quoted at 2.75% + EGP 2–3 per transaction with no setup or recurring fee. Official current Paymob/Fawry fee pages were not retrievable, so these are secondary-source figures.

### Cited Findings
- Paymob: no setup fee, no recurring fee, 2.75% + EGP 3 per transaction — [Naos Solutions](https://naos-solutions.com/online-payment-gateways-in-egypt/) [A]; conflicting "2.75% + EGP 2", covering cards, wallets and Fawry machines — [Facebook answers page](https://www.facebook.com/fb-answers/best-payment-gateway-egypt/) [W]
- Most Egyptian PSPs charge ~2.5–3.5% for card payments — [PayAtlas Egypt](https://payatlas.com/countries/egypt-eg) [A]
- Fawry provides cash payment at a large kiosk network for 250+ services — [Wise](https://wise.com/gb/hub/payment-methods/egypt) [A]
- paymob.com/en/pricing returned 404 on 2026-11-19 — [Paymob](https://paymob.com/en/pricing) [P — no data]

### Inferences
- Model payment cost: **low 2.5%, base 2.75% + EGP 3, high 3.5% + EGP 3** of collected revenue. On a 499 EGP monthly plan, base fee ≈ EGP 16.7 (3.3%); on annual prepayment of ~5,000 EGP, ≈ EGP 140.5 (2.8%) — another argument for annual billing.
- Recurring card tokenization exists at Egyptian PSPs but most micro-shops will pay via wallet/Fawry/InstaPay, which are pull-not-push: expect higher involuntary churn than Recurly's 1.06%.

### Gaps
- Official Paymob, Fawry (FawryPay), Kashier, Geidea fee schedules not retrieved; fees for mobile wallets and InstaPay acceptance not confirmed.
- Whether Paymob supports card-on-file recurring subscriptions for small merchants, and its fee, not confirmed.

---

## 5. Valuation context (multiples, MENA POS/retail SaaS rounds)

### Takeaway
Public SaaS traded around 7x run-rate revenue in early 2025; private SaaS typically 3–7x ARR, with sub-USD 1M ARR businesses usually valued on earnings (2–4x SDE) rather than ARR. MENA retail/POS SaaS is investable at scale: Rewaa closed a USD 45M Series B (Dec 2025); Foodics has raised ~USD 198M over 4 rounds.

### Cited Findings
- SaaS Capital Index median 7.0x run-rate revenue at start of 2025; expected band 5.5–8.0x — [SaaS Capital](https://www.saas-capital.com/blog-posts/private-saas-company-valuations-multiples/) [P]
- Private SaaS 3–7x ARR in 2025 while SCI ~6.7x — [L40](https://www.l40.com/insights/saas-multiples) [A]; Flippa 2025: private SaaS 3–10x ARR — via [Axial](https://www.axial.net/forum/saas-multiples/) [A]
- Under USD 1M ARR, owner-operated SaaS valued at 2–4x SDE, not ARR — [Ad Astra Equity](https://www.adastraequity.com/ebitda-multiples/saas-company) [W]
- Carta: SaaS Series A median pre-money ≈ USD 60M in Q3 2025, +19% YoY — [CRV citing Carta](https://www.crv.com/content/saas-churn-rate) [A]
- Rewaa (Saudi cloud POS + accounting for retail): USD 45M Series B, Dec 2025, led by Wa'ed Ventures, Idrisi Ventures, Unity Ventures; prior USD 27M Series A in 2023; valuation and ARR not disclosed — [Wamda](https://www.wamda.com/2025/12/saudi-arabias-rewaa-closes-45-million-series-b) [P]
- Foodics (Saudi cloud POS for restaurants): ~USD 198M raised across 4 rounds — [Multiples.vc](https://multiples.vc/private-comps/foodics) [A]; Series B USD 20M led by Sanabil (total then USD 28M) — [Foodics press](https://www.foodics.com/press/startup-foodics-raises-us20-million-sar75mil-in-series-b-funding-round-led-by-sanabil-investments/) [P]; early SAR 15M (USD 4M) round led by Raed Ventures — [Raed Ventures](https://raed.vc/news/raed-ventures-lef-seed-round-of-foodics/) [P]
- Foodics "continuously delivered 40–50% growth, 2025 no exception"; eyeing M&A and fresh capital in 2026 — [Kristie Neo Substack](https://kristieneo.substack.com/p/foodics-eyes-m-and-a-fresh-capital) [A]

### Inferences
- For an Egyptian early-stage SMB SaaS with EGP revenue (currency risk) and high SMB churn, a realistic exit/round multiple is at the lower end: **2x low / 4x base / 6x ARR high**; below ~USD 1M ARR, an earnings-based valuation is more likely.

### Gaps
- No MAGNiTT or Wamda figure for MENA SaaS revenue multiples was retrieved; no disclosed valuation for Rewaa or Foodics' latest rounds.
- No Egyptian POS SaaS funding data (e.g. Daftra, local players) found.

---

## Summary table

FX: USD 1 = EGP 53.7 (CBE, 17 Nov 2026). "Est." = analyst inference from cited ranges, not a published figure.

| Model input | Low | Base | High | Source |
|---|---|---|---|---|
| Monthly logo churn, monthly-billed paid shop (ARPA < USD 25) | 3% | 5% | 7% | [ChartMogul via Churnbuster](https://churnbuster.io/articles/b2b-saas-churn-rate/) (6.1% median <USD 25 ARPA); [CRV](https://www.crv.com/content/saas-churn-rate); [Recurly](https://recurly.com/research/churn-rate-benchmarks/) — est. |
| Monthly churn, annual-billed shop (equiv.) | 0.7% | 1.1% | 1.5% | Est. from 4.7x ratio, [Customerscore](https://www.customerscore.io/blog/8-retention-metrics-plg-saas-should-look-at) |
| Involuntary churn share | 1.0 pt | 1.5 pt | 2.5 pt | [Recurly](https://recurly.com/research/churn-rate-benchmarks/) 1.06% global — Egypt uplift est. |
| Free-to-paid conversion (within 6 months) | 2% | 4% | 8% | [ChartMogul](https://chartmogul.com/reports/saas-conversion-report/); [Fiscallion/OpenView](https://www.fiscallion.io/blog/freemium-conversion-rate-saas) |
| Trial-to-paid (no card) | 4% | 8% | 15% | [ChartMogul](https://chartmogul.com/reports/saas-conversion-report/); [IdeaProof](https://ideaproof.io/versus/freemium-vs-paid-trial) |
| Net revenue retention (annual) | 82% | 95% | 101% | [ChartMogul 82%](https://chartmogul.com/reports/saas-retention-the-ai-churn-wave/); [SaaS Capital 101%](https://www.saas-capital.com/wp-content/uploads/2025/09/RB32WS1-2025-B2B-SaaS-Retention-Benchmarks.pdf) — base est. |
| CAC payback (months, GM-adjusted) | 6 | 11 | 16 | [Aleph/Benchmarkit](https://www.getaleph.com/answers/cac-payback-period-saas-2026); [SaaSHero](https://www.saashero.net/strategy/enterprise-saas-cac-payback-standards/) |
| LTV/CAC target | 3 | 3 | 5 | Rule of thumb — not sourced in this pass |
| Gross margin | 70% | 78% | 85% | Illustrative, [SaaSHero](https://www.saashero.net/strategy/enterprise-saas-cac-payback-standards/), [Optifai](https://optif.ai/learn/questions/cac-payback-period-benchmark/) — weak |
| Annual-plan discount | 15% | 17% | 20% | [Optifai](https://optif.ai/learn/questions/b2b-saas-churn-rate-benchmark/) — weak |
| Meta CPM Egypt (EGP / 1,000 impressions) | 54 (USD 1.00) | 97 (USD 1.81) | 150 (USD 2.80) | [Stackmatix](https://www.stackmatix.com/blog/facebook-ads-cpm-by-country-2026); [PaceAds](https://paceads.com/research/meta-ads-statistics-2026) |
| Meta CPC Egypt (EGP) | 4.3 (USD 0.08) | 13.4 (USD 0.25) | 27 (USD 0.50, est.) | [Momen Hajaj](https://momenhajaj.com/blog/meta-ads-cost-egypt/); [AdCostly](https://adcostly.com/facebook-ads-cost-in-egypt) — weak |
| Cost per lead, SMB B2B, Egypt (EGP) | 100 | 336 (USD 6.25) | 800 | [Eko Innovations](https://ekoinnovations.com/how-much-does-meta-ads-cost-egypt) — weak; low/high est. |
| Reseller/agent commission | — | — | — | Not found (gap) |
| Support agent salary (EGP/month gross) | 5,550 | 9,000 | 13,000 | [Wuzzuf](https://wuzzuf.net/careers/how-language-decides-customer-service-salary/) median 5,550; base/high est. |
| Field sales rep salary (EGP/month) | — | — | — | Not found (gap) |
| Senior Laravel/Vue developer (EGP/month) | 27,000 | 45,000 | 78,000 | [CVProMaker](https://www.cvpromaker.com/salaries/egypt/full-stack-developer); [Levels.fyi](https://www.levels.fyi/t/software-engineer/locations/egypt); [OctogleHire](https://www.octoglehire.com/rates/egypt) |
| Cloud server (EUR/month, one node) | 5.49 | 20.99 | 35.49 | [Hetzner](https://www.hetzner.com/cloud/regular-performance/); [Northflank](https://northflank.com/blog/hetzner-cloud-server-price-increases) |
| Infra cost per paying tenant (EGP/month) | 15 | 30 | 60 | Est. — no benchmark found |
| Transactional email (USD per 1,000) | 0.10 (EGP 5.4) | 0.16 (EGP 8.6) | Brevo USD 18 per 5,000 (EGP 193 per 1,000) | [AWS SES](https://aws.amazon.com/ses/pricing/); [Mailblast](https://www.mailblast.io/blog/comparisons/brevo-pricing-explained) |
| WhatsApp utility/OTP message (EGP) | 0.19 | 0.19 | 0.25 (est. FX drift) | [GrowAxis](https://www.growaxis.net/insights/whatsapp-business-api-providers-egypt-2026) USD 0.0036 |
| WhatsApp marketing message (EGP) | 3.36 | 3.46 | 3.8 (est.) | [GrowAxis](https://www.growaxis.net/insights/whatsapp-business-api-providers-egypt-2026); [Modeer360](https://modeer360.com/en/blog/whatsapp-business-egypt) |
| Payment collection fee | 2.5% | 2.75% + EGP 3 | 3.5% + EGP 3 | [Naos](https://naos-solutions.com/online-payment-gateways-in-egypt/); [PayAtlas](https://payatlas.com/countries/egypt-eg) |
| Valuation multiple (x ARR) | 2x | 4x | 7x | [SaaS Capital](https://www.saas-capital.com/blog-posts/private-saas-company-valuations-multiples/); [L40](https://www.l40.com/insights/saas-multiples); [Ad Astra](https://www.adastraequity.com/ebitda-multiples/saas-company) |
| FX USD→EGP | 47 (Jan 2026) | 53.7 (17 Nov 2026) | 60 (stress, est.) | [CBE](https://www.cbe.org.eg/en/economic-research/statistics/exchange-rates); [Arab Finance](https://www.facebook.com/ArabFinanceEn/posts/infographic-the-us-dollar-traded-at-egp-4696-at-the-end-of-january-2026-before-r/1522021843279425/) |
