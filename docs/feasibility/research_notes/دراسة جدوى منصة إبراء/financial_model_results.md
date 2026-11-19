# Financial model results (from ibraa-financial-model.xlsx, run 19 Nov 2026)

Source file: docs/feasibility/ibraa-financial-model.xlsx
(sheets: الملخص، تحليل الحساسية، الافتراضات، التوقعات الشهرية). All EGP, monthly, excl. VAT, 36 months from launch.
Model built by the coordinator from the research notes; every input carries its source in the assumptions sheet.

## Key assumptions (low / base / high)
- New sign-ups month 1: 80 / 150 / 250, growing 4% / 6% / 8% per month, capped at 800 / 1,500 / 2,500 per month.
- Sign-up → paid conversion: 2% / 4% / 8% (ChartMogul/OpenView freemium 2–5%).
- Paid customers from dealers/direct sales: 0 / 5 / 10 in month 1, growing 3% / 5% / 7%; dealer commission 3 / 2 / 2 months of subscription.
- Monthly paid churn: 7% / 5% / 3% (ChartMogul median 6.1% for < $25/month).
- Free shops monthly churn/dormancy: 12% / 10% / 8%.
- Prices 499 / 999 / 1,499; mix basic/pro/enterprise 70-25-5 / 60-30-10 / 50-35-15; annual billing share 20% / 30% / 40% with 16.7% discount; founder price factor 0.70 months 1–6, 0.86 months 7–12.
- Payment fees 3%; hosting 25 / 15 / 10 EGP per active shop (min 4,000 / 3,000 / 3,000 per month).
- Cost per sign-up from ads: 500 / 336 / 200 EGP (only source: agency claim ~$6.25 per lead in Egypt, weak).
- Fixed monthly: development team 90,000; marketing/sales 15,000; support 8,000 per agent per 150/200/250 paying shops (min 1); admin 12,000/10,000/10,000; data-protection officer & compliance 8,000/6,000/5,000.
- One-time (month 1): company setup 60k/50k/40k; e-receipt + VAT integration 250k/200k/150k; legal docs + data-protection licence 70k/50k/40k.
- Valuation multiple on ARR: 2x / 3x / 4x.

## Results by scenario

| Metric | Conservative | Base | Optimistic |
|---|---|---|---|
| Sign-ups Y1 / Y2 / Y3 | 1,202 / 1,925 / 3,081 | 2,530 / 5,092 / 10,246 | 4,744 / 11,947 / 26,630 |
| Paying shops end Y1 / Y2 / Y3 | 17 / 35 / 59 | 143 / 352 / 717 | 488 / 1,525 / 3,686 |
| Active free shops end Y3 | 2,006 | 7,312 | 20,017 |
| MRR end Y3 (EGP) | 38,195 | 510,477 | 2,834,193 |
| Revenue Y1 / Y2 / Y3 (EGP) | 61k / 206k / 367k | 523k / 2.11M / 4.54M | 1.80M / 9.04M / 24.13M |
| Total costs Y1 / Y2 / Y3 | 2.70M / 2.86M / 3.65M | 2.97M / 4.10M / 6.77M | 3.25M / 5.97M / 12.03M |
| Net Y1 / Y2 / Y3 | -2.64M / -2.66M / -3.28M | -2.45M / -1.99M / -2.23M | -1.45M / +3.08M / +12.09M |
| Cumulative cash end Y3 | -8.58M | -6.67M | +13.73M |
| First month with positive net | none within 36 | none within 36 | month 13 |
| Funding needed (lowest cumulative cash) | 8.58M | 6.67M | 1.45M |
| ARPU after founder pricing ends | 651 | 711 | 769 |
| Expected customer life (months) | 14 | 20 | 33 |
| LTV (EGP) | 9,028 | 13,803 | 24,863 |
| CAC via ads (EGP) | 25,000 | 8,400 | 2,500 |
| LTV/CAC | 0.4x | 1.6x | 9.9x |
| CAC payback (months) | 38 | 12 | 3 |
| ARR end Y3 | 0.46M | 6.13M | 34.0M |
| Indicative value end Y3 (ARR × multiple) | 0.9M | 18.4M | 136M |

Note: ARR × multiple is an upper bound; below ~$1M ARR buyers usually value on profit.

## Sensitivity (base scenario, one change at a time)

| Change | Paying shops end Y3 | Revenue Y3 | Net Y3 | First positive month | Funding needed | LTV/CAC |
|---|---|---|---|---|---|---|
| None (base) | 717 | 4.54M | -2.23M | none | 6.67M | 1.6x |
| Conversion 6% instead of 4% | 935 | 5.89M | -0.99M | none | 4.75M | 2.5x |
| Cost per sign-up 150 instead of 336 | 717 | 4.54M | -0.32M | 35 | 3.35M | 3.7x |
| Churn 3% instead of 5% | 862 | 5.39M | -1.47M | none | 5.61M | 2.7x |
| Dev team 45k instead of 90k | 717 | 4.54M | -1.69M | none | 5.05M | 1.6x |
| 15 dealer customers in month 1 instead of 5 | 1,281 | 8.19M | +0.30M | 28 | 3.04M | 1.6x |
| Combined plan: conversion 6%, sign-up cost 200, churn 4%, team 45k, 10 dealer customers | 1,331 | 8.40M | +2.82M | 15 | 1.30M | 5.2x |

## Coordinator's reading
- Paid ads alone do not pay back at base assumptions (LTV/CAC 1.6x < 3x rule of thumb). The business only works with cheap acquisition: dealers/accountants, referrals, organic, conversion > 5%.
- Break-even needs roughly 200 paying shops just to cover fixed costs (≈132k EGP/month fixed ÷ ≈675 EGP contribution per paying shop), before any marketing spend.
- The biggest single lever is the dealer/direct channel; the second is cost per sign-up.
