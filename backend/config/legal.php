<?php

/*
|--------------------------------------------------------------------------
| Public legal documents (PRD-08)
|--------------------------------------------------------------------------
|
| The Markdown sources live in resources/legal/. This file is the single source of truth for
| the document version and for every company detail substituted into them ({{TOKEN}}).
| A document is served as a draft until LEGAL_IS_DRAFT=false; a non-draft document is only
| served when every placeholder is filled (see GetPublicLegalDocumentAction).
*/

return [

    /*
    | Version recorded on signup consent (signup_requests.terms_version) and rendered into the
    | documents as {{LEGAL_VERSION}}. Bump it whenever the text changes.
    */
    'version' => (string) (env('LEGAL_VERSION') ?: '2026-11-draft-v1'),

    /*
    | true (default) = pages carry the "draft, needs legal review" banner and unfilled placeholders
    | stay visible. Set LEGAL_IS_DRAFT=false only after a lawyer has approved the texts.
    */
    'is_draft' => filter_var(env('LEGAL_IS_DRAFT', true), FILTER_VALIDATE_BOOL),

    /*
    | Company details, substituted for {{COMPANY_NAME}} etc. Leave empty until the owner supplies
    | them; the support contact falls back to the platform branding when left empty.
    */
    'placeholders' => [
        'COMPANY_NAME' => (string) env('LEGAL_COMPANY_NAME', ''),
        'COMPANY_LEGAL_ADDRESS' => (string) env('LEGAL_COMPANY_LEGAL_ADDRESS', ''),
        'COMMERCIAL_REGISTER_NO' => (string) env('LEGAL_COMMERCIAL_REGISTER_NO', ''),
        'TAX_REGISTRATION_NO' => (string) env('LEGAL_TAX_REGISTRATION_NO', ''),
        'EFFECTIVE_DATE' => (string) env('LEGAL_EFFECTIVE_DATE', ''),
        'DPO_EMAIL' => (string) env('LEGAL_DPO_EMAIL', ''),
        'HOSTING_REGION' => (string) env('LEGAL_HOSTING_REGION', ''),
        'SUPPORT_EMAIL' => (string) env('LEGAL_SUPPORT_EMAIL', ''),
        'SUPPORT_PHONE' => (string) env('LEGAL_SUPPORT_PHONE', ''),
    ],
];
