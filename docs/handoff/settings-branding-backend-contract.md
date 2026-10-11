# Settings → Branding: backend contract (BRND-3 / BRND-5)

For the Settings UI team. This file describes the backend as of W2 batch 4, lane 4A. All endpoints are under `/api/v1`. Responses use the usual envelope `{ success, message?, data? }`, and every message is translated (`X-Locale: ar|en`).

## 1. What changed for the Settings screen

- Shop logos now really persist. Each tenant has its own light and dark logo, stored in that tenant's storage. The old behaviour was different: the form ignored the files, and every tenant read the shared `public/logo*.png`.
- Logo URLs are absolute URLs of a public, host-bound route: `https://<shop-host>/api/v1/branding/logo/light?v=<hash>`. The `v` hash changes on every upload, so it can be cached forever client-side. `null` means "no logo"; show the fallback (platform logo, then a neutral icon).
- Three new tenant settings keys: `receipt_header_lines`, `receipt_footer_text` and a stricter `system_theme_color`. Section 4 has the rules.

## 2. Upload / remove a logo (preferred)

| | |
|---|---|
| Upload | `POST /api/v1/settings/branding/logo/{variant}` (multipart), field `file` |
| Remove | `DELETE /api/v1/settings/branding/logo/{variant}` |
| `variant` | `light` or `dark` (anything else is 404) |
| Auth | Bearer token + `X-Tenant` / tenant host. Permission **`settings.manage`**. Quick-login tokens are refused. |
| File rules | PNG, JPEG or WEBP only, max **2048 KB**, **64–2048 px** per side. SVG, GIF and ICO are always refused, even if renamed. The server checks the real content and re-encodes the image (metadata removed). |

Success is `200` (both upload and delete; delete is idempotent):

```json
{
  "success": true,
  "message": "تم حفظ اللوجو بنجاح.",
  "data": {
    "name": "…", "subtitle": "…",
    "logos": { "light": "https://shop.example.com/api/v1/branding/logo/light?v=3f2a…", "dark": null },
    "theme_color": "emerald",
    "phone": "…", "address": "…", "invoice_color": "emerald",
    "print": { "show_logo": true, "show_name": true, "show_subtitle": true },
    "receipt": { "header_lines": ["…"], "footer_text": "…" },
    "legal": { "commercial_register": "…", "tax_registration_no": "…" }
  }
}
```

Errors:

| Status | When |
|---|---|
| 401 | no or invalid token |
| 403 | the user lacks `settings.manage` |
| 404 | unknown `variant` |
| 422 | `errors.file[0]` holds the translated reason: missing, type not allowed, SVG, too large, bad dimensions or corrupt |

## 3. Legacy form (still supported, deprecated)

`POST /api/v1/settings` (multipart) still accepts `logo_light_file`, `logo_dark_file` and `logo_file`. A `logo_file` with no light file counts as the light logo. Those files now go through the same rules and storage as section 2.

- An invalid logo returns **422** on that field, and **nothing** from the form is saved.
- Files are stored after the text settings are committed.
- The removal is planned for BRND-11. New UI should use the dedicated endpoints.

`GET /api/v1/settings` now also returns these keys inside `settings`:

| Key | Type | Notes |
|---|---|---|
| `logo_light_url`, `logo_dark_url` | string\|null | absolute URL or null |
| `receipt_header_lines` | string | lines separated by `\n` (textarea value) |
| `receipt_footer_text` | string | empty means the receipt falls back to `invoice_footer_note` |

## 4. New / stricter settings keys (`POST /api/v1/settings`)

| Key | Rules | Error message key |
|---|---|---|
| `receipt_header_lines` | string with `\n` between lines, or an array of strings. At most **6** non-empty lines of at most **80** characters. No `<` or `>`. Stored trimmed, with blank lines dropped. | `branding.receipt_header_too_many_lines`, `branding.receipt_header_line_too_long`, `branding.receipt_text_no_markup` |
| `receipt_footer_text` | string, at most **500** characters, no `<` or `>`. When empty, prints use `invoice_footer_note`. | `branding.receipt_text_no_markup` |
| `system_theme_color` | one of `amber, emerald, blue, purple, rose, orange, teal, indigo`, or `#RRGGBB` (6 hex digits). `#fff` and `red` are refused. | `branding.theme_color_invalid` |
| `invoice_primary_color` | unchanged: `amber, emerald, blue, slate` | — |

Field labels are in `branding.attributes.*`.

## 5. Public branding (before login)

`GET /api/v1/branding`. No token. `throttle:public-api` (60/min per IP, so expect a `429` with `Retry-After`).

```json
{
  "success": true,
  "data": {
    "platform": {
      "name": "…", "short_name": "…", "subtitle": "…",
      "logos": { "light": "https://…/logo-light.png", "dark": "https://…/logo-dark.png" },
      "favicon": "https://…", "app_icon": "https://…",
      "primary_color": "#059669", "website_url": "", "support_email": "", "support_phone": ""
    },
    "tenant": null
  }
}
```

When the request resolves a shop (tenant host, or `X-Tenant` on the API host), `tenant` is filled with `{ name, subtitle, logos: { light, dark }, theme_color }`. That list is complete: no contact data, legal IDs or secrets.

## 6. Public logo file

`GET /api/v1/branding/logo/{light|dark}`. Use the URLs returned by the API; don't build them by hand.

- The shop is chosen by the request **host** only. `X-Tenant` and `?tenant=` are ignored.
- A central, console, unknown or not-ready host, or a shop with no logo, gets `404`.
- Response headers: `Content-Type` is `image/png`, `image/jpeg` or `image/webp`, plus `X-Content-Type-Options: nosniff`, `Content-Disposition: inline`, `Cache-Control: public, max-age=300` and `ETag`. A matching `If-None-Match` gets `304`.

## 7. `/api/v1/system/context` → `data.branding`

```json
"branding": {
  "platform": { …same as section 5… },
  "tenant":   { …same as the upload response data… } | null,
  "logo_light": "…", "logo_dark": "…", "logo": "…"
}
```

- `logo_light`, `logo_dark` and `logo` are kept so older screens keep working. They now hold the shop logo, falling back to the platform logo (`logo_dark` falls back to the shop light logo first). They never point at the shared `/logo.png?v=`.
- `data.system.company_*`, `commercial_register`, `tax_registration_no` and `system_theme_color` are now read through the same source. `system_theme_color` is validated: an invalid stored value comes back as `emerald`.
- `data.tenant.legal` = `{ commercial_register, tax_registration_no }` (current tenant only).

## 8. Workspace resolver

`GET /api/v1/central/tenants/resolve?code=…` → `data.logo_url` is the shop's light logo URL, or `null`. It used to be the shared `/logo.png`. There is also a new `data.logo_dark_url`. `WorkspaceConnectingState.vue` already handles `null` (`v-if`).

## 9. Not done here (follow-ups)

- `powered_by` block (BRND-12) and the platform asset upload (BRND-2, lane 4B).
- The platform-console host returns `/api/v1/branding` only after `api.branding` is added to `ResolveApiTenancy::ADMIN_HOST_ROUTES` (lane 4C).
