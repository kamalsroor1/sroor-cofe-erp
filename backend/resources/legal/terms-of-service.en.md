> ⚠️ **DRAFT — Requires Legal Review Before Publication / مسودة — تحتاج مراجعة قانونية قبل النشر**

# Terms of Service & Subscription Agreement — Sroor ERP

**Version:** `{{LEGAL_VERSION}}`  
**Effective Date:** `{{EFFECTIVE_DATE}}`

---

## 1. Parties and Definitions

This Terms of Service Agreement ("Terms") is entered into between:
1. **Platform Provider ("Company" or "Sroor"):** `{{COMPANY_NAME}}`, with its registered legal address at `{{COMPANY_LEGAL_ADDRESS}}`, Commercial Register No. `{{COMMERCIAL_REGISTER_NO}}`, Tax Registration No. `{{TAX_REGISTRATION_NO}}`.
2. **Subscriber ("Shop", "Tenant", or "Customer"):** The commercial entity or individual applying for a subscription or using the Platform.

For the purposes of these Terms:
- **"Platform":** The Sroor cloud ERP and POS software for managing sales, inventory, invoicing, treasury, and branches across web, desktop, and mobile applications.
- **"Workspace":** The isolated multi-tenant environment and dedicated database provisioned for the Subscriber.
- **"Subscriber Data":** All items, invoices, stock movements, customer and supplier records, and user accounts entered by the Subscriber or its staff within the Workspace.

---

## 2. Scope of Service and License

2.1 The Company grants the Subscriber a limited, non-exclusive, non-transferable, non-sublicensable license to use the Platform for its internal business operations during an active subscription term, subject to the limits of the selected plan (branches, warehouses, users, items, and monthly invoices).  
2.2 The Company retains all intellectual property rights in the Platform's source code, interfaces, trademarks, and central databases. Nothing in these Terms transfers any intellectual property ownership to the Subscriber.

---

## 3. Registration, Activation, and Trial Period

3.1 Subscription requests are submitted electronically and require explicit acceptance of these Terms, the Privacy Policy, and the Data Processing Agreement (DPA).  
3.2 Consent metadata (timestamp of acceptance, legal document version identifier, and IP address) is recorded in the central database as verifiable evidence of electronic contracting.  
3.3 Any applicable free trial period (defaulting to `14` days per platform configuration unless specified otherwise in the selected plan) starts upon Workspace provisioning.

---

## 4. Fees, Billing, and Taxes

4.1 Subscription fees (monthly or annual) are billed in advance in the currency displayed on the pricing page (Egyptian Pounds `EGP` unless stated otherwise).  
4.2 All listed prices exclude Value Added Tax (VAT) and applicable government taxes unless explicitly stated otherwise.  
4.3 If a subscription expires without renewal payment, the Platform provides a `3`-day grace period, followed by a `30`-day **Read-Only Mode** allowing the Subscriber to view reports and export its data before the Workspace is suspended.

---

## 5. Subscriber Obligations and Acceptable Use

5.1 The Subscriber is responsible for the accuracy of registration details and for safeguarding user credentials and access tokens.  
5.2 The Subscriber bears sole legal, accounting, and tax responsibility for the accuracy of invoices, prices, taxes, and balances recorded in the Platform. The Company is not a party to any commercial transactions between the Subscriber and its customers or suppliers.  
5.3 The Subscriber must not use the Platform for any unlawful activity under the laws of the Arab Republic of Egypt, attempt to bypass tenant isolation, reverse-engineer the Platform, or submit abusive automated traffic.

---

## 6. Data Ownership, Backups, and Account Lifecycle

6.1 **Data Ownership:** All Subscriber Data remains the exclusive property of the Subscriber.  
6.2 **Encrypted Backups:** The Platform performs automated encrypted backups (`AES-256`) of tenant databases according to the operational retention policy (`7` daily, `4` weekly, and `3` monthly snapshots).  
6.3 **Retention, Archiving, and Final Purge:**
- Following the `30`-day read-only period and account suspension or cancellation, the Platform retains Workspace data for `90` days to allow account reactivation or data export.
- After the retention window expires, the Workspace is archived with a verified encrypted final snapshot (`final` / `pre_purge`).
- Permanent database deletion (purge) is executed only after at least `30` days in archived status (or `90` days suspended/cancelled), leaving an immutable central audit record.

---

## 7. Service Availability and Limitation of Liability

7.1 The Company exercises reasonable professional care to maintain Platform availability and security; however, the service is provided on an "AS IS" basis without warranty against interruptions caused by scheduled maintenance, hosting providers, or telecommunications failures.  
7.2 To the maximum extent permitted by law, the Company's aggregate liability arising out of or relating to these Terms shall not exceed the total subscription fees actually paid by the Subscriber during the three (`3`) months immediately preceding the event giving rise to liability.

---

## 8. Amendments, Governing Law, and Contact Information

8.1 The Company may update these Terms with reasonable prior notice to Subscribers. Continued use of the Platform or subscription renewal constitutes acceptance of the updated version.  
8.2 These Terms are governed by and construed in accordance with the laws of the Arab Republic of Egypt, and the competent courts at the Company's registered seat shall have exclusive jurisdiction.  
8.3 Legal and Support Contact Details:
- **Support Email:** `{{SUPPORT_EMAIL}}`
- **Data Protection Officer (DPO):** `{{DPO_EMAIL}}`
- **Phone / WhatsApp:** `{{SUPPORT_PHONE}}`
