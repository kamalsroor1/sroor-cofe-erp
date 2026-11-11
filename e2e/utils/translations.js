// Accessible names by translation KEY, not by copied text: the label a test looks for is read from
// the same generated catalogue the SPA ships (backend/lang/* -> `php artisan lang:export`), so a copy
// change never breaks a selector and a missing key fails loudly.
import fs from 'node:fs';
import path from 'node:path';
import { REPO_ROOT } from './local-stack.js';

const cache = new Map();

function catalogue(locale) {
    if (!cache.has(locale)) {
        const file = path.join(REPO_ROOT, `backend/resources/js/helpers/defaultTranslations.${locale}.json`);
        cache.set(locale, JSON.parse(fs.readFileSync(file, 'utf8')));
    }
    return cache.get(locale);
}

/** t('signup.form.business_name') -> the Arabic label; `:name` / `{name}` placeholders are replaced. */
export function t(key, params = {}, locale = 'ar') {
    const value = key.split('.').reduce((node, part) => (node == null ? undefined : node[part]), catalogue(locale));
    if (typeof value !== 'string') throw new Error(`translation key not found: ${key}`);
    return Object.entries(params).reduce(
        (text, [name, replacement]) =>
            text.replaceAll(`:${name}`, String(replacement)).replaceAll(`{${name}}`, String(replacement)),
        value
    );
}
