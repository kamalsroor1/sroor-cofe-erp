/**
 * SHOP-04: item barcode / price label printing — presets, settings, the print job and the label list.
 * Pure helpers (no Vue) so they are unit-tested with node --test.
 */
import { resolveSymbology, SYMBOLOGIES } from './barcode.js';

/**
 * Label sizes in millimetres. `roll` presets print one label per page (thermal label printers);
 * `sheet` presets lay a grid of labels on an A4 page.
 */
export const LABEL_PRESETS = Object.freeze({
    roll_38x25: { kind: 'roll', width: 38, height: 25 },
    roll_40x25: { kind: 'roll', width: 40, height: 25 },
    roll_50x30: { kind: 'roll', width: 50, height: 30 },
    a4_3x8: { kind: 'sheet', width: 70, height: 37, cols: 3, rows: 8 },
    a4_5x13: { kind: 'sheet', width: 38.1, height: 21.2, cols: 5, rows: 13 },
});
export const A4 = Object.freeze({ width: 210, height: 297 });

export const LABEL_PRESET_KEYS = Object.freeze(Object.keys(LABEL_PRESETS));
export const MIN_COPIES = 1;
export const MAX_COPIES = 500;
/** Hard cap on one print job so a typo (5000 copies) cannot freeze the page. */
export const MAX_LABELS = 2000;

export const DEFAULT_LABEL_SETTINGS = Object.freeze({
    preset: 'roll_38x25',
    symbology: 'auto',
    show_name: true,
    show_price: true,
    show_barcode: true,
    show_code: true,
});

const SETTINGS_STORAGE_KEY = 'item_labels_settings';
const JOB_STORAGE_KEY = 'item_labels_job';

/** Keeps only known keys with valid values; anything else falls back to the default. */
export function normalizeLabelSettings(raw) {
    const source = raw && typeof raw === 'object' ? raw : {};
    const settings = { ...DEFAULT_LABEL_SETTINGS };
    if (LABEL_PRESET_KEYS.includes(source.preset)) settings.preset = source.preset;
    if (SYMBOLOGIES.includes(source.symbology)) settings.symbology = source.symbology;
    for (const key of ['show_name', 'show_price', 'show_barcode', 'show_code']) {
        if (typeof source[key] === 'boolean') settings[key] = source[key];
    }
    return settings;
}

/** Whole number of copies within [MIN_COPIES, MAX_COPIES]; anything unparsable becomes 1. */
export function clampCopies(value) {
    const n = Math.floor(Number(value));
    if (!Number.isFinite(n) || n < MIN_COPIES) return MIN_COPIES;
    return Math.min(n, MAX_COPIES);
}

/** The slice of an item a label needs (the job is stored, so it never carries cost or stock). */
export function labelLineFromItem(item, copies = 1) {
    return {
        id: item?.id ?? null,
        name: String(item?.name ?? ''),
        code: String(item?.code ?? '').trim(),
        selling_price: item?.selling_price == null ? null : String(item.selling_price),
        copies: clampCopies(copies),
    };
}

/** Why a line cannot be printed with the given symbology, or null when it can. */
export function lineSkipReason(line, symbology = 'auto') {
    if (!line?.code) return 'no_code';
    return resolveSymbology(line.code, symbology) ? null : 'unsupported_code';
}

/**
 * Expands the job lines into one entry per printed label.
 * Lines without a printable code are skipped (and reported) only when the barcode is shown.
 * @returns {{ labels: object[], skipped: {id:any,name:string,reason:string}[], truncated: boolean }}
 */
export function buildLabels(lines, rawSettings) {
    const settings = normalizeLabelSettings(rawSettings);
    const labels = [];
    const skipped = [];
    let truncated = false;

    for (const raw of Array.isArray(lines) ? lines : []) {
        const line = labelLineFromItem(raw, raw?.copies);
        const reason = settings.show_barcode ? lineSkipReason(line, settings.symbology) : null;
        if (reason) {
            skipped.push({ id: line.id, name: line.name, reason });
            continue;
        }
        const symbology = line.code ? resolveSymbology(line.code, settings.symbology) : null;
        for (let copy = 0; copy < line.copies; copy++) {
            if (labels.length >= MAX_LABELS) {
                truncated = true;
                break;
            }
            labels.push({
                key: `${line.id}-${copy}`,
                item_id: line.id,
                name: line.name,
                code: line.code,
                price: line.selling_price,
                symbology,
            });
        }
        if (truncated) break;
    }
    return { labels, skipped, truncated };
}

/** Groups labels into printed pages: one label per page on a roll, cols x rows on an A4 sheet. */
export function paginateLabels(labels, presetKey) {
    const preset = LABEL_PRESETS[presetKey] || LABEL_PRESETS[DEFAULT_LABEL_SETTINGS.preset];
    const perPage = preset.kind === 'sheet' ? preset.cols * preset.rows : 1;
    const pages = [];
    for (let i = 0; i < labels.length; i += perPage) pages.push(labels.slice(i, i + perPage));
    return pages;
}

const mm = (value) => `${Number(value.toFixed(2))}mm`;

/** Page geometry: page size plus the centring padding of an A4 grid. */
export function pageGeometry(presetKey) {
    const preset = LABEL_PRESETS[presetKey] || LABEL_PRESETS[DEFAULT_LABEL_SETTINGS.preset];
    if (preset.kind !== 'sheet') {
        return { width: preset.width, height: preset.height, padX: 0, padY: 0, preset };
    }
    return {
        width: A4.width,
        height: A4.height,
        padX: Math.max(0, (A4.width - preset.cols * preset.width) / 2),
        padY: Math.max(0, (A4.height - preset.rows * preset.height) / 2),
        preset,
    };
}

/** The @page rule for the chosen preset (injected while the print page is open). */
export function pageCss(presetKey) {
    const { width, height } = pageGeometry(presetKey);
    return `@page { size: ${mm(width)} ${mm(height)}; margin: 0; }`;
}

function storage(kind) {
    try {
        return kind === 'session' ? globalThis.sessionStorage : globalThis.localStorage;
    } catch {
        return null;
    }
}

function readJson(kind, key) {
    try {
        const raw = storage(kind)?.getItem(key);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeJson(kind, key, value) {
    try {
        storage(kind)?.setItem(key, JSON.stringify(value));
        return true;
    } catch {
        return false;
    }
}

/** Last label settings used on this device (a per-device convenience, never required). */
export const loadLabelSettings = () => normalizeLabelSettings(readJson('local', SETTINGS_STORAGE_KEY));
export const saveLabelSettings = (settings) =>
    writeJson('local', SETTINGS_STORAGE_KEY, normalizeLabelSettings(settings));

// In-memory copy so the print page still works when session storage is blocked.
let memoryJob = null;

/** Hands the print job to the print page (survives a reload of that page through session storage). */
export function saveLabelJob(job) {
    memoryJob = {
        settings: normalizeLabelSettings(job?.settings),
        lines: (job?.lines || []).map((line) => labelLineFromItem(line, line.copies)),
    };
    writeJson('session', JOB_STORAGE_KEY, memoryJob);
    return memoryJob;
}

export function loadLabelJob() {
    const stored = readJson('session', JOB_STORAGE_KEY) || memoryJob;
    if (!stored || !Array.isArray(stored.lines) || stored.lines.length === 0) return null;
    return {
        settings: normalizeLabelSettings(stored.settings),
        lines: stored.lines.map((line) => labelLineFromItem(line, line.copies)),
    };
}
