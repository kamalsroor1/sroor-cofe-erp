# Google Play: Data safety answers (draft, MOB-05)

> Draft for the owner to copy into Play Console > App content > Data safety.
> Review it against the final app build (permissions, SDKs) before submitting. Package: `com.ibraa.app`.

## Account deletion (required)

| Play Console question | Answer |
|---|---|
| Does your app allow users to create an account? | Yes (shops are created through ibraa.app; staff accounts are created by the shop owner inside the app). |
| Delete account URL | `https://ibraa.app/account-deletion` |
| Can users request that some or all data is deleted without deleting the account? | Yes, through the same page (reason field) or support; handled manually after identity verification. |

What the page says (source: `backend/resources/legal/account-deletion.{ar,en}.md`):

- Request: public form on `/account-deletion` (name, mobile, optional email, optional shop code). Stored centrally in `account_deletion_requests`; the platform Telegram bot is alerted; an operator verifies identity within 3 working days, executes the deletion by hand within 30 days, then marks the request handled in the console (`/super-admin/account-deletion-requests`).
- Deleted: user accounts, passwords, tokens and devices; the whole shop database; uploaded files; the shop registration (subdomain/code).
- Retained: platform subscription invoices and payment receipts (tax/commercial law, up to 5 years), a short central audit record of the deletion, encrypted backups until they expire (up to 90 days), the deletion request itself.

## Data collection and security

| Question | Answer |
|---|---|
| Does your app collect or share any of the required user data types? | Yes |
| Is all of the user data collected by your app encrypted in transit? | Yes (HTTPS/TLS only) |
| Do you provide a way for users to request that their data is deleted? | Yes (URL above) |
| Is data shared with third parties? | No sale or advertising sharing. Service providers that process data on our behalf (hosting, email delivery, error monitoring) are "service providers" and are not declared as sharing. |

## Data types

| Data type (Play category) | Collected | Shared | Required / optional | Purpose | Notes |
|---|---|---|---|---|---|
| Personal info > Name | Yes | No | Required | Account management, App functionality | Staff names; customer/supplier names entered by the shop (the shop is the controller). |
| Personal info > Email address | Yes | No | Optional for staff, required for the shop owner | Account management, Communications | Signup verification and platform notices. |
| Personal info > Phone number | Yes | No | Required for the shop owner | Account management, App functionality | Customer phones entered by the shop for invoices and WhatsApp receipts. |
| Personal info > Address | Yes | No | Optional | App functionality | Shop/branch address on receipts; customer address if the shop enters it. |
| Financial info > Purchase history | Yes | No | Required | App functionality | Sales invoices, payments and balances recorded by the shop. No card numbers are collected by the app. |
| Photos and videos > Photos | Yes | No | Optional | App functionality | Shop logo and item images uploaded by the shop. |
| App activity > App interactions | Yes | No | Required | App functionality, Fraud prevention, security | Activity / audit log of who did what in the shop. |
| App info and performance > Crash logs, Diagnostics | Yes | No | Required | Analytics (stability) | Server-side error monitoring; no advertising use. |
| Device or other IDs | Yes | No | Required | Account management, Fraud prevention, security | Device name/token for sign-in sessions and quick unlock. |

Not collected: precise or approximate location, contacts, calendar, messages, health, audio, web browsing, advertising ID.

## Permissions to explain (from `backend/android/app/src/main/AndroidManifest.xml`)

| Permission | Why | Data leaves the device? |
|---|---|---|
| `CAMERA` | Barcode scanning (ML Kit, on-device) | No, only the decoded barcode text is used. |
| `BLUETOOTH_*` | Bluetooth ESC/POS receipt printers | No. |
| Biometric (plugin) | Optional quick unlock with fingerprint/face | No, the OS keeps the biometric data; the app only gets success/failure. |
| `REQUEST_INSTALL_PACKAGES` | "direct" flavor in-app update only; must NOT be declared in the `play` flavor | n/a |
| Storage (legacy, SDK limited) | Saving exported reports / receipt images | Only when the user shares them. |

## Open items for the owner

1. Fill the company details in `config/legal.php` (env `LEGAL_*`) so the legal pages leave draft mode.
2. Legal review of the privacy policy and the account-deletion page text.
3. Confirm the final list of SDKs in the `play` build (error monitoring, push) and adjust the table.
4. Make sure `REQUEST_INSTALL_PACKAGES` is removed from the `play` flavor before upload.
