> ⚠️ **DRAFT — Requires Legal Review Before Publication / مسودة — تحتاج مراجعة قانونية قبل النشر**

# Data Processing Agreement (DPA) — Sroor ERP

**Version:** `{{LEGAL_VERSION}}`  
**Effective Date:** `{{EFFECTIVE_DATE}}`

---

## 1. Parties and Legal Roles

This Data Processing Agreement ("DPA") forms an integral part of the Terms of Service between:
1. **Data Controller ("Shop" or "Subscriber"):** The subscribing business entity that determines the purposes and means of processing personal data relating to its customers, suppliers, and staff within its Workspace.
2. **Data Processor ("Platform" or "Company"):** `{{COMPANY_NAME}}`, with its registered legal address at `{{COMPANY_LEGAL_ADDRESS}}`, Commercial Register No. `{{COMMERCIAL_REGISTER_NO}}`, Tax Registration No. `{{TAX_REGISTRATION_NO}}`, which processes personal data on behalf of the Data Controller and in accordance with its documented instructions.

This DPA is governed by the **Egyptian Personal Data Protection Law No. 151 of 2020** and its executive regulations.

---

## 2. Scope, Nature, and Purpose of Processing

2.1 **Subject Matter:** Provision of an isolated multi-tenant cloud ERP/POS environment for storing and processing sales, inventory, invoicing, and customer/supplier/user records of the Data Controller.  
2.2 **Duration of Processing:** For the duration of the active subscription, plus the grace period (`3` days), read-only window (`30` days), and pre-archive retention period (`90` days) defined in the Terms of Service and `config/tenant_lifecycle.php`.  
2.3 **Categories of Data Subjects:** Shop staff/users, Shop customers, and Shop suppliers.  
2.4 **Types of Personal Data:** Names, phone numbers, email addresses, physical addresses, account balances, and invoice/transaction histories.

---

## 3. Obligations of the Data Controller (The Shop)

3.1 The Data Controller warrants that it has a lawful basis for collecting and processing the personal data of its customers, suppliers, and staff, and for obtaining any required consents under applicable law.  
3.2 The Data Controller agrees not to store unnecessary sensitive personal data in free-text or notes fields within the Workspace.

---

## 4. Obligations of the Data Processor (Sroor Platform)

Acting as Data Processor, the Company commits to:
1. **Processing on Instructions Only:** Process Workspace data solely to deliver the Platform services in accordance with the Data Controller's documented instructions or as required by applicable law.
2. **Strict Multi-Tenant Isolation:** Provision a dedicated database and isolated storage path per tenant to prevent any cross-tenant data access.
3. **Confidentiality and Access Governance:** Restrict operator access to authorized personnel under confidentiality obligations, enforcing two-factor step-up authentication (2FA) and immutable central audit logging for administrative actions.
4. **Encryption and Secure Backups:** Encrypt data in transit via `TLS/HTTPS` and encrypt database backups at rest using `AES-256` with `SHA-256` checksum verification on a rolling `7` daily / `4` weekly / `3` monthly retention schedule.

---

## 5. Sub-processors and Hosting

5.1 The Data Controller authorizes the Company to engage cloud infrastructure and hosting sub-processors in `{{HOSTING_REGION}}` to host application servers and encrypted backup archives.  
5.2 The Company ensures that any sub-processor is bound by security and data protection obligations no less protective than those set out in this DPA.

---

## 6. Security Incident Notification and Assistance

6.1 Upon becoming aware of a confirmed personal data breach affecting the Data Controller's Workspace, the Company shall notify the Data Controller without undue delay, providing relevant details and remediation measures in accordance with Egyptian Law No. 151 of 2020.  
6.2 The Company shall reasonably assist the Data Controller, through built-in Platform features, in responding to data subject requests (access, rectification, export, or erasure).

---

## 7. Data Return and Deletion Upon Termination

7.1 The Data Controller may export its data and reports throughout the active subscription and during the `30`-day Read-Only window.  
7.2 Following the `90`-day retention period after suspension/cancellation and archiving with a verified encrypted `final` snapshot, the tenant database is permanently purged only after at least `30` days in archived status (or `90` days suspended/cancelled), with an immutable central audit record.

---

## 8. Data Protection Officer (DPO) & Contact Details

- **Data Processor:** `{{COMPANY_NAME}}` — `{{COMPANY_LEGAL_ADDRESS}}`
- **Data Protection Officer (DPO):** `{{DPO_EMAIL}}`
- **Support & Legal Contact:** `{{SUPPORT_EMAIL}}` | `{{SUPPORT_PHONE}}`
