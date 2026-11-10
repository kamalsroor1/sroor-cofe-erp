> ⚠️ **DRAFT — Requires Legal Review Before Publication / مسودة — تحتاج مراجعة قانونية قبل النشر**

# Privacy Policy & Personal Data Protection Notice — Sroor ERP

**Version:** `2026-11-draft-v1`  
**Effective Date:** `{{EFFECTIVE_DATE}}`

---

## 1. Introduction and Legal Framework

`{{COMPANY_NAME}}` ("Company" or "Sroor"), with its registered legal address at `{{COMPANY_LEGAL_ADDRESS}}`, Commercial Register No. `{{COMMERCIAL_REGISTER_NO}}`, Tax Registration No. `{{TAX_REGISTRATION_NO}}`, is committed to protecting personal data in accordance with the **Egyptian Personal Data Protection Law No. 151 of 2020**, its executive regulations, and industry-standard security controls.

This Privacy Policy explains what data we collect, why we process it, how long we retain it, the rights of data subjects, and our hosting and security safeguards.

---

## 2. Categories of Data Collected and Processed

The Platform processes three distinct categories of data:

### 2.1 Shop Registration & Account Data (Central Database)
- Business name, business type, and preferred workspace subdomain.
- Contact person name, phone number, email address, and selected subscription plan.
- Verifiable legal consent metadata: acceptance timestamp (`terms_accepted_at`), document version (`terms_version`), IP address (`ip`), and user-agent string for security and abuse prevention.
- Platform subscription and billing records.

### 2.2 Shop Staff & User Accounts (Tenant Database)
- Full names, login emails/usernames, hashed passwords, assigned roles/permissions, and branch assignments.
- Shift logs, operational activity logs, and accounting entries within the Shop's Workspace.

### 2.3 Shop Customers & Suppliers Data (Controlled by the Shop)
- Customer and supplier names, phone numbers, addresses, account balances, and sales/purchase/return invoices recorded by the Shop.
- For this category, the Shop acts as the **Data Controller** and Sroor acts strictly as the **Data Processor** on the Shop's behalf.

---

## 3. Purposes of Processing

Personal data is processed strictly for the following legitimate purposes:
1. Provisioning and operating isolated multi-tenant workspaces, POS, inventory, and accounting features.
2. Communicating with applicants and subscribers for onboarding, technical support, and security or billing notifications.
3. Securing the Platform, preventing fraud, enforcing rate limits, and maintaining immutable audit trails.
4. Complying with applicable legal, tax, and regulatory obligations in the Arab Republic of Egypt.

The Company never sells personal data or shares it with third parties for third-party marketing.

---

## 4. Data Retention, Encrypted Backups, and Deletion (Lifecycle-Bound)

Our retention schedule directly reflects the technical lifecycle policy (`config/tenant_lifecycle.php`) and backup policy (`docs/07-operations/backup-restore.md`):

1. **Active Subscription & Trial (`14` days):** Workspace data remains fully active and accessible to the Shop.
2. **Grace Period & Read-Only Mode:** Upon expiration without renewal, a `3`-day grace period applies, followed by `30` days in **Read-Only Mode** so the Shop can inspect and export its records.
3. **Retention Window Before Archiving (`90` days):** After suspension or cancellation, Workspace data is retained for `90` days to permit reactivation or export, after which the tenant transitions to `Archived` status with a verified encrypted `final` backup snapshot.
4. **Encrypted Backups:** All tenant database backups are encrypted using `AES-256` with `SHA-256` integrity verification, retained on a rolling schedule of `7` daily, `4` weekly, and `3` monthly snapshots.
5. **Final Purge:** Permanent tenant database deletion (`tenants:purge`) is only eligible after at least `30` days in archived status (or `90` days suspended/cancelled), accompanied by a `pre_purge` encrypted archive and an immutable central audit log entry.

---

## 5. Data Subject Rights (Egyptian Law No. 151 of 2020)

Under Egyptian Personal Data Protection Law No. 151 of 2020, data subjects have the right to:
- **Know and Access:** Be informed about and access their personal data held by the Controller or Processor.
- **Rectification:** Request correction or updating of inaccurate or incomplete personal data.
- **Withdraw Consent:** Withdraw prior consent to processing where applicable, subject to ongoing contractual or statutory obligations.
- **Erasure and Restriction:** Request deletion or restriction of processing once the purpose of collection or statutory retention period has ended.

> **Controller Notice:** For end-customer or supplier data stored inside a Shop's Workspace, requests should be directed to the respective Shop as the **Data Controller**. The Platform provides the Shop with tools to fulfill such requests.

---

## 6. Hosting and Cross-Border Data Transfer

Platform servers, tenant databases, and encrypted backups are hosted in secure cloud data centers located in `{{HOSTING_REGION}}`, protected by encryption in transit (`TLS/HTTPS`) and encryption at rest (`AES-256`), in compliance with the technical and organizational safeguards required under Egyptian Law No. 151 of 2020.

---

## 7. Contact Information & Data Protection Officer (DPO)

For privacy inquiries or to exercise your statutory rights, please contact our Data Protection Officer:
- **Legal Entity:** `{{COMPANY_NAME}}`
- **Registered Address:** `{{COMPANY_LEGAL_ADDRESS}}`
- **Data Protection Officer (DPO) Email:** `{{DPO_EMAIL}}`
- **Support Email:** `{{SUPPORT_EMAIL}}`
- **Phone / WhatsApp:** `{{SUPPORT_PHONE}}`
