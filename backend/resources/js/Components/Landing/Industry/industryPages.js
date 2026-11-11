import {
    ArrowLeftRight,
    Boxes,
    ChartColumn,
    Coffee,
    FileText,
    Receipt,
    Scale,
    ScanBarcode,
    Smartphone,
    Tags,
    Truck,
    Undo2,
} from 'lucide-vue-next';

/**
 * One /for/:industry template, driven by this config (build_pages.py INDUSTRIES). Hero copy lives in
 * landing.pages.industries.<key>, the three "designed for your daily work" cards in marketing.industry.items.<key>.
 * `visual`: the blend card + phone report (coffee) or the POS window.
 */
export const INDUSTRY_PAGES = Object.freeze({
    coffee: {
        visual: 'blend',
        items: [
            { key: 'weight', icon: Scale },
            { key: 'blend', icon: Coffee },
            { key: 'profit', icon: ChartColumn },
        ],
    },
    retail: {
        visual: 'pos',
        items: [
            { key: 'queue', icon: ScanBarcode },
            { key: 'shift', icon: Receipt },
            { key: 'stock', icon: Boxes },
        ],
    },
    wholesale: {
        visual: 'pos',
        items: [
            { key: 'pricing', icon: Tags },
            { key: 'statements', icon: FileText },
            { key: 'returns', icon: Undo2 },
        ],
    },
    vans: {
        visual: 'pos',
        items: [
            { key: 'stock', icon: Truck },
            { key: 'transfers', icon: ArrowLeftRight },
            { key: 'mobile', icon: Smartphone },
        ],
    },
});

export const industryCopyKey = (key) => `landing.pages.industries.${key}`;
