// Arabic letter (unicode escape) -> latin transliteration; data only, never shown to users.
const AR_MAP = {
    '\u0627': 'a',
    '\u0623': 'a',
    '\u0625': 'a',
    '\u0622': 'a',
    '\u0628': 'b',
    '\u062a': 't',
    '\u062b': 'th',
    '\u062c': 'g',
    '\u062d': 'h',
    '\u062e': 'kh',
    '\u062f': 'd',
    '\u0630': 'z',
    '\u0631': 'r',
    '\u0632': 'z',
    '\u0633': 's',
    '\u0634': 'sh',
    '\u0635': 's',
    '\u0636': 'd',
    '\u0637': 't',
    '\u0638': 'z',
    '\u0639': 'a',
    '\u063a': 'gh',
    '\u0641': 'f',
    '\u0642': 'q',
    '\u0643': 'k',
    '\u0644': 'l',
    '\u0645': 'm',
    '\u0646': 'n',
    '\u0647': 'h',
    '\u0629': 'a',
    '\u0648': 'w',
    '\u064a': 'y',
    '\u0649': 'a',
    '\u0621': '',
    '\u0626': 'e',
    '\u0624': 'o',
};

export const SUBDOMAIN_MIN = 3;
export const SUBDOMAIN_MAX = 30;

/** Lowercase a-z0-9 and single dashes only, no leading/trailing dash, at most 30 chars. */
export const sanitizeSubdomain = (value) =>
    String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/-{2,}/g, '-')
        .replace(/^-+/, '')
        .slice(0, SUBDOMAIN_MAX)
        .replace(/-+$/, '');

/**
 * A suggested workspace code from a shop name (Arabic is transliterated). Returns '' when the result
 * is too short, so the server picks a free one itself.
 */
export const suggestSubdomain = (name) => {
    const latin = Array.from(String(name || ''))
        .map((char) => (char in AR_MAP ? AR_MAP[char] : char))
        .join('');
    const code = sanitizeSubdomain(latin);
    return code.length >= SUBDOMAIN_MIN ? code : '';
};
