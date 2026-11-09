/**
 * Mirror of App\\Support\\WeightUnits: unit names that mean "sold by weight" (kg / g spellings, anything with كيلو).
 * Only used to pre-set the item form toggle; the server applies the same rule when the flag is not sent.
 */
const NAMES = [
    'كجم',
    'كج',
    'كغ',
    'كغم',
    'كيلو',
    'كيلوجرام',
    'كيلو جرام',
    'كيلوغرام',
    'كيلو غرام',
    'جم',
    'جرام',
    'غرام',
    'غم',
    'kg',
    'kgs',
    'kilo',
    'kilos',
    'kilogram',
    'kilograms',
    'g',
    'gm',
    'gr',
    'grm',
    'gram',
    'grams',
];

export function isWeightUnit(unit) {
    const normalized = String(unit ?? '')
        .trim()
        .toLowerCase();
    if (normalized === '') return false;
    return NAMES.includes(normalized) || normalized.includes('كيلو');
}
